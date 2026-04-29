<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\PayoutTransaction;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class BluswapPayoutService
{
  private string $serviceName = 'BLUSWAP_PAYOUT';
  private string $apiKey;
  private string $createContactUrl;
  private string $payoutUrl;

  public function __construct()
  {
    $this->apiKey = (string) env('BLUESWAP_SKEY');
    $this->createContactUrl = (string) env('BLUESWAP_CUSTURL');
    $this->payoutUrl = (string) env('BLUESWAP_PAYOUTURL');
  }

  private function headers(): array
  {
    return [
      'x-api-key'    => $this->apiKey,
      'Accept'       => 'application/json',
      'Content-Type' => 'application/json',
    ];
  }

  /**
   * Create beneficiary/contact and return parsed result
   */
  private function createContact(
    string $beneAccount,
    string $ifscCode,
    string $beneficiaryName,
    ?string $email,
    ?string $mobile
  ): array {
    $payload = [
      'bank_account_number' => $beneAccount,
      'ifsc'                => strtoupper($ifscCode),
      'name'                => $beneficiaryName,
      'contact_number'      => $mobile,
      'email_id'            => $email,
      'account_type'        => 'Savings',
    ];

    try {
      $response = Http::withHeaders($this->headers())->post($this->createContactUrl, $payload);
      /** @var Response $response */
      $resp = $response->json();

      $this->writeLogs(
        'createContact',
        $this->createContactUrl,
        $payload,
        $response->status(),
        $response->body()
      );

      if (!is_array($resp)) {
        return [
          'status' => 'F',
          'message' => 'Invalid contact API response',
          'contact_id' => null,
          'raw' => null,
        ];
      }

      $partnerStatus = strtoupper((string) ($resp['status'] ?? ''));
      $partnerCode   = (string) ($resp['status_code'] ?? '');
      $partnerMsg    = (string) ($resp['message'] ?? '');
      $contactId     = $resp['data']['contact_id'] ?? null;

      if ($partnerStatus === 'SUCCESS' && in_array($partnerCode, ['200', '201'], true) && !empty($contactId)) {
        return [
          'status' => 'S',
          'message' => $partnerMsg ?: 'Contact created successfully',
          'contact_id' => $contactId,
          'raw' => $resp,
        ];
      }

      if ($partnerStatus === 'FAILED') {
        return [
          'status' => 'F',
          'message' => $partnerMsg ?: 'Contact creation failed',
          'contact_id' => null,
          'raw' => $resp,
        ];
      }

      return [
        'status' => 'P',
        'message' => $partnerMsg ?: 'Contact pending',
        'contact_id' => $contactId,
        'raw' => $resp,
      ];
    } catch (\Throwable $e) {
      $this->writeLogs(
        'createContact',
        $this->createContactUrl,
        $payload,
        500,
        $e->getMessage()
      );

      return [
        'status' => 'F',
        'message' => $e->getMessage(),
        'contact_id' => null,
        'raw' => null,
      ];
    }
  }

  /**
   * This matches your controller call exactly:
   * initiateTransaction($refid, $bene_account, $ifsc_code, $amount, $beneficiary_name, $email, $mobile)
   */
  public function initiateTransaction(
    string $refid,
    string $beneAccount,
    string $ifscCode,
    $amount,
    string $beneficiaryName,
    ?string $email = null,
    ?string $mobile = null
  ) {
    $amount = round((float) $amount, 2);
    $contactResp = $this->createContact(
      $beneAccount,
      $ifscCode,
      $beneficiaryName,
      $email,
      $mobile
    );
    if (($contactResp['status'] ?? 'F') !== 'S' || empty($contactResp['contact_id'])) {
      return response()->json([
        'status' => ($contactResp['status'] ?? 'F') === 'P' ? 'P' : 'F',
        'message' => $contactResp['message'] ?? 'Contact creation failed',
        'utr' => null,
        'api_txn_id' => $refid,
      ]);
    }

    $contactId = $contactResp['contact_id'];

    $payload = [
      'order_id'     => $refid,
      'contact_id'   => $contactId,
      'amount'       => number_format($amount, 2, '.', ''),
      'payment_mode' => 'IMPS',
      'description'  => 'Payout Transfer',
    ];

    try {
      $response = Http::withHeaders($this->headers())->post($this->payoutUrl, $payload);
      /** @var Response $response */
      $resp = $response->json();

      $this->writeLogs(
        'initiateTransaction',
        $this->payoutUrl,
        $payload,
        $response->status(),
        $response->body()
      );

      if (!is_array($resp)) {
        return response()->json([
          'status' => 'P',
          'message' => 'Invalid payout API response',
          'utr' => null,
          'api_txn_id' => $refid,
        ]);
      }

      $partnerStatus = strtoupper((string) ($resp['status'] ?? ''));
      $partnerCode   = (string) ($resp['status_code'] ?? '');
      $partnerMsg    = (string) ($resp['message'] ?? '');
      $data          = $resp['data'] ?? [];

      $utr = $data['ref_id'] ?? null;
      $apiTxnId = $data['bluswap_transaction_id'] ?? null;
      $orderIdOrRef = $data['order_id'] ?? $refid;

      if ($partnerStatus === 'SUCCESS' && $partnerCode === '200') {
        return response()->json([
          'status' => 'S',
          'message' => $partnerMsg ?: 'Success',
          'utr' => $utr,
          'api_txn_id' => $apiTxnId ?: $orderIdOrRef,
        ]);
      }

      if ($partnerStatus === 'FAILED') {
        return response()->json([
          'status' => 'F',
          'message' => $partnerMsg ?: 'Failed',
          'utr' => $utr,
          'api_txn_id' => $apiTxnId ?: $orderIdOrRef,
        ]);
      }

      return response()->json([
        'status' => 'P',
        'message' => $partnerMsg ?: 'Pending',
        'utr' => $utr,
        'api_txn_id' => $apiTxnId ?: $orderIdOrRef,
      ]);
    } catch (\Throwable $e) {
      $this->writeLogs(
        'initiateTransaction',
        $this->payoutUrl,
        $payload,
        500,
        $e->getMessage()
      );

      return response()->json([
        'status' => 'F',
        'message' => $e->getMessage(),
        'utr' => null,
        'api_txn_id' => $refid,
      ]);
    }
  }
  public function handlePayoutCallback()
  {
    $url = request()->fullUrl();
    $headers = request()->headers->all();
    $rawBody = request()->getContent();
    $data = json_decode($rawBody, true);
    $this->writeLogs(
      'Payout_Callback',
      $url,
      $rawBody,
      200,
      $data
    );
    die();
    $txn_id = $decoded['external_order_id'] ?? null;
    $status = $decoded['payment_status'] ?? null;
    $utr    = $decoded['utr_number'] ?? null;
    $pay_id = $decoded['id'] ?? null;
    $txn_detail = PayoutTransaction::where('txn_id', $txn_id)->first();
    $message = 'Confirmation pending from partner bank';
    $response_status = 'Pending';
    $http_code = 201;
    if ($txn_detail) {
      if ($status === 'SUCCESSFUL' && $txn_detail->status === 'P') {
        $message = 'Transaction successful';
        $response_status = 'Success';
        $http_code = 200;
        PayoutTransaction::where('txn_id', $txn_id)->update([
          'status'           => 'S',
          'api_txn_id'       => $pay_id,
          'utr'              => $utr,
          'response_message' => $message,
          'description'      => $message,
          'updated_at'       => now(),
          'updated_by'       => 'WEBHOOK',
        ]);
      } elseif ($status === 'FAILED' && $txn_detail->status === 'P') {
        $message = 'Transaction failed';
        $response_status = 'Failed';
        $http_code = 400;
        DB::beginTransaction();
        try {
          $user = User::where('id', $txn_detail->user_id)->lockForUpdate()->first();
          $credit_status = $this->creditUserWalletAmount(
            $user,
            $txn_id,
            $txn_detail->amount,
            $txn_detail->charge_amount,
            $txn_detail->gst_amount
          );
          if ($credit_status == true) {
            PayoutTransaction::where('txn_id', $txn_id)->update([
              'status'           => 'R',
              'api_txn_id'       => $pay_id,
              'utr'              => $utr,
              'response_message' => 'Transaction failed',
              'description'      => 'Transaction failed',
              'updated_at'       => now(),
              'updated_by'       => 'WEBHOOK',
            ]);
          }
          DB::commit();
        } catch (\Exception $e) {
          DB::rollBack();
          Log::error("Transaction rollback for txn_id {$txn_id}: " . $e->getMessage());
        }
      } else {
        return response()->json([
          'status' => 'S',
          'message' => 'Transaction updated.',
        ]);
      }
      $callbackPayload = [
        'http_code'     => $http_code,
        'status'        => $response_status,
        'message'       => 'Transaction updated successfully.',
        'data'          => [
          'transaction_id' => $txn_id,
          'reference_id'   => $pay_id,
          'utr'            => $utr,
          'transfer_mode'  => 'IMPS',
          'amount'         => $txn_detail->amount,
          'timestamp'      => now()->toDateTimeString(),
        ],
      ];

      // Send callback if URL exists
      $callback_url = DB::table('user_services')
        ->where('user_id', $txn_detail->user_id)
        ->value('payout_callback');

      if ($callback_url) {
        try {
          Http::withHeaders(['Content-Type' => 'application/json'])
            ->timeout(120)
            ->post($callback_url, $callbackPayload);
        } catch (\Exception $e) {
          Log::error("UTR Callback Failed: " . $e->getMessage());
        }
      }

      return response()->json([
        'status' => 'S',
        'message' => 'Transaction updated.',
      ]);
    }

    // No transaction found
    return response()->json([
      'status'  => 'F',
      'message' => 'No transaction found with this reference number.',
    ]);
  }
  private function writeLogs(
    string $api,
    string $url,
    $requestPayload = null,
    $responseCode = null,
    $responseData = null
  ): void {
    $log = [
      'service'       => $this->serviceName,
      'api'           => $api,
      'url'           => $url,
      'ip'            => request()->ip(),
      'datetime'      => now()->toDateTimeString(),
      'payload'       => $requestPayload,
      'http_code'     => $responseCode,
      'response_data' => $responseData,
    ];
    Log::channel('bluswap')->info('Bluswap Payout Log:', $log);
  }
}