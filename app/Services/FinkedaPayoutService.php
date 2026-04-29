<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;

class FinkedaPayoutService
{
  private string $serviceName = 'FINKEDA_PAYOUT';

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
  private string $payoutUrl;
  private string $statusUrl;

  public function __construct()
  {
    $this->baseUrl = rtrim((string) config('services.finkeda_payout.base_url'), '/');
    $this->clientId = (string) config('services.finkeda_payout.client_id');
    $this->agentId = (string) config('services.finkeda_payin.agent_id');
    $this->counterId = (string) config('services.finkeda_payin.counter_id');

    $this->version = (string) (config('services.finkeda_payin.version') ?? '1.0');
    $this->appName = (string) (config('services.finkeda_payin.app_name') ?? 'TERMINAL');
    $this->scope = (string) (config('services.finkeda_payin.scope') ?? 'TERMINAL LOAD');
    $this->defaultRequestSource = (string) (config('services.finkeda_payin.request_source') ?? 'WEB');
    $this->timeout    = (int) (config('services.finkeda_payout.timeout') ?? 120);
    $this->logChannel = (string) (config('services.finkeda_payout.log_channel') ?? 'daily');
    $this->tokenUrl  = $this->baseUrl . '/api/session/terminal/token';
    $this->payoutUrl = $this->baseUrl . '/api/terminal/payout';
    $this->statusUrl = $this->baseUrl . '/terminal/payout/getStatus';
  }

  /**
   * Initiate payout transaction
   */
  public function initiateTransaction(
    string $orderIdOrRef,
    string $bene_account,
    string $bene_ifsc,
    float $amount,
    string $bene_name,
    string $bene_mobile,
    string $bene_email,
    string $reqLong,
    string $reqLat,
  ) {
    $bene_address = '102';
    $txn_mode = 'IMPS';
    $amount = round($amount, 2);
    $remark = 'Payout';
    try {
      $token = $this->generateToken($orderIdOrRef, $reqLong, $reqLat);
      if (!$token) {
        return ['status' => 'F', 'message' => 'Token generation failed'];
      }
      $headers = $this->authorizedHeaders($orderIdOrRef, $token, $reqLong, $reqLat);
      $payload = [
        'beneficiaryAcount'     => $bene_account,
        'beneficiaryName'       => $bene_name,
        'requestAmount'         => (string) $amount,
        'beneficiaryIFSC'       => $bene_ifsc,
        'requestTransferType'   => strtoupper($txn_mode), // IMPS/NEFT/RTGS
        'beneMobile'            => $bene_mobile,
        'beneEmail'             => $bene_email,
        'beneAddress'           => $bene_address,
        'remark'                => $remark,
      ];

      $response = Http::withHeaders($headers)
        ->timeout($this->timeout)
        ->post($this->payoutUrl, $payload);

      /** @var Response $response */
      $this->writeLogs(
        $this->serviceName,
        'initiatePayout',
        $this->payoutUrl,
        $payload,
        $headers,
        $response->status(),
        $response->body()
      );

      $resp = $response->json();

      if (!is_array($resp)) {
        return response()->json([
          'status' => 'P',
          'message' => 'Invalid partner response',
          'utr' => null,
          'api_txn_id' => null,
        ]);
      }
      $apiCode = (string) ($resp['apiResponseCode'] ?? '');
      $apiData = $resp['apiResponseData'] ?? [];

      $partnerCode = (string) ($apiData['responseCode'] ?? '');
      $partnerMsg  = (string) ($apiData['responseMessage'] ?? ($resp['apiResponseMessage'] ?? ''));
      $data        = $apiData['data'] ?? ($apiData['responseData'] ?? []);

      $utr = $data['utr'] ?? $data['utr_rrn'] ?? null;
      $apiTxnId = $data['orderId'] ?? $data['external_id'] ?? $data['userrequestid'] ?? null;

      // If gateway itself is not successful
      if ($apiCode !== '200') {
        return response()->json([
          'status' => 'P',
          'message' => $partnerMsg ?: 'Pending',
          'utr' => $utr,
          'api_txn_id' => $apiTxnId,
        ]);
      }
      if ($partnerCode === '200') {
        return response()->json([
          'status' => 'S',
          'message' => $partnerMsg ?: 'Success',
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
        $this->serviceName,
        'initiatePayout',
        $this->payoutUrl,
        $payload ?? null,
        $headers ?? $this->commonHeaders($orderIdOrRef),
        500,
        $e->getMessage()
      );
      return response()->json([
        'status' => 'P',
        'message' => $e->getMessage(),
        'utr' => null,
        'api_txn_id' => null,
      ]);
    }
  }

  /**
   * PATH: terminal/payout/getStatus?orderId=<urn>
   */
  public function checkPayoutTxnStatus(string $orderId, $reqLong, $reqLat): array
  {
    try {
      $token = $this->generateToken($orderId, $reqLong, $reqLat);
      if (!$token) {
        return ['status' => 'F', 'message' => 'Token generation failed'];
      }
      $headers = $this->authorizedHeaders($orderId, $token, $reqLong, $reqLat);
      $response = Http::withHeaders($headers)
        ->timeout($this->timeout)
        ->get($this->statusUrl, ['orderId' => $orderId]);

      /** @var Response $response */
      $this->writeLogs(
        $this->serviceName,
        'getStatus',
        $this->statusUrl,
        ['orderId' => $orderId],
        $headers,
        $response->status(),
        $response->body()
      );

      return [
        'status'    => 'S',
        'http_code' => $response->status(),
        'response'  => $response->json(),
      ];
    } catch (\Throwable $e) {
      $this->writeLogs(
        $this->serviceName,
        'getStatus',
        $this->statusUrl,
        ['orderId' => $orderId],
        $this->maskHeadersForLog($this->commonHeaders($orderId)),
        500,
        $e->getMessage()
      );

      return ['status' => 'F', 'message' => $e->getMessage()];
    }
  }

  /**
   * GET /api/session/terminal/token, JWT valid 30 minutes
   */
  public function generateToken(string $urn, ?string $reqLong = null, ?string $reqLat = null): ?string
  {
    $cacheKey = 'finkeda_payout_token_' . md5($this->agentId . '|' . $this->counterId);
    return Cache::remember($cacheKey, now()->addMinutes(29), function () use ($urn, $reqLong, $reqLat) {
      $headers = $this->commonHeaders($urn, $reqLong, $reqLat);
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

  /**
   * Common headers:
   */
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

  private function buildBearerData(?string $reqLong = null, ?string $reqLat = null,): string
  {
    $bearerData = [
      'requestIp' => '148.135.142.33',
      'reqLong' => $reqLong,
      'reqLat' => $reqLat,
      'requestSource' => 'WEB',
      'localTimeStamp' => now()->format('Y-m-d H:i:s'),
      'version' => $this->version,
      'appName' => $this->appName,
      'scope' => $this->scope,
    ];

    return base64_encode(json_encode($bearerData, JSON_UNESCAPED_SLASHES));
  }
  private function commonHeaders(
    string $urn,
    ?string $reqLong = null,
    ?string $reqLat = null,
  ): array {
    $requestSource = 'WEB';
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

    Log::channel('finkeda')->info('Finkeda Payout Log', $log);
  }

  private function maskHeadersForLog(array $headers): array
  {
    $masked = $headers;

    if (!empty($masked['Authorization'])) {
      $masked['Authorization'] = 'Basic ********';
    }

    return $masked;
  }

  public function handlePayoutCallback()
  {
    // Not defined in the provided PDF, so keeping empty.
  }
}
