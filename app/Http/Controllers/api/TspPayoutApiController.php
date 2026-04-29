<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\PayoutTransaction;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserService;
use App\Models\UserApiKey;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Services\FlipzikPayoutService;
use App\Services\BulkpePayoutService;
use App\Services\PaywizePayoutService;
use App\Services\BuckboxPayoutService;
use App\Services\RunpaisaPayoutService;
use App\Models\UserWalletTransaction;
use App\Models\UserVirtualAccount;
use App\Models\FundRequest;
use Illuminate\Support\Facades\Log;

class TspPayoutApiController extends Controller
{
  protected $flipzikService;
  protected $paywizeService;
  protected $bulkpeService;
  protected $buckboxService;
  protected $runpaisaService;

  public function __construct(FlipzikPayoutService $flipzikService, PaywizePayoutService $paywizeService, BulkpePayoutService $bulkpeService, BuckboxPayoutService $buckboxService, RunpaisaPayoutService $runpaisaService)
  {
    $this->flipzikService = $flipzikService;
    $this->paywizeService = $paywizeService;
    $this->bulkpeService = $bulkpeService;
    $this->buckboxService = $buckboxService;
    $this->runpaisaService = $runpaisaService;
  }
  public function flipzikcallback(Request $request)
  {
    return $this->flipzikService->handlePayoutCallback();
  }
  public function paywizecallback(Request $request)
  {
    return $this->paywizeService->handlePayoutCallback();
  }
  public function bulkpecallback(Request $request)
  {
    return $this->bulkpeService->handlePayoutCallback();
  }
  public function buckboxcallback(Request $request)
  {
    return $this->buckboxService->handlePayoutCallback();
  }
  public function runpaisacallback(Request $request)
  {
    return $this->runpaisaService->handlePayoutCallback();
  }
  public function tsppayoutMoneyTransfer(Request $request)
  {
    $user = $this->authenticateRequest($request);
    if ($user instanceof \Illuminate\Http\JsonResponse) return $user;
    if (!$user) return response()->json(['status' => 'FAILED', 'message' => 'Please contact admin.'], 400);

    $user_id = $user->id;

    $userApis = UserService::where('user_id', $user_id)->first();
    if (!$userApis || $userApis->payout_status === 'B' || $user->active != 'A') {
      return response()->json(['status' => 'FAILED', 'message' => 'API is disabled. Please contact admin.'], 400);
    }

    $refid = $request->input('txn_id');
    if (empty($refid) || !preg_match('/^(?=.*[A-Za-z])[A-Za-z0-9]{8,12}$/', $refid)) {
      return response()->json(['status' => 'FAILED', 'message' => 'Reference ID must be 8–12 alphanumeric characters and contain at least one letter.'], 400);
    }


    $fieldLabels = [
      'bene_account_number' => 'Beneficiary account number',
      'bene_mobile'         => 'Beneficiary mobile number',
      'bene_email'          => 'Beneficiary email address',
      'bank_name'           => 'Bank name',
      'bank_branch'         => 'Bank branch',
      'ifsc_code'           => 'IFSC code',
      'bene_name'           => 'Beneficiary name',
      'amount'              => 'Amount',
      'transfer_mode'       => 'Transfer mode',
    ];
    $requiredFields = array_keys($fieldLabels);

    foreach ($requiredFields as $field) {
      if (empty($request->input($field))) {
        return response()->json([
          'status'  => 'FAILED',
          'message' => $fieldLabels[$field] .
            ' is required. Please provide a valid value in "' .
            $field . '".'
        ], 400);
      }
    }


    if (PayoutTransaction::where('txn_id', $refid)->exists()) {
      return response()->json(['status' => 'FAILED', 'message' => 'Reference ID already exists. Please provide unique reference ID.'], 400);
    }

    $amount            = $request->input('amount');
    if (
      !is_numeric($amount) || $amount < $userApis->minimum_transaction || $amount > $userApis->maximum_transaction
    ) {
      return response()->json([
        'status'  => 'FAILED',
        'message' => 'Amount must be between ' .
          $userApis->minimum_transaction .
          ' and ' .
          $userApis->maximum_transaction . '.'
      ], 400);
    }
    $bene_account      = $request->input('bene_account_number');
    $ifsc_code         = $request->input('ifsc_code');
    $beneficiary_name  = $request->input('bene_name');
    $mobile           = $request->input('bene_mobile') ?? $user->phone_number;
    $email            = $request->input('bene_email') ?? $user->email;
    $payment_mode      = 'IMPS';
    $active_api        = $userApis->payout_api_active_api;

    $charge_amount = 0;

    if ($userApis->virtual_type === 'percentage') {
      if ($amount <= 500) {
        $charge_amount = round($userApis->pflate_charges, 2);
      } elseif ($amount > 500 && $amount <= 1000) {
        $charge_amount = round($userApis->pflate_charges2, 2);
      } elseif ($amount > 1000) {
        $charge_amount = round(($amount * $userApis->virtual_charges) / 100, 2);
      } else {
        $charge_amount = round(($amount * 1) / 100, 2); // Fallback case
      }
    } elseif ($userApis->virtual_type === 'flatrate') {
      if ($amount <= 1000) {
        $charge_amount = round($userApis->pslab_1000, 2);
      } elseif ($amount <= 25000) {
        $charge_amount = round($userApis->pslab_25000, 2);
      } elseif ($amount > 50000) {
        $charge_amount = round($userApis->pslab_200000, 2);
      } else {
        $charge_amount = round(($amount * 1) / 100, 2); // 1%
      }
    } else {
      $charge_amount = round(($amount * 1) / 100, 2); // 1%
    }
    $platform_charge = round($userApis->platform_fee, 2);
    $gst_amount = round($charge_amount * 0.18, 2); // 18% GST
    $total_service_amount = $charge_amount + $gst_amount + $platform_charge;
    $usable_balance = $user->virtual_balance;
    $checkamount         = number_format($total_service_amount, 2, '.', '');
    if (bccomp($usable_balance, $checkamount, 2) === -1) {
      return response()->json([
        'status'  => 'FAILED',
        'message' => 'Insufficient balance. Please add balance in your virtual wallet.'
      ], 400);
    }
    try {
      $total_amountdebit = DB::transaction(function () use (
        $user,
        $refid,
        $amount,
        $charge_amount,
        $platform_charge,
        $gst_amount,
        $bene_account,
        $ifsc_code,
        $beneficiary_name,
        $payment_mode,
        $mobile,
        $email,
        $active_api
      ) {
        $lockedUser = User::where('id', $user->id)
          ->lockForUpdate()
          ->first();
        if (!$lockedUser) {
          return [
            'success' => false,
            'message' => $walletResult['message'] ?? 'Wallet debit failed.',
          ];
        }
        $walletResult = $this->debitUserWalletAmount(
          $lockedUser,
          $refid,
          $amount,
          $charge_amount,
          $gst_amount
        );
        if (empty($walletResult['status'])) {
          return [
            'success' => false,
            'message' => $walletResult['message'] ?? 'Wallet debit failed.',
          ];
        }
        $total_amount = $walletResult['total_amount'];
        PayoutTransaction::create([
          'user_id'          => $lockedUser->id,
          'txn_id'           => $refid,
          'bene_account'     => $bene_account,
          'bene_ifsc'        => $ifsc_code,
          'bene_name'        => $beneficiary_name,
          'bene_phone'        =>  $mobile,
          'bene_email'        =>  $email,
          'transfer_mode'    => $payment_mode,
          'amount'           => $amount,
          'charge_amount'    => $charge_amount,
          'platform_fee'    => $platform_charge,
          'gst_amount'       => $gst_amount,
          'total_charge'     => $charge_amount + $gst_amount,
          'total_amount'     => $total_amount,
          'status'           => 'P',
          'payment_status'   => 'P',
          'response_message' => 'Transaction initiated',
          'description'      => 'Transaction initiated',
          'created_by'       => 'API',
          'updated_by'       => 'API',
          'api'              => $active_api,
          'ip'               => request()->ip(),
          'created_at'       => now(),
          'updated_at'       => now(),
        ]);
        return [
          'success'      => true,
          'total_amount' => $total_amount,
        ];
      });
      if ($total_amountdebit['success'] === false) {
        return response()->json([
          'status'      => 'FAILED',
          'message'     => 'Unable to initiate transaction. Please try again.',
          'status_code' => 400,
        ], 400);
      }
    } catch (\Throwable $e) {
      Log::error("Payout init failed for {$refid}: " . $e->getMessage());
      return response()->json([
        'status'      => 'FAILED',
        'message'     => 'Unable to initiate transaction. Please try again.',
        'status_code' => 500,
      ], 500);
    }
    $user_id = $user->id;
    $Payouttxn = PayoutTransaction::where('txn_id', $refid)->exists();
    $wallettxn = UserWalletTransaction::where('refid', $refid)->exists();
    if ($Payouttxn && $wallettxn) {
      if ($active_api === 'Flipzik') {
        $api_response = $this->flipzikService->initiateTransaction($refid, $bene_account, $ifsc_code, $amount, $mobile, $email, $beneficiary_name);
      } elseif ($active_api === 'Bulkpe') {
        $api_response = $this->bulkpeService->initiateTransaction($refid, $bene_account, $ifsc_code, $amount, $mobile, $email, $beneficiary_name, $user);
      } elseif ($active_api === 'Paywize') {
        $api_response = $this->paywizeService->initiateTransaction($refid, $bene_account, $ifsc_code, $amount, $beneficiary_name);
      } elseif ($active_api === 'Buckbox') {
        $api_response = $this->buckboxService->initiateTransaction($refid, $bene_account, $ifsc_code, $amount, $mobile, $email, $beneficiary_name, $user);
      } elseif ($active_api === 'Runpaisa') {
        $api_response = $this->runpaisaService->initiateTransaction($refid, $bene_account, $ifsc_code, $amount, $mobile, $email, $beneficiary_name);
      } elseif ($active_api === 'UnionBank') {
        $api_response = $this->runpaisaService->initiateTransaction($refid, $bene_account, $ifsc_code, $amount, $mobile, $email, $beneficiary_name);
      } else {
        return response()->json(['status' => 'FAILED', 'message' => 'Unsupported payout API Please contact to Admin'], 400);
      }
      $decoded = $api_response->getData(); // ✅ returns stdClass
      $status     = strtoupper($decoded->status ?? 'P');
      $message    = $decoded->message ?? 'Transaction processed';
      $utr        = $decoded->utr ?? null;
      $api_txn_id = $decoded->api_txn_id ?? null;


      $updateCommon = [
        'api_txn_id'       => $api_txn_id,
        'utr'              => $utr,
        'updated_by'       => 'API',
        'updated_at'       => now(),
        'response_message' => $message,
      ];

      if ($status === 'S') {
        $updateFinal = array_merge($updateCommon, [
          'status'       => 'S',
          'processed_at' => now(),
          'processed_by' => 'API',
          'description'  => $message ?? 'Transaction successful',
        ]);
      } elseif ($status === 'P') {
        $updateFinal = array_merge($updateCommon, [
          'status'       => 'P',
          'processed_at' => now(),
          'processed_by' => 'API',
          'description'  => $message,
        ]);
      } elseif ($status === 'F') {
        $updateFinal = array_merge($updateCommon, [
          'status'       => 'R',
          'processed_at' => now(),
          'processed_by' => 'API',
          'description'  => 'Transaction failed',
        ]);
        $this->creditUserWalletAmount($user, $refid, $amount, $charge_amount, $gst_amount);
      } else {
        $updateFinal = array_merge($updateCommon, [
          'description' => 'Awaiting confirmation',
        ]);
      }
      $affectedRows = DB::table('payout_transactions')
        ->where('txn_id', $refid)
        ->update($updateFinal);
      Log::info("Updated payout_transactions for txn_id {$refid}, affected rows: {$affectedRows}");
      return $this->buildApiResponse($status, $message, null, $refid, $api_txn_id, $utr, $payment_mode);
    } else {
      Log::error("Payout API call failed for {$refid}:");
      return response()->json([
        'status'  => 'FAILED',
        'message'     => 'Transaction is failed.',
        'status_code' => 400,
        'data' => [
          'txn_status'  => 'FAILED',
          'transaction_id' => $refid, // client-side reference
          'reference_id'   => null,          // server-side reference
          'bank_reference' => null,  // UTR / RRN from bank
          'transfer_mode'  => 'IMPS',
          'timestamp'      => now()->toIso8601String(),
        ],
      ], 201);
    }
  }

