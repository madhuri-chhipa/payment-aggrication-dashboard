<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\UserService;


class FinkedaPayinService
{
  private string $serviceName = 'finkeda';

  private string $baseUrl;
  private int $timeout;
  private string $logChannel;

  private string $clientId;
  private string $agentId;
  private string $counterId;

  private string $version;
  private string $appName;
  private string $scope;
  private string $defaultRequestSource;

  private string $tokenUrl;
  private string $gatewayTypeUrl;
  private string $checkoutUrl;
  private string $statusUrl;
  private string $balanceUrl;

  public function __construct()
  {
    $this->baseUrl = 'https://apigateway.finkeda.com';
    $this->timeout = (int) (config('services.finkeda_payin.timeout') ?? 120);
    $this->logChannel = (string) (config('services.finkeda_payin.log_channel') ?? 'daily');

    $this->clientId = (string) config('services.finkeda_payin.client_id');
    $this->agentId = (string) config('services.finkeda_payin.agent_id');
    $this->counterId = (string) config('services.finkeda_payin.counter_id');

    $this->version = (string) (config('services.finkeda_payin.version') ?? '1.0');
    $this->appName = (string) (config('services.finkeda_payin.app_name') ?? 'TERMINAL');
    $this->scope = (string) (config('services.finkeda_payin.scope') ?? 'TERMINAL LOAD');
    $this->defaultRequestSource = (string) (config('services.finkeda_payin.request_source') ?? 'WEB');

    $this->tokenUrl       = $this->baseUrl . '/api/session/terminal/token';
    $this->gatewayTypeUrl = $this->baseUrl . '/api/terminal/gateway/init';
    $this->checkoutUrl    = $this->baseUrl . '/api/terminal/checkout/create';
    $this->statusUrl      = $this->baseUrl . '/api/terminal/checkout/checkOrderStatus';
    $this->balanceUrl     = $this->baseUrl . '/api/terminal/checkBalance';
  }

  public function generateToken(string $urn, ?string $reqLong = null, ?string $reqLat = null, ?string $requestSource = null): ?string
  {
    $cacheKey = 'finkeda_payin_token_' . md5($this->agentId . '|' . $this->counterId);
    return Cache::remember($cacheKey, now()->addMinutes(29), function () use ($urn, $reqLong, $reqLat, $requestSource) {
      $headers = $this->commonHeaders($urn, $reqLong, $reqLat, $requestSource);
      $response = Http::withHeaders($headers)
        ->timeout($this->timeout)
        ->get($this->tokenUrl);
      /** @var Response $response */
      $this->writeLogs(
        $this->serviceName,
        'token fetch',
        $this->tokenUrl,
        [],
        $headers,
        $response->status(),
        $response->body()
      );
      $resp = $response->json();
      if (!is_array($resp)) {
        return null;
      }

      $token = data_get($resp, 'apiResponseData.responseData.token');

      return is_string($token) && $token !== '' ? $token : null;
    });
  }

  public function getGatewayType(string $urn, ?string $reqLong = null, ?string $reqLat = null, ?string $requestSource = null): array
  {
    try {
      $token = $this->generateToken($urn, $reqLong, $reqLat, $requestSource);
      if (!$token) {
        return ['status' => 'F', 'message' => 'Token generation failed'];
      }

      $headers = $this->authorizedHeaders($urn, $token, $reqLong, $reqLat, $requestSource);

      $response = Http::withHeaders($headers)
        ->timeout($this->timeout)
        ->get($this->gatewayTypeUrl);

      /** @var Response $response */
      $this->writeLogs(
        $this->serviceName,
        'getGatewayType',
        $this->gatewayTypeUrl,
        [],
        $headers,
        $response->status(),
        $response->body()
      );
      $resp = $response->json();
      if (!is_array($resp)) {
        return ['status' => 'F', 'message' => 'Invalid partner response'];
      }

      $apiResponseCode = (string) ($resp['apiResponseCode'] ?? '');
      $responseCode = (string) data_get($resp, 'apiResponseData.responseCode', '');
      $responseMessage = (string) data_get($resp, 'apiResponseData.responseMessage', 'Failed');
      $responseData = data_get($resp, 'apiResponseData.responseData', []);

      if ($apiResponseCode !== '200' || $responseCode !== '200' || !is_array($responseData)) {
        return [
          'status' => 'F',
          'message' => $responseMessage,
          'response' => $resp,
        ];
      }

      return [
        'status' => 'S',
        'message' => $responseMessage,
        'gateway_types' => $responseData,
        'response' => $resp,
      ];
    } catch (\Throwable $e) {
      $this->writeLogs(
        $this->serviceName,
        'getGatewayType',
        $this->gatewayTypeUrl,
        [],
        $this->maskHeadersForLog($this->commonHeaders($urn, $reqLong, $reqLat, $requestSource)),
        500,
        $e->getMessage()
      );

      return ['status' => 'F', 'message' => $e->getMessage()];
    }
  }

