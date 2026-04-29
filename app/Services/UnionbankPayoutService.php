<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class UnionbankPayoutService
{
  // === Finsova / Union Bank config ===
  private string $username;
  private string $password;

  // AES details (32-char key, 16-char IV) — as per Java util (AES/CBC/PKCS5Padding)
  private string $aesKey; // 32 chars
  private string $aesIv;  // 16 chars

  // Sender / Remitter static details (as required by FundTransfer API)
  private string $senderCode;
  private string $remitterAccNo;
  private string $remitterName;
  private string $remitterAddress;
  private string $remitterMobile;
  private string $remitterEmail;

  // URLs from documentation
  private string $tokenUrl;
  private string $fundTransferUrl;
  private string $fundStatusUrl;
  private string $BankStatementUrl;
  private string $CheckBalUrl;

  private string $cacheKey = 'finsova_unionbank_token';

  public function __construct()
  {
    // 🔐 Load from env/config (recommended)
    $this->username = (string) config('services.finsova.username', env('FINSOVA_USERNAME', ''));
    $this->password = (string) config('services.finsova.password', env('FINSOVA_PASSWORD', ''));

    $this->aesKey = (string) config('services.finsova.aes_key', env('FINSOVA_AES_KEY', ''));
    $this->aesIv  = (string) config('services.finsova.aes_iv',  env('FINSOVA_AES_IV', ''));

    $this->senderCode = (string) config('services.finsova.sender_code', env('FINSOVA_SENDER_CODE', 'Finsova'));

    $this->remitterAccNo   = (string) config('services.finsova.remitter_acc', env('FINSOVA_REMITTER_ACC', ''));
    $this->remitterName    = (string) config('services.finsova.remitter_name', env('FINSOVA_REMITTER_NAME', 'Finsova Fintech Private Limited'));
    $this->remitterAddress = (string) config('services.finsova.remitter_address', env('FINSOVA_REMITTER_ADDRESS', 'MUMBAI'));
    $this->remitterMobile  = (string) config('services.finsova.remitter_mobile', env('FINSOVA_REMITTER_MOBILE', '9999999999'));
    $this->remitterEmail   = (string) config('services.finsova.remitter_email', env('FINSOVA_REMITTER_EMAIL', 'support@example.com'));

    // URLs (UAT as per PDF)
    $this->tokenUrl        = (string) config('services.finsova.token_url', env(
      'FINSOVA_TOKEN_URL',
      'https://apim.unionbankofindia.bank.in/BankServices/handlerpb/1/Finsova/FinsovaServiceGroups/tokenGenApi'
    ));

    $this->fundTransferUrl = (string) config('services.finsova.fund_transfer_url', env(
      'FINSOVA_FUND_TRANSFER_URL',
      'https://apim.unionbankofindia.bank.in/BankServices/handlersb/1/Finsova/FinsovaServiceGroups/fundTransferServiceExternal'
    ));

    $this->fundStatusUrl   = (string) config('services.finsova.fund_status_url', env(
      'FINSOVA_FUND_STATUS_URL',
      'https://apim.unionbankofindia.bank.in/BankServices/handlersb/1/Finsova/FinsovaServiceGroups/fundStatusService'
    ));
    $this->BankStatementUrl   = (string) config('services.finsova.bankstatement_url', env(
      'FINSOVA_BANKSTATEMENT_URL',
      'https://apim.unionbankofindia.bank.in/BankServices/handlerpb/1/Finsova/FinsovaServiceGroups/fetchAccountStatementApi'
    ));
    $this->CheckBalUrl   = (string) config('services.finsova.checkbal_url', env(
      'FINSOVA_CHECKBAL_URL',
      'https://apim.unionbankofindia.bank.in/BankServices/handlerpb/1/Finsova/FinsovaServiceGroups/balanceEnquiryService'
    ));
  }

  /**
   * Same signature you already use.
   * Creates payout using FundTransfer API.
   */
  public function initiateTransaction(
    string $txn_id,
    string $bene_account,
    string $bene_ifsc,
    float $amount,
    string $bene_name,
    string $email,
    string $mobile
  ) {
    // basic config validation
    if (!$this->isCryptoConfigured()) {
      return response()->json([
        'status'     => 'F',
        'message'    => 'Finsova AES config missing (key/iv).',
        'utr'        => null,
        'api_txn_id' => null,
      ]);
    }

    [$ok, $tokenOrError] = $this->getToken();
    if (!$ok) {
      return response()->json([
        'status'     => 'F',
        'message'    => $tokenOrError ?: 'Failed to generate token.',
        'utr'        => null,
        'api_txn_id' => null,
      ]);
    }
    $address = '102 Sector 52';
    $token = $tokenOrError;
    $tranType = ($amount >= 200000) ? 'RTGS' : 'NEFT';
    $payload = [
      'data' => [
        'type'                   => 'account',
        'tranType'               => $tranType,
        'senderCode'             => $this->senderCode,
        'transactionId'          => $txn_id,
        'beneficiaryAccNo'       => $bene_account,
        'beneficiaryAccName'     => $bene_name,
        'beneficiaryAddress'     => $address,
        'beneficiaryBankIFSCCode' => $bene_ifsc,
        'beneficiaryMobileNumber' => $mobile,
        'beneficiaryEmailId'     => $email,
        'transactionAmount'      => number_format((float) $amount, 2, '.', ''),
        'transactionDate'        => date('dmY'),
        'remitterAccNo'          => $this->remitterAccNo,
        'remitterName'           => $this->remitterName,
        'remitterAddress'        => $this->remitterAddress,
        'countryCode'            => 'IND',
        'remitterMobileNumber'   => $this->remitterMobile,
        'remitterEmailId'        => $this->remitterEmail,
        'purpose'                => 'P08',
      ],
    ];
    $msgid = $this->generateMsgId();
    $apiRes = $this->postEncrypted(
      url: $this->fundTransferUrl,
      bearerToken: $token,
      plainPayload: $payload,
      msgid: $msgid,
      apiName: 'FundTransfer'
    );
    if (!$apiRes['ok']) {
      return response()->json([
        'status'     => 'P',
        'message'    => $apiRes['message'] ?? 'Partner bank confirmation pending.',
        'utr'        => null,
        'api_txn_id' => null,
      ]);
    }
    if (is_string($apiRes)) {
      $apiRes = json_decode($apiRes, true);
    }
    $data = $apiRes['data'] ?? [];
    $apiStatus     = trim((string) ($data['status'] ?? ''));
    $responseCode  = (string) ($data['responseCode'] ?? '');
    $bankTxnId     = $data['bankTxnId'] ?? null;
    $transactionId = $msgid;
    if ($responseCode === '000') {
      return response()->json([
        'status'     => 'S', // FIXED (not P)
        'message'    => $apiStatus ?: 'Transaction Successful',
        'utr'        => $bankTxnId,        // ✅ bankTxnId → utr
        'api_txn_id' => $transactionId,    // ✅ transactionId → api_txn_id
      ]);
    }
    if (in_array($responseCode, ['401', '906', '907', '908', '911'])) {
      return response()->json([
        'status'     => 'P',
        'message'    => $apiStatus ?: 'Transaction Pending',
        'utr'        => $bankTxnId,
        'api_txn_id' => $transactionId,
      ]);
    }
    return response()->json([
      'status'     => 'F',
      'message'    => $apiStatus ?: ('Failed with code ' . $responseCode),
      'utr'        => $bankTxnId,
      'api_txn_id' => $transactionId,
    ]);
  }

  /**
   * Your existing TransactionStatus method, now calls Fund Status API.
   */
  public function TransactionStatus($txn_id)
  {
    if (!$this->isCryptoConfigured()) {
      return (object) [
        'status'  => 'F',
        'message' => 'Finsova AES config missing (key/iv).',
        'utr'     => null,
      ];
    }

    [$ok, $tokenOrError] = $this->getToken();
    if (!$ok) {
      return (object) [
        'status'  => 'F',
        'message' => $tokenOrError ?: 'Failed to generate token.',
        'utr'     => null,
      ];
    }

    $token = $tokenOrError;

    $payload = [
      'data' => [
        'type'          => 'account',
        'senderCode'    => $this->senderCode,
        'transactionId' => (string) $txn_id,
      ],
    ];

    $msgid = $this->generateMsgId();

    $apiRes = $this->postEncrypted(
      url: $this->fundStatusUrl,
      bearerToken: $token,
      plainPayload: $payload,
      msgid: $msgid,
      apiName: 'FundStatus'
    );

    if (!$apiRes['ok']) {
      return (object) [
        'status'  => 'P',
        'message' => $apiRes['message'] ?? 'Confirmation pending from partner bank.',
        'utr'     => null,
      ];
    }

    $data = $apiRes['data'] ?? [];

    $txnStatus = strtoupper((string) ($data['status'] ?? ''));
    $responseCode = (string) ($data['responseCode'] ?? '');
    $bankTxnId = $data['bankTxnId'] ?? null;

    // Typical: SUCCESS / or error message in status
    if ($responseCode === '000' && $txnStatus === 'SUCCESS') {
      return (object) [
        'status'  => 'S',
        'message' => 'Transaction successful',
        'utr'     => $bankTxnId, // bankTxnId used as reference
      ];
    } else if ($txnStatus === 'FAILED') {
      return (object) [
        'status'  => 'F',
        'message' => 'Transaction Failed',
        'utr'     => $bankTxnId,
      ];
    }

    // If bank says not found / invalid etc., treat as Failed
    if (in_array($responseCode, ['999', '998', '913'], true)) {
      return (object) [
        'status'  => 'F',
        'message' => 'Transaction Failed',
        'utr'     => $bankTxnId,
      ];
    }

    return (object) [
      'status'  => 'P',
      'message' => 'Transaction might go pending stage',
      'utr'     => $bankTxnId,
    ];
  }
  public function bankstatement($startdate, $enddate)
  {
    // basic config validation
    if (!$this->isCryptoConfigured()) {
      return response()->json([
        'status'     => 'F',
        'message'    => 'Finsova AES config missing (key/iv).',
        'utr'        => null,
        'api_txn_id' => null,
      ]);
    }

    [$ok, $tokenOrError] = $this->getToken();
    if (!$ok) {
      return response()->json([
        'status'     => 'F',
        'message'    => $tokenOrError ?: 'Failed to generate token.',
        'utr'        => null,
        'api_txn_id' => null,
      ]);
    }
    $token = $tokenOrError;
    $payload = [
      'data' => [
        'type'                   => 'account',
        'accNum'          => $this->remitterAccNo,
        'startDate'           => $startdate,
        'endDate'        => $enddate,
        'senderCode'            => $this->senderCode,
      ],
    ];
    $msgid = $this->generateMsgId();
    $apiRes = $this->postEncrypted(
      url: $this->BankStatementUrl,
      bearerToken: $token,
      plainPayload: $payload,
      msgid: $msgid,
      apiName: 'BankStatement'
    );
    if (!$apiRes['ok']) {
      return response()->json([
        'status'     => 'P',
        'message'    => $apiRes['message'] ?? 'Partner bank confirmation pending.',
        'utr'        => null,
        'api_txn_id' => null,
      ]);
    }
    if (is_string($apiRes)) {
      $apiRes = json_decode($apiRes, true);
    }

    return $apiRes['data']['transactionDetails'] ?? [];
  }
  public function getBalance()
  {
    // basic config validation
    if (!$this->isCryptoConfigured()) {
      return response()->json([
        'status'     => 'F',
        'message'    => 'Finsova AES config missing (key/iv).',
        'utr'        => null,
        'api_txn_id' => null,
      ]);
    }

    [$ok, $tokenOrError] = $this->getToken();
    if (!$ok) {
      return response()->json([
        'status'     => 'F',
        'message'    => $tokenOrError ?: 'Failed to generate token.',
        'utr'        => null,
        'api_txn_id' => null,
      ]);
    }
    $token = $tokenOrError;
    $payload = [
      'data' => [
        'type'                   => 'account',
        'accountNumber'          => $this->remitterAccNo,
        'senderCode'            => $this->senderCode,
      ],
    ];
    $msgid = $this->generateMsgId();
    $apiRes = $this->postEncrypted(
      url: $this->CheckBalUrl,
      bearerToken: $token,
      plainPayload: $payload,
      msgid: $msgid,
      apiName: 'CheckBalance'
    );
    if (!$apiRes['ok']) {
      return response()->json([
        'status'     => 'P',
        'message'    => $apiRes['message'] ?? 'Partner bank confirmation pending.',
        'utr'        => null,
        'api_txn_id' => null,
      ]);
    }
    if (is_string($apiRes)) {
      $apiRes = json_decode($apiRes, true);
    }
    $availBal = (float) ($apiRes['data']['amount']['AvailBal'] ?? 0.00);
    return response()->json([
      'balance' => number_format($availBal, 2, '.', '')
    ]);
  }
  /**
   * Union Bank APIM docs do NOT specify webhook for FundTransfer/FundStatus.
   * Keeping method so your routes won’t break, but it returns "not supported".
   */
  public function handlePayoutCallback()
  {
    $this->writeLogs(
      'FINSOVA_PAYOUT',
      'Callback_NotSupported',
      request()->fullUrl(),
      file_get_contents('php://input'),
      json_encode(request()->headers->all()),
      200,
      'Callback endpoint hit but not used for UnionBank APIM flow.'
    );

    return response()->json([
      'status'  => 'F',
      'message' => 'Callback not supported for this integration. Use TransactionStatus polling.',
    ], 200);
  }

  // ==========================================================
  // Token handling
  // ==========================================================

  private function getToken(): array
  {
    $cached = Cache::get($this->cacheKey);
    if (is_string($cached) && strlen($cached) > 20) {
      return [true, $cached];
    }

    $msgid = $this->generateMsgId();

    $plainPayload = [
      'data' => [
        'username' => $this->username,
        'password' => $this->password,
      ],
    ];

    $apiRes = $this->postEncrypted(
      url: $this->tokenUrl,
      bearerToken: null,
      plainPayload: $plainPayload,
      msgid: $msgid,
      apiName: 'TokenGen'
    );

    if (!$apiRes['ok']) {
      return [false, $apiRes['message'] ?? 'Token generation failed'];
    }

    $resp = $apiRes['raw_plain'] ?? [];
    $token = $resp['data']['token'] ?? null;
    $status = (string) ($resp['status'] ?? '');

    if ($status === '00' && is_string($token) && strlen($token) > 20) {
      Cache::put($this->cacheKey, $token, 55 * 60);
      return [true, $token];
    }

    return [false, $resp['errorMsg'] ?? 'Token not received'];
  }

  // ==========================================================
  // Core encrypted POST (matches PDF + Java util)
  // ==========================================================

  private function postEncrypted(
    string $url,
    ?string $bearerToken,
    array $plainPayload,
    string $msgid,
    string $apiName
  ): array {
    $plainJson = json_encode($plainPayload, JSON_UNESCAPED_SLASHES);

    if ($plainJson === false) {
      return [
        'ok' => false,
        'message' => 'Failed to encode request JSON',
      ];
    }

    $headers = [
      'Content-Type' => 'application/json',
      'Accept'       => 'application/json',
    ];

    if ($bearerToken) {
      $headers['Authorization'] = 'Bearer ' . $bearerToken;
    }

    $body = [];
    $response = null;
    $httpStatus = null;
    $rawResponseBody = null;
    $decryptedResponse = null;
    $logPayload = [];

    try {
      $encrypted = $this->encryptAesBase64($plainJson);

      $body = [
        'reqdata' => $encrypted,
        'msgid'   => $msgid,
      ];

      $response = Http::withHeaders($headers)
        ->timeout(60)
        ->connectTimeout(60)
        ->withOptions([
          'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
        ])
        ->post($url, $body);

      /** @var \Illuminate\Http\Client\Response $response */
      $httpStatus = $response->status();
      $rawResponseBody = (string) $response->body();

      // Try decrypting response if available
      if (trim($rawResponseBody) !== '') {
        try {
          $decryptedResponse = $this->decryptAesBase64(trim($rawResponseBody));
        } catch (\Throwable $decryptEx) {
          $decryptedResponse = 'DECRYPT_FAILED: ' . $decryptEx->getMessage();
        }
      }

      $this->writeLogs(
        service: 'FINSOVA_PAYOUT',
        api: $apiName,
        url: $url,
        msgid: $msgid,
        request_payload: $body,               // encrypted request actually sent
        plain_payload: $plainPayload,         // original plain request
        headers: $headers,
        response_code: $httpStatus,
        response_data: $rawResponseBody,      // encrypted/raw response
        plain_responsedata: $decryptedResponse // decrypted response
      );

      if (!$response->successful()) {
        return [
          'ok' => false,
          'message' => 'HTTP error: ' . $httpStatus,
          'raw_response' => $rawResponseBody,
          'decrypted_response' => $decryptedResponse,
        ];
      }

      $encBody = trim($rawResponseBody);
      if ($encBody === '') {
        return [
          'ok' => false,
          'message' => 'Empty response body',
        ];
      }

      $plain = $this->decryptAesBase64($encBody);

      $decoded = json_decode($plain, true);
      if (!is_array($decoded)) {
        return [
          'ok' => false,
          'message' => 'Failed to parse decrypted JSON response',
          'decrypted_response' => $plain,
        ];
      }

      $status = (string) ($decoded['status'] ?? '');
      if ($status !== '00') {
        return [
          'ok' => false,
          'message' => (string) ($decoded['errorMsg'] ?? 'API returned failure'),
          'raw_plain' => $decoded,
          'decrypted_response' => $plain,
        ];
      }

      return [
        'ok' => true,
        'raw_plain' => $decoded,
        'decrypted_response' => $plain,
        'data' => $decoded['data'] ?? [],
      ];
    } catch (\Throwable $e) {
      $this->writeLogs(
        service: 'FINSOVA_PAYOUT',
        api: $apiName,
        url: $url,
        msgid: $msgid,
        request_payload: $body,               // encrypted request actually sent
        plain_payload: $plainPayload,         // original plain request
        headers: $headers,
        response_code: $httpStatus,
        response_data: $rawResponseBody,      // encrypted/raw response
        plain_responsedata: $decryptedResponse // decrypted response
      );


      return [
        'ok' => false,
        'message' => $e->getMessage(),
      ];
    }
  }

  // ==========================================================
  // AES-256-CBC helpers (compatible with Java code)
  // ==========================================================

  private function encryptAesBase64(string $plain): string
  {
    // Java: AES/CBC/PKCS5Padding with UTF-8 bytes, then Base64
    $cipher = 'AES-256-CBC';

    $raw = openssl_encrypt(
      $plain,
      $cipher,
      $this->aesKey,           // ASCII bytes (32 chars)
      OPENSSL_RAW_DATA,        // raw bytes output
      $this->aesIv             // 16 chars
    );

    if ($raw === false) {
      throw new \RuntimeException('Encryption failed: ' . openssl_error_string());
    }

    return base64_encode($raw);
  }

  private function decryptAesBase64(string $encBase64): string
  {
    $cipher = 'AES-256-CBC';
    $decoded = base64_decode($encBase64, true);

    if ($decoded === false) {
      throw new \RuntimeException('Invalid base64 response');
    }

    $plain = openssl_decrypt(
      $decoded,
      $cipher,
      $this->aesKey,
      OPENSSL_RAW_DATA,
      $this->aesIv
    );

    if ($plain === false) {
      throw new \RuntimeException('Decryption failed: ' . openssl_error_string());
    }

    return $plain;
  }
  private function isCryptoConfigured(): bool
  {
    return (is_string($this->aesKey) && strlen($this->aesKey) === 32)
      && (is_string($this->aesIv)  && strlen($this->aesIv)  === 16);
  }

  private function generateMsgId(): string
  {
    return date('ymdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
  }

  private function writeLogs($service, $api, $url, $msgid = null, $request_payload = null, $plain_payload = null, $headers = null, $response_code = null, $response_data = null, $plain_responsedata = null)
  {
    $log = [
      'service'       => $service,
      'api'           => $api,
      'url'           => $url,
      'msgID'         => $msgid,
      'datetime'      => now()->toDateTimeString(),
      'headers'       => $headers,
      'payload'       => $request_payload,
      'plain_payload'       => $plain_payload,
      'http_code'     => $response_code,
      'response_data' => $response_data,
      'plain_response_data' => $plain_responsedata,
    ];

    Log::channel('finsova')->info('Finsova/UnionBank Payout Log:', $log);
  }
}