  public function tsppayoutcheckbalance(Request $request)
  {
    $user = $this->authenticateRequest($request);
    if ($user instanceof \Illuminate\Http\JsonResponse) return $user;
    if (!$user) {
      return response()->json([
        'status'  => 'FAILED',
        'message' => 'User not found, please contact admin.',
      ], 400);
    }
    if ($user->active != 'A') {
      return response()->json([
        'status'  => 'FAILED',
        'message' => 'User is disabled, please contact admin.',
      ], 400);
    }
    return response()->json([
      'status' => 'SUCCESS',
      'message' => 'Wallet balance retrieved successfully.',
      'email'          => $user->email,
      'company_name'   => $user->company_name,
      'timestamp' => now()->toDateTimeString(),
      'wallet' => [
        'wallet_balance' => $user->virtual_balance ?? 0.00,
      ]
    ]);
  }
  public function tsppayoutCheckstatus(Request $request)
  {
    $user = $this->authenticateRequest($request);
    if ($user instanceof \Illuminate\Http\JsonResponse) return $user;

    if (!$user) {
      return response()->json([
        'status'  => 'FAILED',
        'message' => 'User not found. Please contact admin.',
        'data'    => null
      ], 400);
    }

    $userApis = UserService::where('user_id', $user->id)->first();

    if (!$userApis || $userApis->payout_status === 'B' || $user->active != 'A') {
      return response()->json([
        'status'  => 'FAILED',
        'message' => 'Payout API is disabled. Please contact admin.',
        'data'    => null
      ], 400);
    }

    $refid  = $request->input('txn_id');
    $payout = PayoutTransaction::where('txn_id', $refid)->first();

    if (!$payout) {
      return response()->json([
        'status'  => 'FAILED',
        'message' => 'Transaction not found.',
        'data'    => null
      ], 404);
    }

    $status = strtoupper($payout->status); // S | F | P | R

    $statusText = match ($status) {
      'S' => 'SUCCESS',
      'F', 'R' => 'FAILED',
      'P' => 'PENDING',
      default => 'PENDING',
    };

    $statusCode = match ($status) {
      'S' => 200,
      'P' => 202,
      'F', 'R' => 400,
      default => 400,
    };

    return response()->json([
      'status' => 'SUCCESS',
      'message' => $payout->response_message ?? 'Transaction status fetched successfully.',
      'data'    => [
        'txn_status'  => $statusText,
        'transaction_id' => $payout->txn_id,
        'reference_id'   => $payout->api_txn_id,
        'bank_reference' => $payout->utr,
        'transfer_mode'  => 'IMPS',
        'timestamp'      => now()->toIso8601String(),
      ]
    ], $statusCode);
  }
  private function authenticateRequest(Request $request)
  {
    $clientSecret = trim((string) $request->header('client_secret'));
    $authHeader   = trim((string) $request->header('Authorization'));
    $ip           = (string) $request->ip();

    if ($clientSecret === '') {
      return response()->json([
        'status'  => 'FAILED',
        'message' => 'Missing client_secret header.',
      ], 401);
    }

    if ($authHeader === '') {
      return response()->json([
        'status'  => 'FAILED',
        'message' => 'Missing Authorization header.',
      ], 401);
    }

    if (!str_starts_with($authHeader, 'Bearer ')) {
      return response()->json([
        'status'  => 'FAILED',
        'message' => 'Invalid Authorization format. Expected: Bearer {token}.',
      ], 401);
    }

    $token = trim(substr($authHeader, 7));
    if ($token === '') {
      return response()->json([
        'status'  => 'FAILED',
        'message' => 'Authorization token is empty.',
      ], 401);
    }
    $apiKeyRow = UserApiKey::query()
      ->select(['id', 'user_id', 'client_secret', 'ip'])
      ->get()
      ->first(function ($row) use ($clientSecret) {
        try {
          return Crypt::decryptString((string) $row->client_secret) === $clientSecret;
        } catch (\Throwable $e) {
          return false;
        }
      });

    if (!$apiKeyRow) {
      return response()->json([
        'status'  => 'FAILED',
        'message' => 'Invalid Client Secret.',
      ], 401);
    }
    $user = User::find($apiKeyRow->user_id);
    if (!$user) {
      return response()->json([
        'status'  => 'FAILED',
        'message' => 'User not found.',
      ], 401);
    }
    if ($user->auth_token !== $token) {
      return response()->json([
        'status'  => 'FAILED',
        'message' => 'Invalid or mismatched authorization token.',
      ], 401);
    }

    if ($user->auth_token_expiry && now()->greaterThan($user->auth_token_expiry)) {
      return response()->json([
        'status'  => 'FAILED',
        'message' => 'Authorization token has expired. Please re-authenticate.',
      ], 401);
    }

    $allowedIps = array_values(array_filter(array_map('trim', explode(',', (string) $apiKeyRow->ip))));
    if (!in_array($ip, $allowedIps, true)) {
      return response()->json([
        'status'  => 'FAILED',
        'message' => 'Access denied. Your IP address is not authorized.',
      ], 403);
    }
    return $user;
  }

