<?php

namespace App\Services;

use App\Helpers\Helpers;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\PayoutTransaction;
use App\Models\User;
use App\Models\UserService;


class BuckboxPayoutService
{
  private $baseUrl;
  private $payinUrl;
  private $merchantId;
  private $apiKey;
  private $secretKey;
  private $enyKey;
  private $payoutUrl;
  private $checkstatusUrl;
  private $payinapiKey;
  private $payinsecretKey;
  private $payinenyKey;
  private $logPath;

  public function __construct()
  {
    //$this->baseUrl     = 'https://admin-staging.bustto.com/';
    $this->baseUrl     = 'https://admin.bustto.com/';
    $this->payoutUrl   = 'api/merchant/external/payout/';
    $this->payinUrl = 'https://payin.bustto.com/api/merchant/external/transaction/v1/payin/';
    $this->checkstatusUrl  = 'api/merchant/external/payout-status/';
    $this->merchantId  = '';
    $this->apiKey      = '';
    $this->secretKey   = '';
    $this->enyKey   = '';
    $this->payinapiKey      = '';
    $this->payinsecretKey   = '';
    $this->payinenyKey   = '';
    $this->logPath     = storage_path('logs');
  }
  public function initiateTransaction($txn_id, $bene_account, $bene_ifsc, $amount, $mobile, $email, $bene_name)
  {
    $api_response = $this->doTransaction($txn_id, $bene_account, $bene_ifsc, $amount, $mobile, $email, $bene_name);
    if (($api_response['status'] ?? null) === 'S') {
      $decrypted = $this->decryptPayload(
        $api_response['data']['response'],
        $this->enyKey
      );
      $this->writeLogs('BUCKBOX_PAYOUT', 'decrypted', null, $decrypted, null, null, null);
      $detail = $decrypted['bbErrorMsg'] ?? null;
      $data   = $decrypted['TransactionData']   ?? null;
      $code   = (int) ($decrypted['bbStatusCode'] ?? null);
      if (in_array($code, [3, 4, 5, 6], true)) {
        $error_msg = is_array($detail)
          ? ($detail['error'][0] ?? 'Transaction failed')
          : ($detail ?: 'Transaction failed');

        $response_data = [
          'status'     => 'F',
          'message'    => $error_msg,
          'utr'        => null,
          'api_txn_id' => null,
        ];
      } elseif ($code === 0) {
        $txn_status = strtoupper($data['bbTransactionStatus'] ?? '');
        if ($txn_status === 'SUCCESS') {
          $response_data = [
            'status'     => 'S',
            'message'    => 'Payment is Successful',
            'utr'        => $data['bbUtrNumber'] ?? null,
            'api_txn_id' => $data['bbTransactionId'] ?? null,
          ];
        } elseif (in_array($txn_status, ['PENDING', 'HOLD', 'INITIATED'], true)) {
          $response_data = [
            'status'     => 'P',
            'message'    => $data['bbReason'] ?? 'Confirmation pending from partner bank',
            'utr'        => null,
            'api_txn_id' => $data['bbTransactionId'] ?? null,
          ];
        } else {
          $response_data = [
            'status'     => 'P',
            'message'    => $data['bbReason'] ?? 'Confirmation pending from partner bank',
            'utr'        => null,
            'api_txn_id' => $data['bbTransactionId'] ?? null,
          ];
        }
      } else {
        $response_data = [
          'status'     => 'P',
          'message'    => $data['bbReason'] ?? 'Confirmation pending from partner bank',
          'utr'        => null,
          'api_txn_id' => $data['bbTransactionId'] ?? null,
        ];
      }
    } else {
      $response_data = [
        'status'     => 'F',
        'message'    => 'Payment failed',
        'utr'        => null,
        'api_txn_id' => null,
      ];
    }

    return (object) $response_data;
  }


