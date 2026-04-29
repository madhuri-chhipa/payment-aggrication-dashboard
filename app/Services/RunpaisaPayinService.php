<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\PayinTransaction;
use App\Models\UserDetail;
use App\Models\UserService;


class RunpaisaPayinService
{
  private string $clientId;
  private string $username;
  private string $password;

  private string $tokenUrl;
  private string $createOrderUrl;
  private string $orderStatusUrl;
  private  $logPath;

  public function __construct()
  {
    // Hardcoded creds & URLs
    $this->clientId       = '';
    $this->username       = '';
    $this->password       = '';

    $this->tokenUrl       = 'https://api.runpaisa.com/token';
    $this->createOrderUrl = 'https://api.pg.runpaisa.com/order';
    $this->orderStatusUrl = 'https://api.pg.runpaisa.com/status';
    $this->logPath = storage_path('logs');
  }

  public function createToken(): array
  {
    $headers = [
      'Content-Type' => 'application/json',
      'client_id'    => $this->clientId,
      'username'     => $this->username,
      'password'     => $this->password,
    ];

    try {
      $response = Http::withHeaders($headers)
        ->acceptJson()
        ->timeout(60)
        ->connectTimeout(15)
        ->retry(3, 2000)
        ->withOptions([
          'curl' => [
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
          ],
        ])
        ->post($this->tokenUrl, []);
      $this->writeLogs(
        'RUNPAISA_PG',
        'CreateToken',
        $this->tokenUrl,
        [],
        json_encode($headers),
        $response->status(),
        $response->body()
      );

      return [
        'status' => 'S',
        'data'   => $response->json(),
      ];
    } catch (\Exception $e) {
      $this->writeLogs(
        'RUNPAISA_PG',
        'CreateToken',
        $this->tokenUrl,
        [],
        json_encode($headers),
        500,
        $e->getMessage()
      );

      return [
        'status'  => 'F',
        'message' => $e->getMessage(),
      ];
    }
  }