  private function debitUserWalletAmount($user, $refid, $amount, $charge_amount, $gst_amount)
  {
    $isInTransaction = DB::transactionLevel() > 0;
    if (!$isInTransaction) {
      DB::beginTransaction();
    }

    try {
      $user = User::where('id', $user->id)
        ->lockForUpdate()
        ->first();

      $wallet_balance = $user->virtual_balance;
      $total_charge = $charge_amount + $gst_amount;
      $total_amount = $amount + $total_charge;

      if ($wallet_balance < $total_charge) {
        if (!$isInTransaction) {
          DB::rollBack();
        }
        return ['status' => false, 'message' => 'Insufficient balance'];
      }

      $opening_balance = $wallet_balance;
      $closing_balance = $opening_balance - $total_charge;

      DB::table('user_wallet_transactions')->insert([
        'user_id'         => $user->id,
        'service_name'    => 'SELFPAYOUT',
        'refid' => $refid,
        'opening_balance' => $opening_balance,
        'total_charge'    => $total_charge,
        'amount'          => $amount,
        'total_amount'    => $total_amount,
        'closing_balance' => $closing_balance,
        'credit'          => 0.00,
        'debit'           => $total_amount,
        'description'     => $user->uid . '/SELFPAYOUT/' . $refid,
        'created_at'      => now(),
        'updated_at'      => now(),
      ]);

      DB::table('users')
        ->where('id', $user->id)
        ->update([
          'virtual_balance' => $closing_balance,
        ]);

      if (!$isInTransaction) {
        DB::commit();
      }

      return [
        'status'          => true,
        'opening_balance' => $opening_balance,
        'closing_balance' => $closing_balance,
        'total_amount'    => $total_amount,
      ];
    } catch (\Exception $e) {
      if (!$isInTransaction) {
        DB::rollBack();
      }
      Log::error("Wallet debit failed: " . $e->getMessage());
      return ['status' => false, 'message' => 'Wallet debit failed: ' . $e->getMessage()];
    }
  }
  public function virtualcallback(Request $request)
  {
    $url = request()->fullUrl();
    $headers = request()->headers->all();
    $request_payload = file_get_contents('php://input');
    $data = json_decode($request_payload, true);
    $this->writeLogs('VIRTUAL', 'Payout_Callback', $url, $request_payload, json_encode($headers), 200, json_encode($data));
    $event = $data['event'] ?? null;
    $virtual_account_id   = $data['payload']['virtual_account']['entity']['id'] ?? null;
    $amount_paise         = $data['payload']['payment']['entity']['amount'] ?? null;
    $amount       = is_numeric($amount_paise) ? ($amount_paise / 100) : null;
    $bank_reference       = $data['payload']['bank_transfer']['entity']['bank_reference'] ?? null;
    $mode                 = $data['payload']['bank_transfer']['entity']['mode'] ?? null;
    $customer_id          = $data['payload']['payment']['entity']['customer_id'] ?? null;
    $payment_id           = $data['payload']['payment']['entity']['id'] ?? null;
    $payment_status       = $data['payload']['payment']['entity']['status'] ?? null;
    $payer_account_number = $data['payload']['bank_transfer']['entity']['payer_bank_account']['account_number'] ?? null;
    if ($event === 'virtual_account.credited' && $payment_status === 'captured') {
      if (UserWalletTransaction::where('refid', $payment_id)->exists()) {
        return response()->json([
          'status' => 'failed',
          'message' => 'Reference ID already exists'
        ], 400);
      }
      $userVirtual = UserVirtualAccount::where('virtual_account_id', $virtual_account_id)
        ->where('customer_id', $customer_id)
        ->first();

      if (!$userVirtual) {
        return response()->json([
          'status' => 'failed',
          'message' => 'Virtual account mapping not found'
        ], 404);
      }

      $user_id = $userVirtual->user_id;
      $user = User::where('id', $user_id)->first();

      if (!$user) {
        return response()->json([
          'status' => 'failed',
          'message' => 'User not found'
        ], 404);
      }
      do {
        $requestId = 'FUNDR' . random_int(1000000, 9999999);
      } while (FundRequest::where('request_id', $requestId)->exists());
      FundRequest::create([
        'user_id'                => $user_id,
        'amount'                 => $amount,
        'sender_account_number'  => $payer_account_number,
        'company_account_number' => 10,
        'transaction_utr'        => $bank_reference,
        'transaction_datetime'   => now(),
        'remark'                 => 'VCREDIT/' . $mode . '/' . $payment_id,
        'request_id'             => $requestId,
        'status'                 => 'S',
      ]);
      $creditRes = $this->creditUserWalletAmountReq($user, $payment_id, $amount, 0, 0);
      if (is_object($creditRes) && isset($creditRes->status) && $creditRes->status !== 'S') {
        return response()->json([
          'status' => 'failed',
          'message' => $creditRes->message ?? 'Wallet credit failed'
        ], 400);
      }
      return response()->json([
        'status' => 'ok',
        'message' => 'Virtual account credited successfully'
      ]);
    } elseif ($event === 'virtual_account.closed') {
      $updated = UserVirtualAccount::where('virtual_account_id', $virtual_account_id)
        ->where('customer_id', $customer_id)
        ->update([
          'status'    => 'B',
          'closed_at' => now(),
        ]);

      if (!$updated) {
        return response()->json([
          'status' => 'failed',
          'message' => 'Virtual account not found or already closed'
        ], 404);
      }
      return response()->json(['status' => 'ok']);
    }
    return response()->json(['status' => 'ok']);
  }

