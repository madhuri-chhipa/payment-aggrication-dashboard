<?php

namespace App\Http\Controllers\user;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\UserWalletTransaction;

class RazorpayVirtualController extends Controller
{
  private string $SERVICE_NAME = 'RAZORPAY_VIRTUAL';
  private string $API_KEY = 'rzp_live_SCPnua4OrdQCrP';
  private string $API_SECRET = 'GaX5gf3Y1Iw6hm2SHScAiPCp';
  private string $CUSTOMER_URL = 'https://api.razorpay.com/v1/customers';
  private string $ACCOUNT_URL  = 'https://api.razorpay.com/v1/virtual_accounts';

  /**
   * Create Razorpay customer
   */
  private function createCustomer(string $name, string $email, string $userId): array
  {
    $payload = [
      'name' => $name,
      'email' => $email,
      'fail_existing' => '0',
    ];

    return $this->razorpayRequest('POST', $this->CUSTOMER_URL, $payload, [
      'action' => 'createCustomer',
      'user_id' => $userId,
    ]);
  }

  /**
   * Create Virtual Account + save to DB
   * (Your old name verifyBankAccount - but it creates VA, not verify bank)
   */
  public function createVirtualAccount(Request $request)
  {
    $validated = $request->validate([
      'bene_name'   => ['required', 'string', 'max:150'],
      'bene_account' => ['required', 'string', 'max:50'],
      'bene_ifsc'   => ['required', 'string', 'max:20'],
      'bene_email'  => ['required', 'email', 'max:150'],
      'bene_mobile' => ['required', 'string', 'max:20'],
      'bene_proof'  => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
    ]);
    $userId = Auth::id();
    $proofPath = null;
    if ($request->hasFile('bene_proof')) {
      $proofPath = $request->file('bene_proof')->store('virtual_beneficiary_proofs', 'public');
    }
    DB::table('user_virtual_beneficiaries')->insert([
      'user_id'         => $userId,
      'bene_name'       => $validated['bene_name'],
      'bene_account'    => $validated['bene_account'],
      'bene_ifsc'       => strtoupper($validated['bene_ifsc']),
      'bene_email'      => $validated['bene_email'],
      'bene_mobile'     => $validated['bene_mobile'],
      'bene_proof_path' => $proofPath,
      'created_at'      => now(),
      'updated_at'      => now(),
    ]);
    $createCustomer = $this->createCustomer($validated['bene_name'], $validated['bene_email'], $userId);
    if (($createCustomer['status'] ?? null) !== 'S') {
      return response()->json([
        'success' => false,
        'status'  => 'F',
        'message' => 'Failed to create customer.',
        'response' => $createCustomer,
      ], 422);
    }

    $customerId = data_get($createCustomer, 'data.id');

    $payload = [
      'customer_id' => $customerId,
      'receivers' => [
        'types' => ['bank_account'],
      ],
      'description' => 'Customer Identifier created for Bhatiserve',
      'notes' => [
        'project_name' => 'Banking Software',
      ],
    ];

    $vaResp = $this->razorpayRequest('POST', $this->ACCOUNT_URL, $payload, [
      'action' => 'createVirtualAccount',
      'user_id' => $userId,
      'customer_id' => $customerId,
    ]);

    if (($vaResp['status'] ?? null) !== 'S') {
      return response()->json([
        'success' => false,
        'status'  => 'F',
        'message' => 'Failed to create Virtual Account.',
        'response' => $vaResp,
      ], 422);
    }

    $data = $vaResp['data'] ?? [];
    $receiver = data_get($data, 'receivers.0', []);

    $insertData = [
      'user_id'            => $userId,
      'virtual_account_id' => data_get($data, 'id'),
      'customer_id'        => data_get($data, 'customer_id'),
      'receiver_id'        => data_get($receiver, 'id'),
      'entity'             => data_get($data, 'entity'),
      'status'             => 'A',
      'description'        => data_get($data, 'description'),
      'bank_name'          => data_get($receiver, 'bank_name'),
      'account_number'     => data_get($receiver, 'account_number'),
      'ifsc'               => data_get($receiver, 'ifsc'),
      'name'               => data_get($receiver, 'name'),
      'created_at'         => now(),
      'created_on'         => now(),
    ];

    DB::table('users_virtual_accounts')->insert($insertData);

    return response()->json([
      'success' => true,
      'status'  => 'S',
      'message' => 'Virtual Account created and saved successfully.',
      'data'    => $insertData,
    ]);
  }

