<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\PayoutTransaction;
use App\Models\UserDetail;
use App\Models\UserWalletTransaction;


class PaywizePayoutService
{
  private $baseUrl;
  private $tokenUrl;
  private $apiKey;
  private $secretKey;
  private $logPath;

  public function __construct()
  {
    $this->baseUrl     = env('PAYWIZE_BASE_URL') . 'connected-banking/public/payment/initiate';
    $this->tokenUrl  = env('PAYWIZE_BASE_URL') . 'auth/clients/token';
    $this->apiKey   = env('PAYWIZE_API_KEY');
    $this->secretKey   = env('PAYWIZE_SECERT_KEY');
    $this->logPath = storage_path('logs');
  }

  public function initiateTransaction($txn_id, $bene_account, $bene_ifsc, $amount, $bene_name)
  {
    $api_response = $this->doTransaction($txn_id, $bene_account, $bene_ifsc, $amount, $bene_name);
    if (($api_response['status'] ?? 'F') !== 'S') {
      return (object)[
        'status' => 'F',
        'message' => $api_response['message'] ?? '504 Bad Request Please Try After Sometime',
        'utr' => null,
        'api_txn_id' => null
      ];
    }
    $response = $api_response['data'] ?? [];
    $respCode = $response['resp_code'] ?? null;
    $dataencrypt = $response['data'] ?? null;
    if (!$dataencrypt) {
      return (object)[
        'status' => 'F',
        'message' => 'Invalid response: missing data',
        'utr' => null,
        'api_txn_id' => null
      ];
    }
    $DataJson = $this->decryptWebhookPayload($dataencrypt);
    $data = json_decode($DataJson);
    if ($respCode == 2000) {
      return (object)[
        'status' => 'P',
        'message' => $response['resp_message'] ?? 'Confirmation pending from partner bank',
        'utr' => null,
        'api_txn_id' => $data->transaction_id ?? null
      ];
    }
    if (in_array($respCode, [4023, 4001, 4000, 4008, 4022, 5000, 4002, 4003, 4004], true)) {
      return (object)[
        'status' => 'F',
        'message' => $response['resp_message'] ?? 'Transaction failed',
        'utr' => null,
        'api_txn_id' => $data->transaction_id ?? null
      ];
    }
    return (object)[
      'status' => 'P',
      'message' => 'Confirmation pending from partner bank',
      'utr' => null,
      'api_txn_id' => $data->transaction_id ?? null
    ];
  }