  private function creditUserWalletAmountReq($user, $refid, $amount, $charge_amount = 0, $gst_amount = 0)
  {
    $isInTransaction = DB::transactionLevel() > 0;
    if (!$isInTransaction) {
      DB::beginTransaction();
    }
    try {
      $user = User::where('id', $user->id)
        ->lockForUpdate()
        ->first();

      $wallet_balance = $user->virtual_balance;
      $total_charge = $charge_amount + $gst_amount;
      $total_amount = $amount + $total_charge;

      $opening_balance = $wallet_balance;
      $closing_balance = $opening_balance + $total_charge;

      DB::table('user_wallet_transactions')->insert([
        'user_id'         => $user->id,
        'service_name'    => 'VIRTUAL',
        'refid' => $refid,
        'opening_balance' => $opening_balance,
        'total_charge'    => $total_charge,
        'amount'          => $amount,
        'total_amount'    => $total_amount,
        'closing_balance' => $closing_balance,
        'credit'          => $total_amount,
        'debit'           => 0.00,
        'description'     => $user->uid . '/VIRTUAL/' . $refid,
        'created_at'      => now(),
        'updated_at'      => now(),
      ]);

      DB::table('users')
        ->where('id', $user->id)
        ->update([
          'virtual_balance' => $closing_balance,
        ]);

      if (!$isInTransaction) {
        DB::commit();
      }

      return [
        'status'          => true,
        'opening_balance' => $opening_balance,
        'closing_balance' => $closing_balance,
        'total_amount'    => $total_amount,
      ];
    } catch (\Exception $e) {
      if (!$isInTransaction) {
        DB::rollBack();
      }
      Log::error("Wallet credit failed: " . $e->getMessage());
      return ['status' => false, 'message' => 'Wallet credit failed: ' . $e->getMessage()];
    }
  }