  /**
   * Razorpay Virtual Txn webhook callback
   */
  public function virtualTxnCallback(Request $request)
  {
    $url = $request->fullUrl();
    $headers = $request->headers->all();
    $raw = $request->getContent();

    $this->writeLogs('Razorpaycollect_Callback', [
      'url' => $url,
      'headers' => $headers,
      'payload' => $raw,
    ]);

    $data = json_decode($raw, true) ?: [];
    $event = $data['event'] ?? null;

    // 1) Virtual account closed -> mark blocked
    if ($event === 'virtual_account.closed') {
      $virtualAccountId = data_get($data, 'payload.virtual_account.entity.id');

      if (!$virtualAccountId) {
        return response()->json([
          'status' => 'F',
          'message' => 'virtual_account_id not found in webhook payload.',
        ], 400);
      }

      $updated = DB::table('users_virtual_accounts')
        ->where('virtual_account_id', $virtualAccountId)
        ->update([
          'status' => 'B',
          'updated_at' => now(),
        ]);

      if ($updated) {
        return response()->json([
          'status' => 'S',
          'message' => 'Virtual account marked as Blocked (B).',
        ]);
      }

      return response()->json([
        'status' => 'F',
        'message' => 'Failed to update virtual account status.',
      ], 500);
    }

    // 2) Payment captured -> credit wallet (your old logic)
    $virtualAccountId = data_get($data, 'payload.virtual_account.entity.id');
    $amountPaise      = (int) (data_get($data, 'payload.payment.entity.amount') ?? 0);
    $amountRupees     = $amountPaise / 100;

    $bankReference = data_get($data, 'payload.bank_transfer.entity.bank_reference');
    $mode          = data_get($data, 'payload.bank_transfer.entity.mode');
    $customerId    = data_get($data, 'payload.payment.entity.customer_id');
    $paymentId     = data_get($data, 'payload.payment.entity.id');
    $status        = data_get($data, 'payload.payment.entity.status');
    $payerAccNo     = data_get($data, 'payload.bank_transfer.entity.payer_bank_account.account_number');
    $accountDetail = DB::table('users_virtual_accounts')
      ->select('user_id')
      ->where('virtual_account_id', $virtualAccountId)
      ->where('customer_id', $customerId)
      ->first();
    $reqDetail = DB::table('fund_requests')
      ->where('transaction_utr', $bankReference)
      ->where('status', 'A')
      ->where('amount', $amountRupees)
      ->first();
    if (!$reqDetail && $accountDetail && $status === 'captured') {
      $userId = (string) $accountDetail->user_id;
      $requestId = 'VCR' . now()->format('YmdHis') . rand(100, 999);
      $creditOk = $this->creditUserWalletAmount($userId, $paymentId, $amountRupees);
      if ($creditOk) {
        $insData = [
          'user_id' => $userId,
          'request_id' => $requestId,
          'mode' => $mode,
          'company_account_number' => $accountDetail->account_number,
          'wallet_txn_id' => $paymentId,
          'transaction_utr' => $bankReference,
          'amount' => $amountRupees,
          'remarks' => 'VIRTUAL ACCOUNT /' . ($payerAccNo ?? ''),
          'refAmount' => $amountRupees,
          'status' => 'A',
          'created_at' => now(),
          'updated_at' => now(),
          'description' => 'Webhook Request captured',
          'source' => 'WEBHOOK',
          'sender_account_number' => $payerAccNo,
        ];
        $ok = DB::table('user_fund_requests')->insert($insData);
        if ($ok) {
          return response()->json([
            'status' => 'S',
            'message' => 'Request submitted successfully with reference number - ' . $requestId . '.',
          ]);
        }
        return response()->json([
          'status' => 'F',
          'message' => 'Failed to create request. Check Detail and Try Again.',
        ], 500);
      }
      return response()->json([
        'status' => 'F',
        'message' => 'Failed to update wallet credit.',
      ], 500);
    }
    return response()->json([
      'status' => 'F',
      'message' => 'Failed to update - Amount Already Paid.',
    ], 409);
  }