  public function initiateTransactionPG(
    string $urn,
    int $amount,
    string $cardType,
    string $cardNetwork,
    ?string $reqLong = null,
    ?string $reqLat = null,
    ?string $requestSource = null
  ): array {
    try {
      $token = $this->generateToken($urn, $reqLong, $reqLat, $requestSource);
      if (!$token) {
        return ['status' => 'F', 'message' => 'Token generation failed'];
      }
      $gatewayResp = $this->getGatewayType($urn, $reqLong, $reqLat, $requestSource);
      if (($gatewayResp['status'] ?? 'F') !== 'S') {
        return [
          'status' => 'F',
          'message' => $gatewayResp['message'] ?? 'Unable to fetch gateway type',
        ];
      }

      $pgType = $gatewayResp['gateway_types'][0] ?? null;

      if (!$pgType) {
        return [
          'status' => 'F',
          'message' => 'No gateway type available',
        ];
      }

      $headers = $this->authorizedHeaders($urn, $token, $reqLong, $reqLat, $requestSource);

      $payload = [
        'amount' => $amount,
        'settledIn' => 'wallet',
        'cardType' => strtoupper(trim($cardType)),
        'cardNetwork' => strtoupper(trim($cardNetwork)),
        'pgType' => $pgType,
      ];

      $response = Http::withHeaders($headers)
        ->timeout($this->timeout)
        ->post($this->checkoutUrl, $payload);

      /** @var Response $response */
      $this->writeLogs(
        $this->serviceName,
        'generateCheckout',
        $this->checkoutUrl,
        $payload,
        $headers,
        $response->status(),
        $response->body()
      );
      $resp = $response->json();
      if (!is_array($resp)) {
        return ['status' => 'F', 'message' => 'Invalid partner response'];
      }

      $apiResponseCode = (string) ($resp['apiResponseCode'] ?? '');
      $responseCode = (string) data_get($resp, 'apiResponseData.responseCode', '');
      $responseMessage = (string) data_get($resp, 'apiResponseData.responseMessage', 'Failed');
      $redirectionUrl = data_get($resp, 'apiResponseData.responseData.redirectionUrl');

      if ($apiResponseCode !== '200' || $responseCode !== '200') {
        return [
          'status' => 'F',
          'message' => $responseMessage,
          'payment_url' => null,
          'pgType' => $pgType,
          'response' => $resp,
        ];
      }

      return [
        'status' => 'S',
        'message' => $responseMessage,
        'payment_url' => $redirectionUrl,
        'pgType' => $pgType,
        'api_txn_id' => $urn,
        'response' => $resp,
      ];
    } catch (\Throwable $e) {
      $this->writeLogs(
        $this->serviceName,
        'generateCheckout',
        $this->checkoutUrl,
        $payload ?? [],
        $this->maskHeadersForLog($headers ?? $this->commonHeaders($urn, $reqLong, $reqLat, $requestSource)),
        500,
        $e->getMessage()
      );

      return ['status' => 'F', 'message' => $e->getMessage()];
    }
  }
  public function checkOrderStatus(
    string $urn,
    string $orderId,
    ?string $reqLong = null,
    ?string $reqLat = null,
    ?string $requestSource = null
  ): array {
    try {
      $token = $this->generateToken($urn, $reqLong, $reqLat, $requestSource);
      if (!$token) {
        return ['status' => 'F', 'message' => 'Token generation failed'];
      }

      $headers = $this->authorizedHeaders($urn, $token, $reqLong, $reqLat, $requestSource);
      $payload = ['orderId' => $orderId];

      $response = Http::withHeaders($headers)
        ->timeout($this->timeout)
        ->post($this->statusUrl, $payload);
      /** @var Response $response */
      $this->writeLogs(
        $this->serviceName,
        'checkOrderStatus',
        $this->statusUrl,
        $payload,
        $headers,
        $response->status(),
        $response->body()
      );
      return [
        'status' => 'S',
        'http_code' => $response->status(),
        'response' => $response->json(),
      ];
    } catch (\Throwable $e) {
      $this->writeLogs(
        $this->serviceName,
        'checkOrderStatus',
        $this->statusUrl,
        ['orderId' => $orderId],
        $this->maskHeadersForLog($this->commonHeaders($urn, $reqLong, $reqLat, $requestSource)),
        500,
        $e->getMessage()
      );

      return ['status' => 'F', 'message' => $e->getMessage()];
    }
  }

