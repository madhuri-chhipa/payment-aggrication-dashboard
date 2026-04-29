<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\PayoutTransaction;
use App\Models\UserDetail;
use App\Models\UserWalletTransaction;

class FlipzikPayoutService
{
  private $baseUrl;
  private $payoutPath;
  private $accessKey;
  private $secretKey;
  private $logPath;

  public function __construct()
  {
    $this->baseUrl     = env('FLIKZIP_BASE_URL');
    $this->payoutPath  = env('FLIKZIP_PAYOUT_URL');
    $this->accessKey   = env('FLIKZIP_ACCESS_KEY');
    $this->secretKey   = env('FLIKZIP_SECRET_KEY');
    $this->logPath = storage_path('logs');
  }

public function initiateTransaction($txn_id, $bene_account, $bene_ifsc, $amount, $mobile, $email, $bene_name)
{
    $api_response = $this->doTransaction($txn_id, $bene_account, $bene_ifsc, $amount, $mobile, $email, $bene_name);
    if (isset($api_response['status']) && $api_response['status'] === 'S') {
        $responseData = $api_response['data']['data'] ?? [];
        $status      = $responseData['master_status'] ?? null;
        $utr         = $responseData['bank_reference_id'] ?? null;
        $api_txn_id  = $responseData['id'] ?? null;
        if ($status === 'Success') {
            $response_data = [
                'status' => 'S',
                'message' => 'Transaction successful',
                'utr' => $utr,
                'api_txn_id' => $api_txn_id,
            ];
        } elseif ($status === 'Failed') {
            $response_data = [
                'status' => 'F',
                'message' => 'Transaction failed',
                'utr' => $utr,
                'api_txn_id' => $api_txn_id,
            ];
        } elseif ($status === 'Pending') {
            $response_data = [
                'status' => 'P',
                'message' => 'Confirmation pending from partner bank',
                'utr' => $utr,
                'api_txn_id' => $api_txn_id,
            ];
        } else {
            $response_data = [
                'status' => 'P',
                'message' => 'Confirmation pending from partner bank',
                'utr' => $utr,
                'api_txn_id' => $api_txn_id,
            ];
        }
    }
    $response_data = [
        'status' => 'F',
        'message' => 'Payment is Failed',
        'utr' => null,
        'api_txn_id' => null,
    ];
     return (object) $response_data;
}


  private function doTransaction($txn_id, $bene_account, $bene_ifsc, $amount, $mobile, $email, $bene_name)
  {
    $amount = $amount * 100;
    $timestamp = floor(microtime(true) * 1000);
    $method = 'POST';
    $path = '/api/v1/payout/process';
    $query = '';
    $address = 'NOIDA SECTOR-12';
    $payload = [
      'merchant_order_id' => $txn_id,
      'account_number'    => $bene_account,
      'name'              => $bene_name,
      'ifsc_code'         => $bene_ifsc,
      'amount'            => $amount,
      'payment_type'      => '3',
      'mobile_number'     => $mobile,
      'email'             => $email,
      'address'           => $address,
    ];

    $signature = $this->calculateHmac($this->secretKey, $timestamp, $payload, $path, $query, $method);

    $headers = [
      'Content-Type'  => 'application/json',
      'signature'     => $signature,
      'X-Timestamp'   => $timestamp,
      'access_key'    => $this->accessKey,
    ];

    try {
      $response = Http::withHeaders($headers)
        ->timeout(120)
        ->post($this->baseUrl . $this->payoutPath, $payload);

      $this->writeLogs('FLIPZIK_PAYOUT', 'payout', $this->baseUrl . $this->payoutPath, $payload, $headers, $response->status(), $response->body());

      return [
        'status' => 'S',
        'data'   => $response->json(),
      ];
    } catch (\Exception $e) {
      $this->writeLogs('FLIPZIK_PAYOUT', 'payout', $this->baseUrl . $this->payoutPath, $payload, $headers, 500, $e->getMessage());
      return [
        'status' => 'F',
        'message' => $e->getMessage(),
      ];
    }
  }

  private function calculateHmac($secret, $timestamp, $body, $path, $query_string = '', $method = 'POST')
  {
    $body_string = json_encode($body);
    $message = $method . "\n" . $path . "\n" . $query_string . "\n" . $body_string . "\n" . $timestamp . "\n";
    return hash_hmac('sha512', $message, $secret);
  }
  public function handlePayoutCallback()
  {
    $url = request()->fullUrl();
    $headers = request()->headers->all();
    $request_payload = file_get_contents('php://input');
    $this->writeLogs('FLIPZIK_PAYOUT', 'Payout_Callback', $url, $request_payload, json_encode($headers));
    $data = json_decode($request_payload, true);
    $txn_id = $data['data']['object']['merchant_order_id'] ?? null;
    $status = $data['data']['object']['master_status'] ?? null;
    $utr = $data['data']['object']['bank_reference_id'] ?? null;
    $pay_id = $data['data']['object']['id'] ?? null;
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

    Log::channel('daily')->info('Flipzik Payout Log:', $log);
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