  private function creditUserWalletAmount($user, $refid, $amount, $charge_amount, $gst_amount)
  {
    $isInTransaction = DB::transactionLevel() > 0;
    if (!$isInTransaction) {
      DB::beginTransaction();
    }

    try {
      $user = User::where('id', $user->id)
        ->lockForUpdate()
        ->first();

      $wallet_balance = $user->virtual_balance;
      $total_charge = $charge_amount + $gst_amount;
      $total_amount = $amount + $total_charge;

      $opening_balance = $wallet_balance;
      $closing_balance = $opening_balance + $total_amount;

      DB::table('user_wallet_transactions')->insert([
        'user_id'         => $user->id,
        'service_name'    => 'REFUND',
        'refid' => $refid,
        'opening_balance' => $opening_balance,
        'total_charge'    => $total_charge,
        'amount'          => $amount,
        'total_amount'    => $total_amount,
        'closing_balance' => $closing_balance,
        'credit'          => $total_amount,
        'debit'           => 0.00,
        'description'     => $user->uid . '/REFUND/' . $refid,
        'created_at'      => now(),
        'updated_at'      => now(),
      ]);

      DB::table('users')
        ->where('id', $user->id)
        ->update([
          'virtual_balance' => $closing_balance,
        ]);

      if (!$isInTransaction) {
        DB::commit();
      }

      return [
        'status'          => true,
        'opening_balance' => $opening_balance,
        'closing_balance' => $closing_balance,
        'total_amount'    => $total_amount,
      ];
    } catch (\Exception $e) {
      if (!$isInTransaction) {
        DB::rollBack();
      }
      Log::error("Wallet credit failed: " . $e->getMessage());
      return ['status' => false, 'message' => 'Wallet credit failed: ' . $e->getMessage()];
    }
  }
  protected function sendPayoutCallback(int $userId, array $payload): void
  {
    $callback_url = DB::table('user_services')
      ->where('user_id', $userId)
      ->value('payout_callback');

    if (!$callback_url) {
      return;
    }

    try {
      Http::withHeaders(['Content-Type' => 'application/json'])
        ->timeout(120)
        ->post($callback_url, [
          'http_code' => $payload['http_code'] ?? 200,
          'status'    => $payload['status'] ?? 'PENDING',
          'message'   => $payload['message'] ?? 'Transaction updated successfully.',
          'data'      => [
            'transaction_id' => $payload['transaction_id'] ?? null,
            'reference_id'   => $payload['reference_id'] ?? null,
            'utr'            => $payload['utr'] ?? null,
            'transfer_mode'  => $payload['transfer_mode'] ?? null,
            'amount'         => $payload['amount'] ?? null,
            'timestamp'      => now()->toDateTimeString(),
          ],
        ]);
    } catch (\Throwable $e) {
      Log::error("Payout callback failed for user {$userId}: " . $e->getMessage());
    }
  }