  /**
   * Razorpay API request wrapper
   */
  private function razorpayRequest(
    string $method,
    string $url,
    array $payload = [],
    array $context = []
  ): array {
    try {
      /** @var Response $resp */
      $resp = Http::withBasicAuth($this->API_KEY, $this->API_SECRET)
        ->acceptJson()
        ->asJson()
        ->timeout(30)
        ->retry(2, 200) // retry 2 times with 200ms delay
        ->send($method, $url, [
          'json' => $payload,
        ]);

      $body = $resp->json();

      // Safe logging (avoid sensitive data exposure)
      $this->writeLogs('Razorpay_Request', [
        'context'      => $context,
        'method'       => $method,
        'url'          => $url,
        'payload'      => $payload,
        'http_status'  => $resp->status(),
        'response'     => $body,
      ]);

      /**
       * Razorpay success condition:
       * - HTTP 2xx
       * - Response contains "id" OR no "error" key
       */
      if ($resp->successful() && !isset($body['error'])) {
        return [
          'status'      => 'S',
          'http_status' => $resp->status(),
          'data'        => $body,
        ];
      }

      // Razorpay structured error response
      return [
        'status'      => 'F',
        'http_status' => $resp->status(),
        'message'     => $body['error']['description'] ?? 'Razorpay API error',
        'data'        => $body,
      ];
    } catch (\Throwable $e) {

      // Exception logging
      $this->writeLogs('Razorpay_Exception', [
        'context' => $context,
        'method'  => $method,
        'url'     => $url,
        'payload' => $payload,
        'error'   => $e->getMessage(),
      ]);

      return [
        'status'  => 'F',
        'message' => $e->getMessage(),
      ];
    }
  }

  private function creditUserWalletAmount(
    int $userId,
    string $paymentId,
    float $amount,
    string $serviceName = 'VIRTUAL_ACCOUNT',
    string $description = 'Virtual Account Credit'
  ): bool {

    return DB::transaction(function () use (
      $userId,
      $paymentId,
      $amount,
      $serviceName,
      $description
    ) {
      $user = DB::table('users')
        ->where('id', $userId)
        ->lockForUpdate()
        ->first(['id', 'virtual_balance']);
      if (!$user) {
        throw new \RuntimeException("User not found: {$userId}");
      }
      $openingBalance = (float) $user->virtual_balance;
      $closingBalance = $openingBalance + $amount;
      DB::table('users')
        ->where('id', $userId)
        ->update([
          'virtual_balance' => $closingBalance,
        ]);
      UserWalletTransaction::create([
        'user_id'          => $userId,
        'service_name'     => $serviceName,
        'refid'            => $paymentId,
        'opening_balance'  => $openingBalance,
        'total_charge'     => 0,
        'total_amount'     => $amount,
        'amount'           => $amount,
        'closing_balance'  => $closingBalance,
        'credit'           => $amount,
        'debit'            => 0,
        'description'      => $description,
        'created_at'       => now(),
        'updated_at'       => now(),
      ]);

      return true;
    }, 3);
  }

  /**
   * Logs to channel name: RAZORPAY_VIRTUAL
   */
  private function writeLogs(string $tag, array $data = []): void
  {
    Log::channel($this->SERVICE_NAME)->info($tag, $data);
  }
}