  private function doTransaction($txn_id, $bene_account, $bene_ifsc, $amount, $mobile, $email, $bene_name)
  {
    $external_order_id = $txn_id;
    $payment_mode      = 'IMPS';
    $purpose           = 'Reward Transfer';
    $bank_name         = 'HDFC Bank';
    $branch_name       = 'MG Road Branch';
    $bene_address      = 'NOIDA SECTOR-12';

    $api_url  = $this->baseUrl . $this->payoutUrl;
    $jwt_token = $this->generateJWTToken();
    $amount    = intval($amount);

    $payload = [
      'external_order_id'    => $external_order_id,
      'amount'               => $amount,
      'payment_mode'         => $payment_mode,
      'bene_name'            => $bene_name,
      'bene_account_number'  => $bene_account,
      'bene_mobile'          => '+91' . $mobile,
      'bene_ifsc'            => $bene_ifsc,
      'purpose'              => $purpose,
      'bank_name'            => $bank_name,
      'branch_name'          => $branch_name,
      'bene_address'         => $bene_address,
    ];
    $headers = [
      'Api-Key'       => $this->apiKey,
      'Authorization' => 'Bearer ' . $jwt_token,
      'Content-Type'  => 'application/json',
    ];
    $this->writeLogs('BUCKBOX_PAYOUT_BODY', 'payout', $api_url, $payload, $headers, null, null);
    $encryptedRequest = $this->encryptPayload($payload, $this->enyKey);
    $body = [
      "request" => $encryptedRequest
    ];
    try {
      $response = Http::withHeaders($headers)
        ->asJson()
        ->post($api_url, $body);
      /** @var Response $response */
      $json = $response->json();
      $this->writeLogs('BUCKBOX_PAYOUT', 'payout', $api_url, $body, $headers, $response->status(), $response->body());
      return [
        'status' => 'S',
        'data'   => $json,
      ];
    } catch (\Exception $e) {
      $this->writeLogs('BUCKBOX_PAYOUT', 'payout', $api_url, $body, $headers, 500, $e->getMessage());

      return [
        'status'  => 'F',
        'message' => $e->getMessage(),
      ];
    }
  }