  protected function buildApiResponse(
    string $status,
    ?string $message,
    ?int $forcedCode,
    string $txnId,
    ?string $refId,
    ?string $bankReference,
    string $mode
  ) {
    $status = strtoupper(trim($status));

    $normalizedStatus = match ($status) {
      'S' => 'SUCCESS',
      'F', 'R' => 'FAILED',
      'P' => 'PENDING',
      default => 'PENDING',
    };

    $statusCode = $forcedCode ?? match ($normalizedStatus) {
      'SUCCESS' => 200,
      'FAILED'  => 400,
      'PENDING' => 202,
      default   => 202,
    };

    return response()->json([
      'status' => 'SUCCESS',
      'code'    => $statusCode,
      'message' => $message ?? match ($normalizedStatus) {
        'SUCCESS' => 'Transaction completed successfully.',
        'FAILED'  => 'Transaction failed. Please contact support if the issue persists.',
        'PENDING' => 'Transaction is currently being processed.',
        default   => 'Transaction is currently being processed.',
      },
      'data' => [
        'txn_status'  => $normalizedStatus,
        'transaction_id' => $txnId, // client-side reference
        'reference_id'   => $refId,          // server-side reference
        'bank_reference' => $bankReference,  // UTR / RRN from bank
        'transfer_mode'  => strtoupper($mode),
        'timestamp'      => now()->toIso8601String(),
      ],
    ], $statusCode);
  }
  private function writeLogs($service, $api, $url, $request_payload = null, $headers = null, $response_code = null, $response_data = null)
  {
    $log = [
      'service'       => $service,
      'api'           => $api,
      'url'           => $url,
      'ip'            => request()->ip(),
      'datetime'      => now()->toDateTimeString(),
      'headers'       => $headers,
      'payload'       => $request_payload,
      'http_code'     => $response_code,
      'response_data' => $response_data,
    ];

    Log::channel('daily')->info('Virtual Callback Log:', $log);
  }
}