  private function doTransaction($txn_id, $bene_account, $bene_ifsc, $amount, $bene_name)
  {
    $amount = number_format((float)$amount, 2, '.', '');
    $api_url = $this->baseUrl;
    $encryptedPayload = $this->encryptPayload($txn_id, $amount, $bene_ifsc, $bene_account, $bene_name);
    $tokenResponse = $this->getAccessToken();
    $encryptedToken = $tokenResponse['data']['data'] ?? null;
    if (!$encryptedToken) {
      return ['status' => 'F', 'message' => 'Token fetch failed'];
    }
    $tokenDataJson = $this->decryptWebhookPayload($encryptedToken);
    $accessToken = $tokenDataJson;
    $headers = [
      'Content-Type' => 'application/json',
      'Accept' => 'application/json',
      'Authorization' => 'Bearer ' . $accessToken
    ];
    $payload = [
      'payload' => $encryptedPayload
    ];
    try {
      $response = Http::withHeaders($headers)
        ->timeout(120)
        ->post($api_url, $payload);
      $this->writeLogs('PAYWIZE_PAYOUT', 'payout', $api_url, $payload, $headers, $response->status(), $response->body());
      return [
        'status' => 'S',
        'data' => $response->json()
      ];
    } catch (\Exception $e) {
      $this->writeLogs('PAYWIZE_PAYOUT', 'payout', $api_url, $payload, $headers, 500, $e->getMessage());
      return [
        'status' => 'F',
        'message' => $e->getMessage()
      ];
    }
  }
  private function encryptPayload($txn_id, $amount, $bene_ifsc, $bene_account, $bene_name)
  {
    $cipherMethod = 'AES-256-CBC';
    $iv  = $this->secretKey;
    $key = $this->apiKey;
    $amount = (float) $amount;
    $data = [
      "amount" => $amount,
      "payment_mode" => "IMPS",
      "beneficiary_ifsc" => $bene_ifsc,
      "beneficiary_acc_number" => $bene_account,
      "beneficiary_name" => $bene_name,
      "remarks" => "payment",
      "sender_id" => $txn_id,
      "wallet_id" => 'PAYWIZE517452389',
      "channel" => 'SHIVALIK'
    ];

    $jsonData = json_encode($data);
    $this->writeLogs('PAYWIZE_PAYOUT', 'payout_request', null, $jsonData, null, 100, null);
    $encrypted = openssl_encrypt($jsonData, $cipherMethod, $key, OPENSSL_RAW_DATA, $iv);

    return base64_encode($encrypted);
  }
  private function getAccessToken()
  {
    $payload = [
      'api_key'    => $this->apiKey,
      'secret_key' => $this->secretKey
    ];
    $headers = [
      'Content-Type' => 'application/json',
      'Accept' => 'application/json'
    ];

    try {
      $response = Http::withHeaders($headers)->post($this->tokenUrl, $payload);
      return [
        'status' => 'S',
        'data' => $response->json()
      ];
    } catch (\Exception $e) {
      return [
        'status' => 'F',
        'message' => $e->getMessage()
      ];
    }
  }
  private function decryptWebhookPayload($encryptedPayload)
  {
    $cipherMethod = 'AES-256-CBC';
    $iv  = $this->secretKey;
    $key = $this->apiKey;

    $decodedData = base64_decode($encryptedPayload);

    $decrypted = openssl_decrypt($decodedData, $cipherMethod, $key, OPENSSL_RAW_DATA, $iv);

    return $decrypted;
  }
  public function handlePayoutCallback()
  {
    $url = request()->fullUrl();
    $headers = request()->headers->all();
    $request_payload = file_get_contents('php://input');
    $data = json_decode($request_payload, true);
    $encryptedPayload = $data['payload'];
    $decryptedJson = $this->decryptWebhookPayload($encryptedPayload);
    $decryptedData = json_decode($decryptedJson, true);
    $this->writeLogs('PAYWIZE_PAYOUT', 'Payout_Callback', $url, $request_payload, json_encode($headers), 200, json_encode($decryptedData));
    $txn_id = $decryptedData['sender_id'] ?? null;
    $status = $decryptedData['status'] ?? null;
    $utr    = $decryptedData['utr_number'] ?? null;
    $pay_id = $decryptedData['transaction_id'] ?? null;
    $txn_detail = PayoutTransaction::where('txn_id', $txn_id)->first();
    $message = 'Confirmation pending from partner bank';
    $response_status = 'Pending';
    $http_code = 201;
    if ($txn_detail) {
      if ($status === 'Success' && $txn_detail->status === 'P') {
        $message = 'Transaction successful';
        $response_status = 'Success';

        PayoutTransaction::where('txn_id', $txn_id)->update([
          'status'           => 'S',
          'api_txn_id'       => $pay_id,
          'utr'              => $utr,
          'response_message' => $message,
          'description'      => $message,
          'updated_at'       => now(),
          'updated_by'       => 'WEBHOOK',
        ]);
      } elseif ($status === 'Failed' && $txn_detail->status === 'P') {
        $message = 'Transaction failed';
        $response_status = 'Failed';
        DB::beginTransaction();
        try {
          $user = UserDetail::where('id', $txn_detail->user_id)->lockForUpdate()->first();
          $credit_status = $this->creditUserWalletAmount(
            $user,
            $txn_id,
            $txn_detail->amount,
            $txn_detail->charge_amount,
            $txn_detail->gst_amount
          );
          if ($credit_status === true) {
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
        PayoutTransaction::where('txn_id', $txn_id)->update([
          'status'           => 'P',
          'api_txn_id'       => $pay_id,
          'response_message' => $message,
          'description'      => $message,
          'updated_at'       => now(),
          'updated_by'       => 'Pending',
        ]);
      }

      // Prepare callback payload
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

    Log::channel('daily')->info('Paywize Payout Log:', $log);
  }
  private function creditUserWalletAmount($user, $refid, $amount, $charge_amount, $gst_amount)
  {
    $isInTransaction = DB::transactionLevel() > 0;
    if (!$isInTransaction) {
      DB::beginTransaction();
    }

    try {
      $user = UserDetail::where('id', $user->id)
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
}