  private function generateJWTToken()
  {
    $secret_key = $this->secretKey;

    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    $expiration_time = time() + (7 * 24 * 60 * 60);

    $payload = json_encode([
      'merchant_id' => $this->merchantId,
      'name'        => '',
      'email'       => '',
      'exp'         => $expiration_time,
    ]);

    $base64UrlHeader  = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
    $base64UrlPayload = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');

    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret_key, true);
    $base64UrlSignature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
  }
  private function generateJWTTokenPayin()
  {
    $secret_key = $this->payinsecretKey;

    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    $expiration_time = time() + (7 * 24 * 60 * 60);

    $payload = json_encode([
      'merchant_id' => $this->merchantId,
      'name'        => 'Paydhara Recharge',
      'email'       => 'paydharainfo@gmail.com',
      'exp'         => $expiration_time,
    ]);

    $base64UrlHeader  = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
    $base64UrlPayload = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');

    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret_key, true);
    $base64UrlSignature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
  }
  public function getStatus(string $id): array
  {
    $jwt_token = $this->generateJWTToken();
    $headers = [
      'Api-Key'       => $this->apiKey,
      'Authorization' => 'Bearer ' . $jwt_token,
      'Content-Type'  => 'application/json',
    ];
    $api_url = $this->baseUrl . $this->checkstatusUrl . $id;

    try {
      $encryptedRequest = $this->encryptPayload([], $this->enyKey);

      $body = [
        "request" => $encryptedRequest
      ];
      $response = Http::withHeaders($headers)
        ->withBody(json_encode($body), 'application/json')
        ->get($api_url);
      /** @var Response $response */
      $json = $response->json();
      $decrypted = $this->decryptPayload(
        $json['response'],
        $this->enyKey
      );
      $this->writeLogs('BUCKBOX_PAYOUT', 'reward_status', $api_url, json_encode($body), json_encode($headers), $response->status(), $response->body());
      return [
        'status' => 'S',
        'data'   => $decrypted,
      ];
    } catch (\Exception $e) {
      $this->writeLogs('BUCKBOX_PAYOUT', 'reward_status', $api_url, [], json_encode($headers), 500, $e->getMessage());
      return [
        'status'  => 'F',
        'message' => $e->getMessage(),
      ];
    }
  }
  public function TransactionStatus($txn_id)
  {
    $api_response = $this->getStatus($txn_id);
    if (($api_response['status'] ?? null) === 'S') {
      $detail = $api_response['data']['bbErrorMsg'] ?? null;
      $data   = $api_response['data']['TransactionData']   ?? null;
      $code   = (int) ($api_response['data']['bbStatusCode'] ?? null);
      if (in_array($code, [3, 4, 5, 6], true)) {
        $error_msg = is_array($detail)
          ? ($detail['error'][0] ?? 'Transaction failed')
          : ($detail ?: 'Transaction failed');
        $response_data = [
          'status'     => 'F',
          'message'    => $error_msg,
          'utr'        => null,
        ];
      } elseif ($code === 0) {
        $txn_status = strtoupper($data['bbTransactionStatus'] ?? '');
        if ($txn_status === 'SUCCESSFUL') {
          $response_data = [
            'status'     => 'S',
            'message'    => 'Transaction successful',
            'utr'        => $data['bbUtrNumber'] ?? null,
          ];
        } elseif (in_array($txn_status, ['PENDING', 'HOLD', 'INITIATED'], true)) {
          $response_data = [
            'status'     => 'P',
            'message'    => $data['msg'] ?? 'Confirmation pending from partner bank',
            'utr'        => $data['bbUtrNumber'] ?? null,
          ];
        } elseif ($txn_status === 'FAILED') {
          $response_data = [
            'status'     => 'F',
            'message'    => 'Transaction failed',
            'utr'        => $data['bbUtrNumber'] ?? null,
          ];
        } else {
          $response_data = [
            'status'     => 'P',
            'message'    => $data['msg'] ?? 'Confirmation pending from partner bank',
            'utr'        => $data['bbUtrNumber'] ?? null,
          ];
        }
      } else {
        $response_data = [
          'status'     => 'P',
          'message'    => $data['msg'] ?? 'Confirmation pending from partner bank',
          'utr'        => $data['bbUtrNumber'] ?? null,
        ];
      }
    } else {
      $response_data = [
        'status'     => 'P',
        'message'    => $data['msg'] ?? 'Confirmation pending from partner bank',
        'utr'        => $data['bbUtrNumber'] ?? null,
      ];
    }

    return (object) $response_data;
  }
  public function handlePayoutCallback()
  {
    $url = request()->fullUrl();
    $headers = request()->headers->all();

    // Laravel way to read JSON body
    $data = request()->all();  // works for JSON if Content-Type: application/json

    $this->writeLogs('BUCKBOX_PAYOUT', 'Payout_Callback_ENY', $url, null, json_encode($headers), 200, $data);

    // get encrypted payload safely
    $secret = $this->secretKey;
    $enc = $data['encrypted_payload'] ?? null;

    if (empty($enc)) {
      $this->writeLogs('BUCKBOX_PAYOUT', 'Payout_Callback_ERROR', $url, 'encrypted_payload missing', json_encode($headers), 400, $data);
      return response()->json(['status' => false, 'message' => 'encrypted_payload missing'], 400);
    }

    try {
      $decoded = $this->jwt_decode_hs256($enc, $secret);
    } catch (\Throwable $e) {
      $this->writeLogs('BUCKBOX_PAYOUT', 'Payout_Callback_ERROR', $url, $e->getMessage(), json_encode($headers), 400, $data);
      return response()->json(['status' => false, 'message' => 'invalid token'], 400);
    }

    $this->writeLogs('BUCKBOX_PAYOUT', 'Payout_Callback', $url, $decoded, json_encode($headers), 200, $data);
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
  /**
   * Initiate PG Transaction
   */
  public function initiateTransactionPG($txn_id, $amount, $mobile, $email, $name)
  {
    $order = $this->createOrder($txn_id, $amount, $mobile, $email, $name);
    if (($order['status'] ?? 'F') !== 'S') {
      return [
        'status'      => 'F',
        'message'     => $order['message'] ?? 'Order creation failed',
        'payment_url' => null,
        'api_txn_id'  => null,
      ];
    }
    $decrypted = $this->decryptPayload(
      $order['data']['response'],
      $this->payinenyKey
    );
    $this->writeLogs('BUCKBOX_PAYIN_RESPONSE', 'decrypted', null, $decrypted, null, null, null);
    if (is_string($decrypted)) {
      $decrypted = json_decode($decrypted, true);
    }
    $bbStatusCode = $decrypted['bbStatusCode'] ?? 1;
    $transactionData = $decrypted['TransactionData'] ?? [];
    $transactionId   = $transactionData['id'] ?? null;
    $paymentUrl      = $transactionData['upi_link'] ?? null;
    if ((int)$bbStatusCode === 0) {
      return [
        'status'      => 'S',
        'message'     => 'Payment link generate successfully',
        'payment_url' => $paymentUrl,
        'api_txn_id'  => $transactionId,
      ];
    } else {
      return [
        'status'      => 'F',
        'message'     => 'Payment link not generate',
        'payment_url' => null,
        'api_txn_id'  => $transactionId,
      ];
    }
  }
  private function createOrder($txn_id, $amount, $mobile, $email, $name)
  {
    $api_url  = $this->payinUrl;
    $jwt_token = $this->generateJWTTokenPayin();
    $amount    = intval($amount);
    $payload = [
      "amount" => (int) $amount,
      "external_order_id" => $txn_id,
      "delivery_details" => [
        "recipient_phone_number" => '+91' . $mobile,
        "user_id" => "cust" . rand(100, 999),
        "recipient_email" => $email,
        "recipient_name" => $name
      ]
    ];
    $headers = [
      'Api-Key'       => $this->payinapiKey,
      'Authorization' => 'Bearer ' . $jwt_token,
      'Content-Type'  => 'application/json',
    ];
    $this->writeLogs('BUCKBOX_PAYIN_BODY', 'payin', $api_url, $payload, $headers, null, null);
    $encryptedRequest = $this->encryptPayload($payload, $this->payinenyKey);
    $body = [
      "request" => $encryptedRequest
    ];
    try {
      $response = Http::withHeaders($headers)
        ->connectTimeout(10)
        ->timeout(120)
        ->withOptions(['verify' => true])  // keep SSL verification ON
        ->post($api_url, $body);
      /** @var Response $response */
      $json = $response->json();
      $this->writeLogs('BUCKBOX_PAYIN', 'payin', $api_url, $body, $headers, $response->status(), $response->body());
      return [
        'status' => 'S',
        'data'   => $json,
      ];
    } catch (\Exception $e) {
      $this->writeLogs('BUCKBOX_PAYIN', 'payin', $api_url, $body, $headers, 500, $e->getMessage());

      return [
        'status'  => 'F',
        'message' => $e->getMessage(),
      ];
    }
  }
  public function handlePayinCallback()
  {
    $url = request()->fullUrl();
    $headers = request()->headers->all();
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?? [];
    $this->writeLogs('BUCKBOX_PAYIN', 'Payin_Callback', $url, $raw, json_encode($headers), 200, json_encode($data));
    $externalOrderId = $data['external_order_id'] ?? null;     // your txn_id ideally
    $busttoTxnId     = $data['transaction_id'] ?? null;        // bustto id
    $paymentStatus   = strtoupper($data['payment_status'] ?? '');
    $txnStatus       = strtoupper($data['transaction_status'] ?? '');
    $utr             = $data['utr_number'] ?? null;
    $amount          = isset($data['amount']) ? (float)$data['amount'] : null;
    $txn_id = $externalOrderId ?: $busttoTxnId;
    if (!$txn_id) {
      return response()->json(['status' => 'F', 'message' => 'Missing transaction reference'], 400);
    }
    $txn_detail = DB::table('payin_transactions')->where('txn_id', $txn_id)->first();
    if (!$txn_detail) {
      return response()->json(['status' => 'F', 'message' => 'No transaction found with this reference number.'], 404);
    }
    if ($amount !== null && (float)$txn_detail->amount != (float)$amount) {
      Log::warning("Amount mismatch for txn_id {$txn_id}: db={$txn_detail->amount}, cb={$amount}");
    }
    $isSuccess = ($paymentStatus === 'SUCCESS' && $txnStatus === 'SUCCESS');
    $isFailed  = ($paymentStatus === 'FAILED'  || $txnStatus === 'FAILED');
    if ($txn_detail->status !== 'P') {
      return response()->json(['status' => 'S', 'message' => 'Already processed.']);
    }
    if ($isSuccess) {
      $message = 'Transaction successful';
      $credit_status = $this->creditUserWalletAmountPG($txn_detail->user_id, $txn_id, $txn_detail->amount);
      if (($credit_status['status'] ?? false) == true) {
        DB::table('payin_transactions')->where('txn_id', $txn_id)->update([
          'status'           => 'S',
          'payment_status'   => 'S',
          'charge_amount'    => $credit_status['charge_amount'] ?? 0.00,
          'gst_amount'       => $credit_status['gst_amount'] ?? 0.00,
          'total_charge'     => $credit_status['total_charge'] ?? 0.00,
          'total_amount'     => $credit_status['total_amount'] ?? 0.00,
          'api_txn_id'       => $busttoTxnId,
          'utr'              => $utr,
          'response_message' => $message,
          'description'      => $message,
          'updated_at'       => now(),
          'updated_by'       => '0',
        ]);
      } else {
        DB::table('payin_transactions')->where('txn_id', $txn_id)->update([
          'status'           => 'P',
          'api_txn_id'       => $busttoTxnId,
          'utr'              => $utr,
          'response_message' => 'Wallet credit failed, retry needed',
          'description'      => 'Wallet credit failed, retry needed',
          'updated_at'       => now(),
          'updated_by'       => '0',
        ]);
        return response()->json(['status' => 'F', 'message' => 'Credit failed'], 500);
      }
    } elseif ($isFailed) {
      DB::table('payin_transactions')->where('txn_id', $txn_id)->update([
        'status'           => 'F',
        'payment_status'   => 'F',
        'api_txn_id'       => $busttoTxnId,
        'utr'              => $utr,
        'response_message' => 'Transaction failed',
        'description'      => 'Transaction failed',
        'updated_at'       => now(),
        'updated_by'       => '0',
      ]);
    } else {
      DB::table('payin_transactions')->where('txn_id', $txn_id)->update([
        'status'           => 'P',
        'api_txn_id'       => $busttoTxnId,
        'utr'              => $utr,
        'response_message' => "Waiting final success (payment_status={$paymentStatus}, transaction_status={$txnStatus})",
        'description'      => "Waiting final success",
        'updated_at'       => now(),
        'updated_by'       => '0',
      ]);
    }
    return response()->json(['status' => 'S', 'message' => 'Transaction updated.'], 200);
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

    Log::channel('daily')->info('Buckbox Payout Log:', $log);
  }
  private function creditUserWalletAmountPG($user_id, $refid, $amount)
  {
    $descriptionToFind = $user_id . '/PG/' . $refid;
    $transactionExists = DB::table('user_wallet_transactions')
      ->where('description', $descriptionToFind)
      ->exists();
    if ($transactionExists) {
      DB::commit();
      return [
        'status'  => true,
        'message' => 'Duplicate: Wallet has already been credited for this transaction.'
      ];
    }
    $user_service = UserService::where('user_id', $user_id)->first();
    $isInTransaction = DB::transactionLevel() > 0;
    if (!$isInTransaction) {
      DB::beginTransaction();
    }
    try {
      $user = User::where('id', $user_id)->lockForUpdate()->first();
      if ($user_service->payin_settlement == 'I') {
        $wallet_balance = $user->wallet_balance;
        $service_name = 'SETTLE_PAYIN';
      } else {
        $wallet_balance = $user->payin_balance;
        $service_name = 'PAYIN';
      }
      $charge_percentage = $user_service->payin_api_charges ?? 5.00;
      $charge_amount = ($amount * $charge_percentage) / 100;
      $gst_amount = ($charge_amount * 18) / 100;
      $total_charge = $charge_amount + $gst_amount;
      $total_amount = $amount - $total_charge;
      $opening_balance = $wallet_balance;
      $closing_balance = $opening_balance + $total_amount;
      DB::table('user_wallet_transactions')->insert([
        'user_id'         => $user_id,
        'service_name'    => $service_name,
        'opening_balance' => $opening_balance,
        'total_charge'    => $total_charge,
        'amount'          => $amount,
        'total_amount'    => $total_amount,
        'closing_balance' => $closing_balance,
        'credit'          => $total_amount,
        'debit'           => 0.00,
        'description'     => $user_id . '/PG/' . $refid,
        'is_settled'      => 0,
        'created_at'      => now(),
        'updated_at'      => now(),
      ]);
      if ($user_service->payin_settlement == 'I') {
        DB::table('users')
          ->where('id', $user_id)
          ->update([
            'wallet_balance' => $closing_balance,
          ]);
      } else {
        DB::table('users')
          ->where('id', $user_id)
          ->update([
            'payin_balance' => $closing_balance,
          ]);
      }


      if (!$isInTransaction) {
        DB::commit();
      }
      return [
        'status'          => true,
        'opening_balance' => $opening_balance,
        'closing_balance' => $closing_balance,
        'total_amount'    => $total_amount,
        'charge_amount'   => $charge_amount,
        'gst_amount'    => $gst_amount,
        'total_charge' => $total_charge
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

      $wallet_balance = $user->wallet_balance;
      $total_charge = $charge_amount + $gst_amount;
      $total_amount = $amount + $total_charge;

      $opening_balance = $wallet_balance;
      $closing_balance = $opening_balance + $total_amount;

      DB::table('user_wallet_transactions')->insert([
        'user_id'         => $user->id,
        'service_name'    => 'PAYOUT',
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
          'wallet_balance' => $closing_balance,
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
  private static function base64UrlDecode(string $input): string
  {
    $replaced = strtr($input, '-_', '+/');
    $padLen = 4 - (strlen($replaced) % 4);
    if ($padLen < 4) {
      $replaced .= str_repeat('=', $padLen);
    }
    $decoded = base64_decode($replaced, true);
    if ($decoded === false) {
      throw new \RuntimeException("Invalid base64 key provided.");
    }
    return $decoded;
  }

  public static function encryptPayload(array $data, string $aesKeyBase64): string
  {
    $aesKey = self::base64UrlDecode($aesKeyBase64);

    $plaintext = json_encode($data, JSON_UNESCAPED_SLASHES);
    if ($plaintext === false) {
      throw new \RuntimeException("Failed to JSON encode payload.");
    }

    // Python side uses 16 bytes nonce in your sample
    $nonce = random_bytes(16);

    $tag = '';
    $ciphertext = openssl_encrypt(
      $plaintext,
      'aes-256-gcm',
      $aesKey,
      OPENSSL_RAW_DATA,
      $nonce,
      $tag,
      '',
      16
    );

    if ($ciphertext === false || strlen($tag) !== 16) {
      throw new \RuntimeException("Encryption failed.");
    }

    // nonce + tag + ciphertext
    $blob = $nonce . $tag . $ciphertext;
    return base64_encode($blob);
  }

  public static function decryptPayload(string $encryptedB64, string $aesKeyBase64): array
  {
    $aesKey = self::base64UrlDecode($aesKeyBase64);

    $blob = base64_decode($encryptedB64, true);
    if ($blob === false || strlen($blob) < 32) {
      throw new \RuntimeException("Invalid encrypted payload.");
    }

    $nonce = substr($blob, 0, 16);
    $tag = substr($blob, 16, 16);
    $ciphertext = substr($blob, 32);

    $plaintext = openssl_decrypt(
      $ciphertext,
      'aes-256-gcm',
      $aesKey,
      OPENSSL_RAW_DATA,
      $nonce,
      $tag
    );

    if ($plaintext === false) {
      throw new \RuntimeException("Decryption failed (tag/key mismatch or corrupted data).");
    }

    $decoded = json_decode($plaintext, true);
    if (!is_array($decoded)) {
      throw new \RuntimeException("Decrypted response is not valid JSON.");
    }

    return $decoded;
  }
  private function jwt_decode_hs256(string $jwt, string $secret): array
  {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
      return ['error' => 'Invalid JWT format'];
    }
    [$headerB64, $payloadB64, $signatureB64] = $parts;
    $b64url_decode = function ($data) {
      $data = strtr($data, '-_', '+/');
      $pad = strlen($data) % 4;
      if ($pad) $data .= str_repeat('=', 4 - $pad);
      return base64_decode($data, true);
    };
    $headerJson  = $b64url_decode($headerB64);
    $payloadJson = $b64url_decode($payloadB64);
    $signature   = $b64url_decode($signatureB64);

    if ($headerJson === false || $payloadJson === false || $signature === false) {
      return ['error' => 'Invalid JWT base64'];
    }
    $header  = json_decode($headerJson, true);
    $payload = json_decode($payloadJson, true);

    if (!is_array($header) || !is_array($payload)) {
      return ['error' => 'Invalid JWT JSON'];
    }

    if (($header['alg'] ?? '') !== 'HS256') {
      return ['error' => 'Unsupported algorithm'];
    }
    $dataToSign  = $headerB64 . '.' . $payloadB64;
    $expectedSig = hash_hmac('sha256', $dataToSign, $secret, true);

    if (!hash_equals($expectedSig, $signature)) {
      return ['error' => 'Invalid token signature'];
    }
    $now = time();
    if (isset($payload['nbf']) && is_numeric($payload['nbf']) && $now < (int)$payload['nbf']) {
      return ['error' => 'Token not active yet (nbf)'];
    }
    if (isset($payload['exp']) && is_numeric($payload['exp']) && $now >= (int)$payload['exp']) {
      return ['error' => 'Token has expired'];
    }

    return $payload;
  }
}