  public function getBalance(string $urn, ?string $reqLong = null, ?string $reqLat = null, ?string $requestSource = null): array
  {
    try {
      $token = $this->generateToken($urn, $reqLong, $reqLat, $requestSource);
      if (!$token) {
        return ['status' => 'F', 'message' => 'Token generation failed'];
      }

      $headers = $this->authorizedHeaders($urn, $token, $reqLong, $reqLat, $requestSource);

      $response = Http::withHeaders($headers)
        ->timeout($this->timeout)
        ->get($this->balanceUrl);

      /** @var Response $response */
      $this->writeLogs(
        $this->serviceName,
        'getBalance',
        $this->balanceUrl,
        [],
        $this->maskHeadersForLog($headers),
        $response->status(),
        $response->body()
      );

      $resp = $response->json();

      return [
        'status' => 'S',
        'http_code' => $response->status(),
        'operativeBalance' => data_get($resp, 'apiResponseData.responseData.operativeBalance'),
        'lienAmount' => data_get($resp, 'apiResponseData.responseData.lienAmount'),
        'response' => $resp,
      ];
    } catch (\Throwable $e) {
      $this->writeLogs(
        $this->serviceName,
        'getBalance',
        $this->balanceUrl,
        [],
        $this->maskHeadersForLog($this->commonHeaders($urn, $reqLong, $reqLat, $requestSource)),
        500,
        $e->getMessage()
      );

      return ['status' => 'F', 'message' => $e->getMessage()];
    }
  }

  private function commonHeaders(
    string $urn,
    ?string $reqLong = null,
    ?string $reqLat = null,
    ?string $requestSource = null
  ): array {
    return [
      'clientId' => $this->clientId,
      'urn' => $urn,
      'agentId' => $this->agentId,
      'counterId' => $this->counterId,
      'bearerData' => $this->buildBearerData($reqLong, $reqLat, $requestSource),
      'Content-Type' => 'application/json',
      'Accept' => 'application/json',
    ];
  }

  private function authorizedHeaders(
    string $urn,
    string $token,
    ?string $reqLong = null,
    ?string $reqLat = null,
    ?string $requestSource = null
  ): array {
    return $this->commonHeaders($urn, $reqLong, $reqLat, $requestSource) + [
      'Authorization' => 'Bearer ' . $token,
    ];
  }

  private function buildBearerData(?string $reqLong = null, ?string $reqLat = null, ?string $requestSource = null): string
  {
    $bearerData = [
      'requestIp' => '148.135.142.33',
      'reqLong' => $reqLong,
      'reqLat' => $reqLat,
      'requestSource' => $requestSource ?: $this->defaultRequestSource,
      'localTimeStamp' => now()->format('Y-m-d H:i:s'),
      'version' => $this->version,
      'appName' => $this->appName,
      'scope' => $this->scope,
    ];

    return base64_encode(json_encode($bearerData, JSON_UNESCAPED_SLASHES));
  }

  private function getServerIp(): string
  {
    return (string) (
      $_SERVER['SERVER_ADDR']
      ?? getHostByName(getHostName())
      ?? request()->server('SERVER_ADDR')
      ?? '127.0.0.1'
    );
  }

  private function writeLogs(
    string $service,
    string $api,
    string $url,
    $requestPayload = null,
    $headers = null,
    $responseCode = null,
    $responseData = null
  ) {
    $log = [
      'service' => $service,
      'api' => $api,
      'url' => $url,
      'ip' => request()->ip(),
      'datetime' => now()->toDateTimeString(),
      'headers' => $headers,
      'payload' => $requestPayload,
      'http_code' => $responseCode,
      'response_data' => $responseData,
    ];

    Log::channel('finkeda')->info('Finkeda Payin Log', $log);
  }

