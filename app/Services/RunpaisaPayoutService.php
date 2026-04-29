<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\PayoutTransaction;
use App\Models\UserDetail;


class RunpaisaPayoutService
{
  private string $clientId;
  private string $username;
  private string $password;

  private string $tokenUrl;
  private string $createOrderUrl;
  private string $orderStatusUrl;
  private  $logPath;
  private string $cacheKey = 'runpaisa_payout_token';

  public function __construct()
  {
    // Hardcoded creds & URLs
    $this->clientId       = '';
    $this->username       = '';
    $this->password       = '';

    $this->tokenUrl       = 'https://api.runpaisa.com/token';
    $this->createOrderUrl = 'https://api.payout.runpaisa.com/payment';
    $this->orderStatusUrl = 'https://api.payout.runpaisa.com/status';
    $this->logPath = storage_path('logs');
  }
  public function initiateTransaction(string $txn_id, string $bene_account, string $bene_ifsc, float $amount, string $bene_name)
  {
    [$ok, $tokenOrError] = $this->getToken();
    if (!$ok) {
      $response_data = [
        'status'     => 'F',
        'message'    => $tokenOrError ?: 'Failed to generate token. Please try again.',
        'utr'        => null,
        'api_txn_id' => null,
      ];
    }
    $token = $tokenOrError;
    $callback_url = url('/api/runpaisa/payout-callback');
    $order = $this->createPayoutOrder($token, $bene_account, $bene_ifsc, $amount, $bene_name, $txn_id, $callback_url);
    if (($order['status'] ?? 'F') !== 'S') {
      $response_data = [
        'status'     => 'P',
        'message'    => $order['message'] ?? 'Confirmation pending from partner bank',
        'utr'        => null,
        'api_txn_id' => null,
      ];
    }
    $res       = $order['data'] ?? [];
    $rpStatus  = strtoupper($res['status'] ?? '');
    $rpCode    = strtoupper($res['code']   ?? '');
    $rpMessage = $res['message'] ?? 'Confirmation pending from partner bank';

    if ($rpStatus === 'ACCEPTED' && $rpCode === 'RP000') {
      $response_data = [
        'status'     => 'P',
        'message'    => $rpMessage,
        'utr'        => null,
        'api_txn_id' => null,
      ];
    } else if ($rpStatus === 'FAIL' || $rpCode === 'RP016' || $rpCode === 'RP017') {
      $response_data = [
        'status'     => 'F',
        'message'    => $rpMessage,
        'utr'        => null,
        'api_txn_id' => null,
      ];
    } else {
      $response_data = [
        'status'     => 'P',
        'message'    => $rpMessage,
        'utr'        => null,
        'api_txn_id' => null,
      ];
    }
    return (object) $response_data;
  }
  private function getToken(): array
  {
    $cached = Cache::get($this->cacheKey);
    if (is_string($cached) && strlen($cached) > 10) {
      return [true, $cached];
    }
    $tokenRes = $this->createToken();
    if (($tokenRes['status'] ?? 'F') !== 'S') {
      return [false, $tokenRes['message'] ?? 'Token generation failed'];
    }
    $r = $tokenRes['data'] ?? [];
    $tStatus = strtoupper($r['status'] ?? '');
    $tCode   = strtoupper($r['code']   ?? '');
    if ($tStatus === 'SUCCESS' && $tCode === 'RP000') {
      $token  = $r['data']['token']  ?? null;
      $expiry = (int) ($r['data']['expiry'] ?? 0);
      if (!$token) {
        return [false, 'Failed to extract token from response'];
      }
      $ttl = max(60, $expiry - 60);
      Cache::put($this->cacheKey, $token, $ttl);

      return [true, $token];
    }

    return [false, $r['message'] ?? 'Token generation failed'];
  }
  private function createToken(): array
  {
    $url = $this->tokenUrl;

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
        ->withOptions([
          'version' => 1.1,
          'curl'    => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
          'headers' => ['Expect' => ''],
        ])
        ->post($url, []);

      $this->writeLogs(
        'RUNPAISA_PAYOUT',
        'CreateToken',
        $url,
        [],
        $headers,
        $response->status(),
        $response->body()
      );

      return [
        'status' => 'S',
        'data'   => $response->json(),
      ];
    } catch (\Throwable $e) {
      $this->writeLogs(
        'RUNPAISA_PAYOUT',
        'CreateToken',
        $url,
        [],
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
  public function getStatus(string $id): array
  {
    [$ok, $tokenOrError] = $this->getToken();
    if (!$ok) {
      return [
        'status'  => 'F',
        'data'    => null,
        'message' => $tokenOrError ?: 'Failed to generate token. Please try again.',
      ];
    }
    $token = $tokenOrError;
    $url = $this->orderStatusUrl;
    $headers = [
      'Content-Type' => 'application/json',
      'client_id'    => $this->clientId,
      'token'        => $token,
    ];
    $payload = [
      'order_id' => $id,
    ];
    try {
      $response = Http::withHeaders($headers)
        ->acceptJson()
        ->withOptions([
          'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
        ])
        ->post($url, $payload);
      $this->writeLogs('RUNPAISA_PAYOUT', 'payout_status', $url, $payload, json_encode($headers), $response->status(), $response->body());
      if ($response->successful()) {
        return [
          'status'  => 'S',
          'data'    => $response->json(),
          'message' => null,
        ];
      } else {
        return [
          'status'  => 'F',
          'data'    => null,
          'message' => "API Error: Received HTTP status " . $response->status(),
        ];
      }
    } catch (\Exception $e) {
      $this->writeLogs('RUNPAISA_PAYOUT', 'payout_status', $url, $payload, json_encode($headers), 500, $e->getMessage());
      return [
        'status'  => 'F',
        'data'    => null,
        'message' => $e->getMessage(),
      ];
    }
  }

  public function TransactionStatus($txn_id)
  {
    $api_response = $this->getStatus($txn_id);
    if (($api_response['status'] ?? null) === 'S' && isset($api_response['data']) && is_array($api_response['data'])) {
      $data = $api_response['data'];
      $code = $data['code'] ?? null;
      if ($code === 'RP000' && isset($data['ORDERSTATUS']) && is_array($data['ORDERSTATUS'])) {
        $orderStatus = $data['ORDERSTATUS'];
        $txn_status = strtoupper($orderStatus['STATUS'] ?? '');
        $utr = $orderStatus['UTR_NO'] ?? null;
        $description = $orderStatus['DESCRIPTION'] !== 'NA' ? $orderStatus['DESCRIPTION'] : 'Transaction processed.';
        if ($txn_status === 'SUCCESS') {
          $response_data = [
            'status'  => 'S',
            'message' => $description,
            'utr'     => $utr,
          ];
        } else {
          $response_data = [
            'status'  => 'P',
            'message' => $description,
            'utr'     => $utr,
          ];
        }
      } else {
        $response_data = [
          'status'  => 'P',
          'message' => $data['MESSAGE'] ?? 'Confirmation pending from the partner.',
          'utr'     => null,
        ];
      }
    } else {
      $response_data['message'] = $api_response['message'] ?? 'API connection failed or invalid response.';
    }
    return (object) $response_data;
  }
  private function createPayoutOrder($token, $bene_account, $bene_ifsc, $amount, $bene_name, $txn_id, $callback_url)
  {
    $url = $this->createOrderUrl;
    $headers = [
      'client_id' => $this->clientId,
      'token'     => $token,
    ];
    $original_payload = [
      'beneficiaryAccountNumber' => $bene_account,
      'beneficiaryIfscCode'      => $bene_ifsc,
      'amount'                   => round($amount, 2),
      'paymentMode'              => 'IMPS',
      'beneficiaryName'          => $bene_name,
      'orderId'                  => $txn_id,
      'callbackurl'              => $callback_url,
    ];
    $multipart_payload = [
      ['name' => 'beneficiaryAccountNumber', 'contents' => $original_payload['beneficiaryAccountNumber']],
      ['name' => 'beneficiaryIfscCode',      'contents' => $original_payload['beneficiaryIfscCode']],
      ['name' => 'amount',                   'contents' => $original_payload['amount']],
      ['name' => 'paymentMode',              'contents' => $original_payload['paymentMode']],
      ['name' => 'beneficiaryName',          'contents' => $original_payload['beneficiaryName']],
      ['name' => 'orderId',                  'contents' => $original_payload['orderId']],
      ['name' => 'callbackurl',              'contents' => $original_payload['callbackurl']],
    ];
    try {
      $response = Http::withHeaders($headers)
        ->timeout(60)
        ->connectTimeout(15)
        ->withOptions([
          'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
        ])
        ->asMultipart()
        ->post($url, $multipart_payload);

      $this->writeLogs(
        'RUNPAISA_PAYOUT',
        'Create_Payout',
        $url,
        $original_payload, // Log the simple payload
        $headers,
        $response->status(),
        $response->body()
      );

      return [
        'status' => 'S',
        'data'   => $response->json(),
      ];
    } catch (\Throwable $e) {
      $this->writeLogs(
        'RUNPAISA_PAYOUT',
        'Create_Payout_Exception',
        $url,
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


  public function handlePayoutCallback()
  {
    $url = request()->fullUrl();
    $headers = request()->headers->all();
    $request_payload = file_get_contents('php://input');
    $data = json_decode($request_payload, true);
    $this->writeLogs('RUNPAISA_PAYOUT', 'Payout_Callback', $url, $request_payload, json_encode($headers), 200, json_encode($data));
    $txn_id = $decryptedData['ORDERID'] ?? null;
    $status = $decryptedData['STATUS'] ?? null;
    $utr    = $decryptedData['UTRNO'] ?? null;
    $pay_id = $decryptedData['ORDERID'] ?? null;
    $txn_detail = PayoutTransaction::where('txn_id', $txn_id)->first();
    $message = 'Confirmation pending from partner bank';
    $response_status = 'Pending';
    $http_code = 201;
    if ($txn_detail) {
      if ($status === 'SUCCESS' && $txn_detail->status === 'P') {
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
      } elseif ($status === 'FAILED' && $txn_detail->status === 'P') {
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

    Log::channel('daily')->info('Runpaisa Payout Log:', $log);
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
