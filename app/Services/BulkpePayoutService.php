<?php

namespace App\Services;

use App\Helpers\Helpers;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;

class BulkpePayoutService
{
  private string $serviceName = 'BULKPE_PAYOUT';

  private string $baseUrl;
  private string $authToken;
  private int $timeout;
  private string $logChannel;

  private string $payoutUrl;
  private string $statusUrl;
  private string $balanceUrl;
  protected User $user;

  public function __construct()
  {
    $this->baseUrl    = rtrim((string) config('services.bulkpe.base_url'), '/') . '/';
    $this->authToken  = (string) config('services.bulkpe.auth_token');
    $this->timeout    = (int) (config('services.bulkpe.timeout') ?? 120);
    $this->logChannel = (string) (config('services.bulkpe.log_channel') ?? 'daily');

    $this->payoutUrl  = $this->baseUrl . 'initiatePayout';
    $this->statusUrl  = $this->baseUrl . 'fetchStatus';
    $this->balanceUrl = $this->baseUrl . 'fetchBalance';
  }

  /**
   * Initiate payout transaction
   */
  public function initiateTransaction(
    string $txn_id,
    string $bene_account,
    string $bene_ifsc,
    float $amount,
    string $txn_mode,
    string $bene_name,
    $user
  ) {
    $this->user = $user;
    $amount = round($amount, 2);

    $headers = $this->defaultHeaders();

    $payload = [
      'amount'          => $amount,
      'account_number'  => $bene_account,
      'beneficiaryName' => $bene_name,
      'reference_id'    => $txn_id,
      'ifsc'            => $bene_ifsc,
      'payment_mode'    => $txn_mode,
    ];

    try {
      $response = Http::withHeaders($headers)
        ->timeout($this->timeout)
        ->post($this->payoutUrl, $payload);

      /** @var Response $response */

      $this->writeLogs(
        $this->serviceName,
        'initiatePayout',
        $this->payoutUrl,
        $payload,
        $this->maskHeadersForLog($headers),
        $response->status(),
        $response->body()
      );

      $resp = $response->json();

      // Validate response format
      if (!is_array($resp)) {
        return response()->json([
          'status' => 'P',
          'message' => 'Invalid partner response',
          'utr' => null,
          'api_txn_id' => null,
        ]);
      }

      // When partner says request itself failed
      if (!($resp['status'] ?? false)) {
        return response()->json([
          'status' => 'F',
          'message' => $resp['message'] ?? 'Failed',
          'utr' => null,
          'api_txn_id' => null,
        ]);
      }

      // When partner says request ok but business pending
      if (($resp['statusCode'] ?? null) !== '200') {
        return response()->json([
          'status' => 'P',
          'message' => $resp['message'] ?? 'Pending',
          'utr' => null,
          'api_txn_id' => null,
        ]);
      }

      $data = $resp['data'] ?? [];
      $st = strtoupper((string)($data['status'] ?? ''));

      if ($st === 'SUCCESS') {
        return response()->json([
          'status' => 'S',
          'message' => $data['message'] ?? 'Payment success',
          'utr' => $data['utr'] ?? null,
          'api_txn_id' => $data['transcation_id'] ?? null, // spelling as per reference
        ]);
      }

      if ($st === 'FAILED') {
        return response()->json([
          'status' => 'F',
          'message' => $data['message'] ?? 'Payment failed',
          'utr' => $data['utr'] ?? null,
          'api_txn_id' => $data['transcation_id'] ?? null,
        ]);
      }

      return response()->json([
        'status' => 'P',
        'message' => $data['message'] ?? 'Pending',
        'utr' => $data['utr'] ?? null,
        'api_txn_id' => $data['transcation_id'] ?? null,
      ]);
    } catch (\Throwable $e) {
      $this->writeLogs(
        $this->serviceName,
        'initiatePayout',
        $this->payoutUrl,
        $payload,
        $this->maskHeadersForLog($headers),
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
   * Fetch payout status
   * Returns array so controller/service can decide how to use it.
   */
  public function checkPayoutTxnStatus(string $txn_id, $user): array
  {
    $this->user = $user;
    $headers = $this->defaultHeaders();
    $payload = ['reference_id' => $txn_id];

    try {
      $response = Http::withHeaders($headers)
        ->timeout($this->timeout)
        ->post($this->statusUrl, $payload);

      /** @var Response $response */

      $this->writeLogs(
        $this->serviceName,
        'fetchStatus',
        $this->statusUrl,
        $payload,
        $this->maskHeadersForLog($headers),
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
        'fetchStatus',
        $this->statusUrl,
        $payload,
        $this->maskHeadersForLog($headers),
        500,
        $e->getMessage()
      );

      return ['status' => 'F', 'message' => $e->getMessage()];
    }
  }

  /**
   * Fetch API balance (CI reference compatible)
   * Returns:
   *  - S => balance available
   *  - Q => pending/insufficient
   *  - F => error
   */
  public function getApiBalance(float $transactionAmount, $user): array
  {
    $this->user = $user;
    $headers = $this->defaultHeaders();

    try {
      $response = Http::withHeaders($headers)
        ->timeout($this->timeout)
        ->get($this->balanceUrl);

      /** @var Response $response */

      $this->writeLogs(
        $this->serviceName,
        'fetchBalance',
        $this->balanceUrl,
        [],
        $this->maskHeadersForLog($headers),
        $response->status(),
        $response->body()
      );

      $resp = $response->json();

      if (!is_array($resp) || !($resp['status'] ?? false)) {
        return ['status' => 'Q', 'message' => $resp['message'] ?? 'Transaction Pending'];
      }

      $accounts = $resp['data']['Accounts'] ?? [];
      $balance = null;

      if (isset($accounts[1]['Balance'])) {
        $balance = (float) $accounts[1]['Balance'];
      } elseif (isset($accounts[0]['Balance'])) {
        $balance = (float) $accounts[0]['Balance'];
      }

      if ($balance === null) {
        return ['status' => 'Q', 'message' => 'Balance not found'];
      }

      if ($balance < $transactionAmount) {
        return ['status' => 'Q', 'message' => 'Transaction Pending'];
      }

      return ['status' => 'S', 'message' => 'Balance Available', 'balance' => $balance];
    } catch (\Throwable $e) {
      $this->writeLogs(
        $this->serviceName,
        'fetchBalance',
        $this->balanceUrl,
        [],
        $this->maskHeadersForLog($headers),
        500,
        $e->getMessage()
      );

      return ['status' => 'F', 'message' => $e->getMessage()];
    }
  }

  /**
   * Standard headers for BulkPe
   */
  private function defaultHeaders(): array
  {
    if ($this->user->services->wallet_type == 'virtual_wallet') {
      $decryptedAuthToken = Helpers::decryptValue($this->user->apiKey->bulkpe_auth_token);
      $this->authToken = $decryptedAuthToken;
    } elseif ($this->user->services->wallet_type == 'payout_wallet') {
      $this->authToken = config('services.bulkpe.auth_token');
    } else {
      return [
        'status'  => 'F',
        'message' => 'Payment failed',
      ];
    }
    return [
      'Authorization' => 'Bearer ' . $this->authToken,
      'Content-Type'  => 'application/json',
      'Accept'        => 'application/json',
    ];
  }

  /**
   * Logs with standard structure + token masking
   */
  private function writeLogs(
    string $service,
    string $api,
    string $url,
    $request_payload = null,
    $headers = null,
    $response_code = null,
    $response_data = null
  ): void {
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

    Log::channel($this->logChannel)->info('BulkPe Payout Log', $log);
  }
  public function handlePayoutCallback() {}
  /**
   * Avoid logging full bearer token
   */
  private function maskHeadersForLog(array $headers): array
  {
    $masked = $headers;

    if (!empty($masked['Authorization'])) {
      $masked['Authorization'] = 'Bearer ********';
    }

    return $masked;
  }
}