  private function maskHeadersForLog(array $headers): array
  {
    $masked = $headers;

    if (!empty($masked['Authorization'])) {
      $masked['Authorization'] = 'Bearer ********';
    }

    if (!empty($masked['bearerData'])) {
      $masked['bearerData'] = '********';
    }

    return $masked;
  }
  public function handlePayinCallback()
  {
    $url = request()->fullUrl();
    $headers = request()->headers->all();
    $decodedData = request()->all();
    if (is_string($decodedData)) {
      $decodedData = json_decode($decodedData, true);
    }
    $this->writeLogs(
      'FINKEDA_PAYIN',
      'Payin_Callback',
      $url,
      request()->all(),
      json_encode($headers),
      200,
      json_encode($decodedData)
    );

    if (!$decodedData) {
      return response()->json(['status' => 'F', 'message' => 'Invalid callback data'], 400);
    }
    $responseCode    = (string) data_get($decodedData, 'apiResponseData.responseCode', '');
    $data = $decodedData['apiResponseData']['data'] ?? [];
    $txn_id       = $data['urn'] ?? null;
    $api_txn_id   = $data['orderId'] ?? null;
    $amount       = isset($data['amount']) ? (float)$data['amount'] : null;
    $status       = strtoupper($data['status'] ?? '');
    $pgStatus     = strtoupper($data['pgStatus'] ?? '');
    $utr          = $data['transactionID'] ?? null;
    if ($responseCode == '401') {
      return view('payments.payment-status', [
        'status' => 'FAILED',
        'message' => 'Transaction failed',
        'txn_id' => $txn_id,
        'amount' => $amount
      ]);
    }
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

    $isSuccess = ($status === 'SUCCESS' && $pgStatus === 'SUCCESS');
    $isFailed  = ($status === 'FAILED' || $pgStatus === 'FAILED');

    if ($txn_detail->status !== 'P') {
      return response()->json(['status' => 'S', 'message' => 'Already processed.']);
    }

    if ($isSuccess) {

      $message = 'Transaction successful';

      $credit_status = $this->creditUserWalletAmountPG(
        $txn_detail->user_id,
        $txn_id,
        $txn_detail->amount
      );

      if (($credit_status['status'] ?? false) == true) {

        DB::table('payin_transactions')->where('txn_id', $txn_id)->update([
          'status'           => 'S',
          'payment_status'   => 'S',
          'charge_amount'    => $credit_status['charge_amount'] ?? 0.00,
          'gst_amount'       => $credit_status['gst_amount'] ?? 0.00,
          'total_charge'     => $credit_status['total_charge'] ?? 0.00,
          'total_amount'     => $credit_status['total_amount'] ?? 0.00,
          'api_txn_id'       => $api_txn_id,
          'utr'              => $utr,
          'response_message' => $message,
          'description'      => $message,
          'updated_at'       => now(),
          'updated_by'       => '0',
        ]);
      } else {

        DB::table('payin_transactions')->where('txn_id', $txn_id)->update([
          'status'           => 'P',
          'api_txn_id'       => $api_txn_id,
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
        'api_txn_id'       => $api_txn_id,
        'utr'              => $utr,
        'response_message' => 'Transaction failed',
        'description'      => 'Transaction failed',
        'updated_at'       => now(),
        'updated_by'       => '0',
      ]);
    } else {

      DB::table('payin_transactions')->where('txn_id', $txn_id)->update([
        'status'           => 'P',
        'api_txn_id'       => $api_txn_id,
        'utr'              => $utr,
        'response_message' => "Waiting final success (status={$status}, pgStatus={$pgStatus})",
        'description'      => "Waiting final success",
        'updated_at'       => now(),
        'updated_by'       => '0',
      ]);
    }
    return view('payments.payment-status', [
      'status' => $status,
      'message' => $message ?? '',
      'txn_id' => $txn_id,
      'amount' => $amount
    ]);
  }
  public function handlePayinRedirection()
  {
    $url = request()->fullUrl();
    $headers = request()->headers->all();
    $status = request()->query('status');
    $decodedData = null;

    if ($status) {
      $base64Decoded = base64_decode(urldecode($status));
      $decodedData = json_decode($base64Decoded, true);
    }
    $this->writeLogs(
      'FINKEDA_PAYIN',
      'Payin_Redirection',
      $url,
      request()->all(),
      json_encode($headers),
      200,
      json_encode($decodedData)
    );
    if (!$decodedData) {
      return response()->json(['status' => 'F', 'message' => 'Invalid callback data'], 400);
    }
    $responseCode    = (string) data_get($decodedData, 'apiResponseData.responseCode', '');
    $data = $decodedData['apiResponseData']['data'] ?? [];
    $txn_id       = $data['urn'] ?? null;
    $amount       = isset($data['amount']) ? (float)$data['amount'] : null;
    $status       = strtoupper($data['status'] ?? '');
    if ($responseCode == '401') {
      return view('payments.payment-status', [
        'status' => 'FAILED',
        'message' => 'Transaction failed',
        'txn_id' => $txn_id,
        'amount' => $amount
      ]);
    }
    return view('payments.payment-status', [
      'status' => $status,
      'message' => $message ?? '',
      'txn_id' => $txn_id,
      'amount' => $amount
    ]);
  }
  private function creditUserWalletAmountPG($user_id, $refid, $amount)
  {
    $transactionExists = DB::table('user_wallet_transactions')
      ->where('refid', $refid)
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
      $wallet_balance = $user->payin_balance;
      $service_name = 'PAYIN';
      $charge_percentage = $user_service->payin_api_charges ?? 5.00;
      $charge_amount = ($amount * $charge_percentage) / 100;
      $gst_amount = ($charge_amount * 18) / 100;
      $total_charge = $charge_amount + $gst_amount;
      $total_amount = $amount - $total_charge;
      $opening_balance = $wallet_balance;
      $closing_balance = $opening_balance + $total_amount;
      DB::table('user_wallet_transactions')->insert([
        'user_id'         => $user_id,
        'refid'           => $refid,
        'service_name'    => $service_name,
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
}