  /**
   * Create Order
   */
  public function createOrder($token, $callback_url, $txn_id, $amount)
  {
    $headers = [
      'client_id' => $this->clientId,
      'token'     => $token,
    ];
    $original_payload = [
      'callbackurl'  => $callback_url,
      'order_id'     => $txn_id,
      'amount'       => round($amount, 2),
      'merc_unq_ref' => 'payment' . $txn_id
    ];
    $multipart_payload = [
      ['name' => 'callbackurl',  'contents' => $original_payload['callbackurl']],
      ['name' => 'order_id',     'contents' => $original_payload['order_id']],
      ['name' => 'amount',       'contents' => $original_payload['amount']],
      ['name' => 'merc_unq_ref', 'contents' => $original_payload['merc_unq_ref']],
    ];
    try {
      $response = Http::withHeaders($headers)
        ->timeout(120)
        ->withOptions(['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]])
        ->asMultipart()
        ->post($this->createOrderUrl, $multipart_payload);
      $this->writeLogs(
        'RUNPAISA_PG',
        'CreateOrder',
        $this->createOrderUrl,
        $original_payload,
        $headers,
        $response->status(),
        $response->body()
      );
      return [
        'status' => 'S',
        'data'   => $response->json(),
      ];
    } catch (\Exception $e) {
      $this->writeLogs(
        'RUNPAISA_PG',
        'CreateOrder - Exception',
        $this->createOrderUrl,
        $original_payload,
        $headers,
        500,
        $e->getMessage()
      );

      return [
        'status'  => 'F',
        'message' => $e->getMessage(),
      ];
    }
  }

  /**
   * Initiate PG Transaction
   */
  public function initiateTransactionPG($txn_id, $amount, $mobile, $email, $name)
  {
    $tok = $this->createToken();
    if (($tok['status'] ?? 'F') !== 'S') {
      return [
        'status'      => 'F',
        'message'     => $tok['message'] ?? 'Token generation failed',
        'payment_url' => null,
        'api_txn_id'  => null,
      ];
    }
    $tokenData = $tok['data'] ?? [];
    $token = $tokenData['data']['token'] ?? null;
    if (!$token) {
      return [
        'status'      => 'F',
        'message'     => $tokenData['message'] ?? 'Token missing',
        'payment_url' => null,
        'api_txn_id'  => null,
      ];
    }
    $callback_url = url('/api/runpaisa/pg-callback');
    $order = $this->createOrder($token, $callback_url, $txn_id, $amount);
    if (($order['status'] ?? 'F') !== 'S') {
      return [
        'status'      => 'F',
        'message'     => $order['message'] ?? 'Order creation failed in our system',
        'payment_url' => null,
        'api_txn_id'  => null,
      ];
    }
    $res = $order['data'] ?? [];
    $apiStatus     = strtoupper($res['status'] ?? '');
    $transactionId = $res['order_id'] ?? $txn_id;
    $paymentUrl    = $res['paymentLink'] ?? null;
    if ($apiStatus === 'SUCCESS' && !empty($paymentUrl)) {
      return [
        'status'      => 'S',
        'message'     => $res['message'] ?? 'Payment link generated successfully.',
        'payment_url' => $paymentUrl,
        'api_txn_id'  => $transactionId,
      ];
    } else {
      return [
        'status'      => 'F',
        'message'     => $res['message'] ?? 'Order rejected by payment gateway',
        'payment_url' => null,
        'api_txn_id'  => $transactionId,
      ];
    }
  }
  public function checkStatus(string $order_id)
  {
    $tok = $this->createToken();
    if (($tok['status'] ?? 'F') !== 'S') {
      return [
        'status'  => 'F',
        'message' => $tok['message'] ?? 'Token generation failed',
        'data'    => null,
      ];
    }
    $tokenData = $tok['data'] ?? [];
    $token = $tokenData['data']['token'] ?? null;
    if (!$token) {
      return [
        'status'  => 'F',
        'message' => $tokenData['message'] ?? 'Token missing',
        'data'    => null,
      ];
    }
    $url = $this->orderStatusUrl;
    $payload = [
      'order_id' => $order_id,
    ];
    $headers = [
      'Content-Type' => 'application/json',
      'client_id' => $this->clientId,
      'token'     => $token,
    ];
    try {
      $response = Http::withHeaders($headers)
        ->post($url, $payload);
      $bodyArr = $response->json() ?? [];
      $this->writeLogs(
        'RUNPAISA_PG',
        'CheckStaus',
        $url,
        $payload,
        json_encode($headers),
        $response->status(),
        json_encode($bodyArr)
      );
      $topStatus = strtoupper($bodyArr['STATUS'] ?? $bodyArr['status'] ?? '');
      $topCode   = strtoupper($bodyArr['CODE']   ?? $bodyArr['code']   ?? '');
      $topMsg    =              $bodyArr['MESSAGE'] ?? $bodyArr['message'] ?? '';
      $orderStatus = $bodyArr['ORDERSTATUS'] ?? null;
      if (is_object($orderStatus)) {
        $orderStatus = json_decode(json_encode($orderStatus), true);
      }
      if ($topStatus === 'SUCCESS' && $topCode === 'RP000' && is_array($orderStatus)) {
        $subStatus = strtoupper($orderStatus['STATUS'] ?? '');
        if ($subStatus === 'SUCCESS') {
          return [
            'status'  => 'S',
            'message' => $topMsg ?: ($orderStatus['ERROR_DESC'] ?? 'Payment is successful'),
            'data'    => $orderStatus,
          ];
        }
        if (in_array($subStatus, ['PENDING', 'HOLD', 'PROCESSING', 'IN_PROGRESS'], true)) {
          return [
            'status'  => 'P',
            'message' => $topMsg ?: ($orderStatus['ERROR_DESC'] ?? 'Confirmation pending from partner bank'),
            'data'    => $orderStatus,
          ];
        }
        return [
          'status'  => 'F',
          'message' => $topMsg ?: ($orderStatus['ERROR_DESC'] ?? 'Transaction failed'),
          'data'    => $orderStatus,
        ];
      }
      if ($topStatus === 'FAIL' || in_array($topCode, ['RP001', 'RP002', 'RP003', 'RP004', 'RP005', 'RP006', 'RP007', 'RP008', 'RP009'], true)) {
        return [
          'status'  => 'F',
          'message' => $topMsg ?: 'Transaction failed',
          'data'    => $orderStatus ?? null,
        ];
      }
      return [
        'status'  => 'P',
        'message' => $topMsg ?: 'Confirmation pending from partner bank',
        'data'    => $orderStatus ?? null,
      ];
    } catch (\Exception $e) {
      $this->writeLogs(
        'RUNPAISA_PG',
        'CheckStaus',
        $url,
        $payload,
        json_encode($headers),
        500,
        $e->getMessage()
      );

      return [
        'status'  => 'F',
        'message' => $e->getMessage(),
        'data'    => null,
      ];
    }
  }

  public function handlePayinCallback()
  {
    $url = request()->fullUrl();
    $headers = request()->headers->all();
    $request_payload = file_get_contents('php://input');
    $data = json_decode($request_payload, true);
    $this->writeLogs('RUNPAISA_PG', 'Payin_Callback', $url, $request_payload, json_encode($headers), 200, json_encode($data));
    $txn_id = $data['ORDER_ID'] ?? null;
    $status = $data['STATUS'] ?? null;
    $utr    = $data['BANK_TXNID'] ?? null;
    $pay_id = $data['ORDER_ID'] ?? null;
    $txn_detail = DB::table('payin_transactions')->where('txn_id', $txn_id)->first();
    $message = 'Confirmation pending from User';
    $response_status = 'Pending';
    $http_code = 201;
    if ($txn_detail) {
      if ($status === 'SUCCESS' && $txn_detail->status === 'P') {
        $message = 'Transaction successful';
        $response_status = 'Success';
        $http_code = 200;
        $credit_status = $this->creditUserWalletAmount($txn_detail->user_id, $txn_id, $txn_detail->amount);
         if (($credit_status['status'] ?? false) === true) {
            DB::table('payin_transactions')->where('txn_id', $txn_id)->update([
            'status'           => 'S',
            'payment_status'  =>'S',
            'charge_amount'=>$credit_status['charge_amount'] ?? 0.00,
            'gst_amount'=>$credit_status['gst_amount'] ?? 0.00,
            'total_charge'=>$credit_status['total_charge'] ?? 0.00,
            'total_amount'=>$credit_status['total_amount'] ?? 0.00,
            'api_txn_id'       => $pay_id,
            'utr'              => $utr,
            'response_message' => $message,
            'description'      => $message,
            'updated_at'       => now(),
            'updated_by'       => '0',
          ]);
        }
      } elseif ($status === 'FAILED' && $txn_detail->status === 'P') {
        $message = 'Transaction failed';
        $response_status = 'Failed';
        $http_code = 400;
        DB::table('payin_transactions')->where('txn_id', $txn_id)->update([
          'status'           => 'F',
          'payment_status'  =>'F',
          'api_txn_id'       => $pay_id,
          'utr'              => $utr,
          'response_message' => 'Transaction failed',
          'description'      => 'Transaction failed',
          'updated_at'       => now(),
          'updated_by'       => '0',
        ]);
      } else {
        DB::table('payin_transactions')->where('txn_id', $txn_id)->update([
          'status'           => 'P',
          'api_txn_id'       => $pay_id,
          'response_message' => $message,
          'description'      => $message,
          'updated_at'       => now(),
          'updated_by'       => '0',
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
          'transfer_mode'  => 'PG',
          'amount'         => $txn_detail->amount,
          'timestamp'      => now()->toDateTimeString(),
        ],
      ];

      // Send callback if URL exists
      $callback_url = DB::table('user_services')
        ->where('user_id', $txn_detail->user_id)
        ->value('payin_callback');

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

    Log::channel('daily')->info('Runpaisa PG Log:', $log);
  }
  private function creditUserWalletAmount($user_id, $refid, $amount)
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
      $user = UserDetail::where('id', $user_id)->lockForUpdate()->first();
      $wallet_balance = $user->payin_balance;
      $charge_percentage = $user_service->payin_api_charges ?? 5.00;
      $charge_amount = ($amount * $charge_percentage) / 100;
      $gst_amount = ($charge_amount * 18) / 100;
      $total_charge = $charge_amount + $gst_amount;
      $total_amount = $amount - $total_charge;
      $opening_balance = $wallet_balance;
      $closing_balance = $opening_balance + $total_amount;
      DB::table('user_wallet_transactions')->insert([
        'user_id'         => $user_id,
        'service_name'    => 'PAYIN',
        'opening_balance' => $opening_balance,
        'total_charge'    => $total_charge,
        'amount'          => $amount,
        'total_amount'    => $total_amount,
        'closing_balance' => $closing_balance,
        'credit'          => $total_amount,
        'debit'           => 0.00,
        'description'     => $user_id . '/PG/' . $refid,
        'created_at'      => now(),
        'updated_at'      => now(),
      ]);
      DB::table('users')
        ->where('id', $user_id)
        ->update([
          'payin_balance' => $closing_balance,
        ]);

      if (!$isInTransaction) {
        DB::commit();
      }
      return [
        'status'          => true,
        'opening_balance' => $opening_balance,
        'closing_balance' => $closing_balance,
        'total_amount'    => $total_amount,
        'charge_amount'   =>$charge_amount,
        'gst_amount'    =>$gst_amount,
        'total_charge'=>$total_charge
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
