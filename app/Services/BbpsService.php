<?php

namespace App\Services;

use App\Models\BbpsBiller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\BbpsBillTransaction;
use Illuminate\Support\Facades\Auth;

class BbpsService
{
  private $workingKey;
  private $accessCode;
  private $instituteId;
  private $baseUrl;
  private $billerInfoApiurl;
  private $planPullApiUrl;
  private $billValidationApiUrl;
  private $billFetchApiUrl;
  private $agentID;
  private $smsService;

  public function __construct(SmsService $smsService)
  {
    $this->workingKey  = config('services.bbps.working_key');
    $this->accessCode  = config('services.bbps.access_code');
    $this->instituteId = config('services.bbps.institute_id');
    $this->baseUrl     = config('services.bbps.base_url');
    $this->billerInfoApiurl = config('services.bbps.biller_info_api_url');
    $this->billFetchApiUrl  = config('services.bbps.bill_fetch_api_url');
    $this->planPullApiUrl  = config('services.bbps.plan_pull_api_url');
    $this->billValidationApiUrl  = config('services.bbps.bill_validation_api_url');
    $this->agentID     = config('services.bbps.agent_id');
    $this->smsService = $smsService;
  }
  public function billerInfo($billerId)
  {
    $startTime = microtime(true);
    $requestId = strtoupper(Str::random(35));

    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      . '<billerInfoRequest>';

    $xml .= '<billerId>' . $billerId . '</billerId>';

    $xml .= '</billerInfoRequest>';

    $encRequest = $this->encrypt($xml);

    $queryParams = [
      'accessCode' => $this->accessCode,
      'requestId' => $requestId,
      'ver' => '1.0',
      'instituteId' => $this->instituteId
    ];

    $url = $this->baseUrl . $this->billerInfoApiurl . '?' . http_build_query($queryParams);
    try {
      /** @var Response $response */
      $response = Http::withOptions(['verify' => false])
        ->withHeaders(['Content-Type' => 'application/json'])
        ->send('POST', $url, [
          'body' => urlencode($encRequest)
        ]);

      $statusCode = $response->status();
      $encryptedResponse = $response->body();
      $decryptedResponse = null;

      if ($response->ok()) {
        $decryptedResponse = $this->decrypt(trim($encryptedResponse));
      }

      $executionTime = round((microtime(true) - $startTime) * 1000, 2);
      $this->writeLogs(
        service: 'BBPS',
        api: 'BILLER INFO',
        url: $url,
        requestPayload: [
          'plain' => $xml,
          'encrypted' => $encRequest
        ],
        headers: null,
        responseCode: $statusCode,
        responseData: [
          'plain' => $decryptedResponse,
          'encrypted' => $encryptedResponse
        ],
        extra: [
          'request_id' => $requestId,
          'execution_time_ms' => $executionTime
        ]
      );

      if (!$response->ok()) {
        throw new \Exception("API Connection Failed: " . $statusCode);
      }

      return $decryptedResponse;
    } catch (\Exception $e) {
      $executionTime = round((microtime(true) - $startTime) * 1000, 2);
      $this->writeLogs(
        service: 'BBPS',
        api: 'BILLER INFO',
        url: $url,
        requestPayload: [
          'plain' => $xml,
          'encrypted' => $encRequest
        ],
        headers: null,
        responseCode: 'FAILED',
        responseData: [
          'plain' => $decryptedResponse,
          'encrypted' => $encryptedResponse
        ],
        extra: [
          'request_id' => $requestId,
          'execution_time_ms' => $executionTime
        ]
      );
      throw $e;
    }
  }


  public function billFetch(string $billerId, array $inputs, array $customer, array $device): array
  {
    $requestId = $this->makeRequestId();

    $biller = BbpsBiller::query()
      ->select(['biller_id', 'customer_params'])
      ->where('biller_id', $billerId)
      ->firstOrFail();

    $schema = $this->decodeSchema($biller->customer_params);

    $inputs = $this->normalizeInputs($inputs);
    $this->validateInputs($schema, $inputs);

    $mobile = trim((string) ($customer['mobile'] ?? ''));
    if ($mobile === '') {
      throw new \InvalidArgumentException('customer.mobile is required');
    }

    $json = $this->buildBillFetchJson(
      agentId: (string) config('services.bbps.agent_id'),
      device: [
        'ip' => (string)($device['ip'] ?? $this->clientIpv4()), // MUST be IPv4
        'initChannel' => (string)($device['initChannel'] ?? 'AGT'),
        'mac' => (string)($device['mac'] ?? '01-23-45-67-89-ab'),
      ],
      customer: [
        'mobile' => $mobile,
        'email' => (string)($customer['email'] ?? ''),
        'aadhaar' => (string)($customer['aadhaar'] ?? ''),
        'pan' => (string)($customer['pan'] ?? ''),
      ],
      billerId: $billerId,
      billerAdhoc: (bool)($biller->biller_adhoc ?? false),
      schema: $schema,
      inputs: $inputs
    );

    $encRequest = $this->encrypt($json);
    $url = $this->billFetchUrl($requestId, $encRequest);
    $startedAt = microtime(true);
    /** @var Response $response */
    $response = Http::withOptions(['verify' => false])
      ->send('POST', $url);
    $statusCode = $response->status();
    $encrypted = (string) $response->body();
    $decrypted = $response->ok() ? (string) $this->decrypt(trim($encrypted)) : '';
    $this->writeLogs(
      service: 'BBPS',
      api: 'BILL FETCH',
      url: $url,
      requestPayload: [
        'plain' => $json,
        'encrypted' => $encRequest
      ],
      headers: null,
      responseCode: $statusCode,
      responseData: [
        'plain' => $decrypted,
        'encrypted' => $encrypted
      ],
      extra: [
        'request_id' => $requestId,
        'execution_time_ms' => 0
      ]
    );
    if (!$response->ok()) {
      throw new \RuntimeException('BillFetch API failed with HTTP ' . $response->status());
    }
    $decryptedArr = json_decode(trim($decrypted), true);

    if (!is_array($decryptedArr)) {
      throw new \RuntimeException('Invalid decrypted response');
    }

    return [
      'requestId' => $requestId,
      'bbps' => $decryptedArr,
    ];
  }
  public function billPayNoFetch(
    string $billerId,
    array $inputs,
    array $customer,
    string $amount, // rupees from UI
    array $paymentMethod,
    array $paymentInfo,
    array $device = []
  ): array {
    $requestId = $this->makeRequestId();

    // --- Load biller + schema
    $biller = BbpsBiller::query()
      ->select(['biller_name', 'category', 'biller_id', 'customer_params', 'biller_adhoc'])
      ->where('biller_id', $billerId)
      ->firstOrFail();

    $schema = $this->decodeSchema($biller->customer_params);
    $inputs = $this->normalizeInputs($inputs);
    $this->validateInputs($schema, $inputs);

    // --- Validate customer
    $mobile = trim((string)($customer['mobile'] ?? ''));
    if ($mobile === '') {
      throw new \InvalidArgumentException('customer.mobile is required');
    }

    $remitterName = trim((string)($customer['name'] ?? ''));
    // --- Amount: rupees -> paise
    $amountPaise = $this->rupeesToPaise($amount);

    // --- Resolve device (AGT)
    $deviceResolved = [
      'initChannel' => (string)($device['initChannel'] ?? 'AGT'),
      'ip' => (string)($device['ip'] ?? $this->clientIpv4()),
      'mac' => (string)($device['mac'] ?? '01-23-45-67-89-ab'),
    ];
    $paymentMode = trim((string)($paymentMethod['paymentMode'] ?? ''));
    if ($paymentMode === '') {
      throw new \InvalidArgumentException('paymentMethod.paymentMode is required');
    }
    $paymentMethodFinal = [
      'paymentMode' => $paymentMode,
      'quickPay' => (string)($paymentMethod['quickPay'] ?? 'Y'),
      'splitPay' => (string)($paymentMethod['splitPay'] ?? 'N'),
    ];
    $paymentRefId = trim((string)($paymentInfo['paymentRefId'] ?? $requestId));
    $txnReferenceId = trim((string)($paymentInfo['txnReferenceId'] ?? $paymentRefId));
    $paymentAccountInfo = $this->resolvePaymentAccountInfo($paymentMethodFinal, $paymentInfo);
    $paymentAccountInfo = (string) $paymentAccountInfo;
    $existingInfo = (array)($paymentInfo['info'] ?? []);
    $existingInfo = $this->normalizeInfoArray($existingInfo);
    $mandatoryRemitterInfo = [
      ['infoName' => 'Remitter Name', 'infoValue' => $remitterName],
      ['infoName' => 'Payment Account Info', 'infoValue' => $paymentAccountInfo],
      ['infoName' => 'Payment mode', 'infoValue' => $paymentMode], // note: "Payment mode" case
    ];
    $finalInfo = $this->mergeMandatoryPaymentInfo(
      existing: $existingInfo,
      mandatory: $mandatoryRemitterInfo
    );


    // --- Build BillPay payload (No Fetch flow)
    $payload = [
      'billerAdhoc' => (bool)($biller->biller_adhoc ?? false),
      'agentId' => (string) config('services.bbps.agent_id'),

      'agentDeviceInfo' => [
        'initChannel' => $deviceResolved['initChannel'],
        'ip' => $deviceResolved['ip'],
        'mac' => $deviceResolved['mac'],
      ],

      'customerInfo' => [
        'customerMobile' => $mobile,
        'customerEmail' => (string)($customer['email'] ?? ''),
        'customerAdhaar' => (string)($customer['aadhaar'] ?? ''),
        'customerPan' => (string)($customer['pan'] ?? ''),
      ],

      'billerId' => $billerId,

      'inputParams' => [
        'input' => $this->toInputArray($inputs),
      ],

      'additionalInfo' => [
        'info' => [],
      ],

      'amountInfo' => [
        'amount' => (string) $amountPaise, // paise
        'currency' => '356',
        'custConvFee' => '0',
      ],

      'paymentMethod' => $paymentMethodFinal,
      'paymentRefId' => $paymentRefId,
      'paymentInfo' => [
        'info' => $finalInfo,
      ],
    ];

    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

    $encRequest = $this->encrypt($json);
    $url = $this->billPayUrl($requestId, $encRequest);

    $startedAt = microtime(true);
    /** @var Response $response */
    $response = Http::withOptions(['verify' => false])->send('POST', $url);
    $statusCode = $response->status();
    $encrypted = (string) $response->body();
    $decrypted = $response->ok() ? (string) $this->decrypt(trim($encrypted)) : '';
    $this->writeLogs(
      service: 'BBPS',
      api: 'QUICK PAY',
      url: $url,
      requestPayload: [
        'plain' => $json,
        'encrypted' => $encRequest
      ],
      headers: null,
      responseCode: $statusCode,
      responseData: [
        'plain' => $decrypted,
        'encrypted' => $encrypted
      ],
      extra: [
        'request_id' => $requestId,
        'execution_time_ms' => 0
      ]
    );

    if (!$response->ok()) {
      throw new \RuntimeException('BillPay API failed with HTTP ' . $response->status());
    }

    $arr = json_decode(trim($decrypted), true);
    if (!is_array($arr)) {
      throw new \RuntimeException('Invalid decrypted BillPay response JSON');
    }
    $responseCode = (string)($arr['responseCode'] ?? '');
    if ($responseCode == '000') {
      // $templateId = config('services.sms.payment_template_id');
      $templateId = '1107170901191166616';
      $billerName = $biller->biller_name;
      $dateTime = now()->format('d M Y, h:i A');
      $paidAmountRupees = is_numeric($amount) ? ((float)$amount / 100) : 0;
      $message = "Thank you for payment of {$paidAmountRupees} against consumer no {$mobile}, Txn Ref ID {$txnReferenceId} on
{$dateTime} vide Cash. -Finsova";
      $this->smsService->sendSms('6376380550', $message, $templateId);
    }
    $status = match ($responseCode) {
      '000' => 'SUCCESS',
      '001', '01', 'PENDING' => 'PENDING',
      default => 'FAILED',
    };

    $paidAmountRupees = is_numeric($amount) ? ((float)$amount / 100) : 0;
    $billAmountRupees = is_numeric($arr['respAmount'] ?? null) ? ((float)$arr['respAmount'] / 100) : 0;

    $txn = BbpsBillTransaction::create([
      'user_id' => Auth::id(),
      'biller_id' => $billerId,
      'category' => $biller->category ?? null,
      'biller_name' => $biller->biller_name ?? $billerId,
      'request_id' => $requestId,
      'amount' => $paidAmountRupees,
      'bill_params' => json_encode($inputs, JSON_UNESCAPED_SLASHES),
      'response_code' => $arr['responseCode'] ?? null,
      'response_reason' => $arr['responseReason'] ?? null,
      'bbps_txn_ref_id' => $arr['txnRefId'] ?? null,
      'approval_ref_number' => $arr['approvalRefNumber'] ?? null,
      'customer_name' => $arr['respCustomerName'] ?? $remitterName,
      'customer_email' => $customer['email'] ?? null,
      'customer_mobile' => $mobile,
      'bill_amount' => $billAmountRupees,
      'status' => $status,
      'api_request' => json_encode($payload, JSON_UNESCAPED_SLASHES),
      'api_response' => json_encode($arr, JSON_UNESCAPED_SLASHES),
    ]);

    return [
      'requestId' => $requestId,
      'bbps' => $arr,
      'paymentRefId' => $paymentRefId,
      'txnReferenceId' => $txnReferenceId,
      'amountPaise' => $amount,
      'transaction_id' => $txn->id,
      'status' => $status,
      'receipt' => [
        'biller_name' => $biller->category ?? $billerId,
        'customer_name' => $arr['respCustomerName'] ?? $remitterName,
        'mobile' => $mobile,
        'bill_number' => $arr['respBillNumber'] ?? ($br['billNumber'] ?? ''),
        'bill_date' => $arr['respBillDate'] ?? ($br['billDate'] ?? ''),
        'due_date' => $arr['respDueDate'] ?? ($br['dueDate'] ?? ''),
        'bbps_txn_ref_id' => $arr['txnRefId'] ?? '',
        'approval_ref_number' => $arr['approvalRefNumber'] ?? '',
        'payment_mode' => $paymentMode,
        'payment_channel' => $paymentMode . ' (Logged In)',
        'bill_amount' => number_format($billAmountRupees, 2, '.', ''),
        'cust_conv_fee' => number_format(((float)($arr['custConvFee'] ?? 0)) / 100, 2, '.', ''),
        'total_amount' => number_format($paidAmountRupees, 2, '.', ''),
        'transaction_datetime' => now()->format('d/m/Y H:i:s'),
        'status' => $status,
        'response_reason' => $arr['responseReason'] ?? '',
      ],
    ];
  }
  public function billPayWithFetch(
    string $billerId,
    string $requestID,
    array $inputs,
    array $customer,
    string $amount, // rupees from UI (final payable)
    array $paymentMethod,
    array $paymentInfo,
    array $billerResponse, // from fetch
    array $additionalInfo = [], // from fetch (optional)
    array $device = []
  ): array {
    $requestId = $requestID;

    // --- Load biller + schema
    $biller = BbpsBiller::query()
      ->select(['biller_name', 'category', 'biller_id', 'customer_params', 'biller_adhoc'])
      ->where('biller_id', $billerId)
      ->firstOrFail();

    $schema = $this->decodeSchema($biller->customer_params);
    $inputs = $this->normalizeInputs($inputs);
    $this->validateInputs($schema, $inputs);
    // --- Validate customer
    $mobile = trim((string)($customer['mobile'] ?? ''));
    if ($mobile === '') throw new \InvalidArgumentException('customer.mobile is required');
    $remitterName = $billerResponse['customerName'];
    // --- Resolve device
    $deviceResolved = [
      'initChannel' => (string)($device['initChannel'] ?? 'AGT'),
      'ip' => (string)($device['ip'] ?? $this->clientIpv4()),
      'mac' => (string)($device['mac'] ?? '01-23-45-67-89-ab'),
    ];

    $paymentMode = trim((string)($paymentMethod['paymentMode'] ?? ''));
    if ($paymentMode === '') throw new \InvalidArgumentException('paymentMethod.paymentMode is required');

    // Force PAY-WITH-FETCH (NOT QuickPay)
    $paymentMethodFinal = [
      'paymentMode' => $paymentMode,
      'quickPay' => 'N',
      'splitPay' => (string)($paymentMethod['splitPay'] ?? 'N'),
    ];

    $paymentRefId = trim((string)($paymentInfo['paymentRefId'] ?? $requestId));
    $txnReferenceId = trim((string)($paymentInfo['txnReferenceId'] ?? $paymentRefId));

    $paymentAccountInfo = $this->resolvePaymentAccountInfo($paymentMethodFinal, $paymentInfo);
    $existingInfo = (array)($paymentInfo['info'] ?? []);
    $existingInfo = $this->normalizeInfoArray($existingInfo);

    $mandatoryRemitterInfo = [
      ['infoName' => 'Remitter Name', 'infoValue' => $remitterName],
      ['infoName' => 'Payment Account Info', 'infoValue' => $paymentAccountInfo],
      ['infoName' => 'Payment mode', 'infoValue' => $paymentMode],
    ];

    $finalInfo = $this->mergeMandatoryPaymentInfo(
      existing: $existingInfo,
      mandatory: $mandatoryRemitterInfo
    );

    // --- Sanitize billerResponse: DO NOT send empty strings
    $br = $this->sanitizeBillerResponse($billerResponse);

    // billAmount is mandatory and must be numeric & not 0
    if (!isset($br['billAmount']) || !is_numeric((string)$br['billAmount']) || (float)$br['billAmount'] == 0.0) {
      throw new \InvalidArgumentException('billerResponse.billAmount must be numeric and not 0');
    }

    // --- Build BillPay payload (WITH FETCH)
    $payload = [
      'billerAdhoc' => (bool)($biller->biller_adhoc ?? false),
      'agentId' => (string) config('services.bbps.agent_id'),

      'agentDeviceInfo' => [
        'initChannel' => $deviceResolved['initChannel'],
        'ip' => $deviceResolved['ip'],
        'mac' => $deviceResolved['mac'],
      ],

      'customerInfo' => [
        'REMITTER_NAME' => $remitterName,
        'customerMobile' => $mobile,
        'customerEmail' => (string)($customer['email'] ?? ''),
        'customerAdhaar' => (string)($customer['aadhaar'] ?? ''),
        'customerPan' => (string)($customer['pan'] ?? ''),
      ],

      'billerId' => $billerId,

      'inputParams' => [
        'input' => $this->toInputArray($inputs),
      ],

      // include additionalInfo if you want (optional)
      'additionalInfo' => [
        'info' => $this->normalizeInfoArray($additionalInfo),
      ],

      // IMPORTANT: With fetch pay, you must send billerResponse
      'billerResponse' => $br,

      'amountInfo' => [
        'amount' => (string) $amount,
        'currency' => '356',
        'custConvFee' => '0',
      ],

      'paymentMethod' => $paymentMethodFinal,
      'paymentRefId' => $paymentRefId,
      'paymentInfo' => [
        'info' => $finalInfo,
      ],
    ];

    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

    $encRequest = $this->encrypt($json);
    $url = $this->billPayUrl($requestId, $encRequest);
    /** @var Response $response */
    $startedAt = microtime(true);
    /** @var Response $response */
    $response = Http::withOptions(['verify' => false])->send('POST', $url);
    $statusCode = $response->status();
    $encrypted = (string) $response->body();
    $decrypted = $response->ok() ? (string) $this->decrypt(trim($encrypted)) : '';
    $this->writeLogs(
      service: 'BBPS',
      api: 'BILL PAY WITH FETCH',
      url: $url,
      requestPayload: [
        'plain' => $json,
        'encrypted' => $encRequest
      ],
      headers: null,
      responseCode: $statusCode,
      responseData: [
        'plain' => $decrypted,
        'encrypted' => $encrypted
      ],
      extra: [
        'request_id' => $requestId,
        'execution_time_ms' => 0
      ]
    );
    if (!$response->ok()) {
      throw new \RuntimeException('BillPay API failed with HTTP ' . $response->status());
    }
    $arr = json_decode(trim($decrypted), true);
    if (!is_array($arr)) {
      throw new \RuntimeException('Invalid decrypted BillPay response JSON');
    }

    $responseCode = (string)($arr['responseCode'] ?? '');
    if ($responseCode == '000') {
      // $templateId = config('services.sms.payment_template_id');
      $templateId = '1107170901191166616';
      $billerName = $biller->biller_name;
      $dateTime = now()->format('d M Y, h:i A');
      $paidAmountRupees = is_numeric($amount) ? ((float)$amount / 100) : 0;
      $message = "Thank you for payment of {$paidAmountRupees} against consumer no {$mobile}, B-Connect TXN ID
{$txnReferenceId} on
{$dateTime} vide Cash. -Finsova";
      $this->smsService->sendSms('7023539859', $message, $templateId);
    }
    $status = match ($responseCode) {
      '000' => 'SUCCESS',
      '001', '01', 'PENDING' => 'PENDING',
      default => 'FAILED',
    };

    // amount and bill_amount stored in rupees
    $paidAmountRupees = is_numeric($amount) ? ((float)$amount / 100) : 0;
    $billAmountRupees = is_numeric($br['billAmount'] ?? null) ? ((float)$br['billAmount'] / 100) : 0;

    $txn = BbpsBillTransaction::create([
      'user_id' => Auth::id(),
      'biller_id' => $billerId,
      'category' => $biller->category ?? null,
      'biller_name' => $biller->biller_name ?? $billerId,
      'request_id' => $requestId,
      'amount' => $paidAmountRupees,
      'bill_params' => json_encode($inputs, JSON_UNESCAPED_SLASHES),
      'response_code' => $arr['responseCode'] ?? null,
      'response_reason' => $arr['responseReason'] ?? null,
      'bbps_txn_ref_id' => $arr['txnRefId'] ?? null,
      'approval_ref_number' => $arr['approvalRefNumber'] ?? null,
      'customer_name' => $arr['respCustomerName'] ?? $remitterName,
      'customer_email' => $customer['email'] ?? null,
      'customer_mobile' => $mobile,
      'bill_amount' => $billAmountRupees,
      'status' => $status,
      'api_request' => json_encode($payload, JSON_UNESCAPED_SLASHES),
      'api_response' => json_encode($arr, JSON_UNESCAPED_SLASHES),
    ]);

    return [
      'requestId' => $requestId,
      'bbps' => $arr,
      'paymentRefId' => $paymentRefId,
      'txnReferenceId' => $txnReferenceId,
      'amountPaise' => $amount,
      'transaction_id' => $txn->id,
      'status' => $status,
      'receipt' => [
        'biller_name' => $biller->category ?? $billerId,
        'customer_name' => $arr['respCustomerName'] ?? $remitterName,
        'mobile' => $mobile,
        'bill_number' => $arr['respBillNumber'] ?? ($br['billNumber'] ?? ''),
        'bill_date' => $arr['respBillDate'] ?? ($br['billDate'] ?? ''),
        'due_date' => $arr['respDueDate'] ?? ($br['dueDate'] ?? ''),
        'bbps_txn_ref_id' => $arr['txnRefId'] ?? '',
        'approval_ref_number' => $arr['approvalRefNumber'] ?? '',
        'payment_mode' => $paymentMode,
        'payment_channel' => $paymentMode . ' (Logged In)',
        'bill_amount' => number_format($billAmountRupees, 2, '.', ''),
        'cust_conv_fee' => number_format(((float)($arr['custConvFee'] ?? 0)) / 100, 2, '.', ''),
        'total_amount' => number_format($paidAmountRupees, 2, '.', ''),
        'transaction_datetime' => now()->format('d/m/Y H:i:s'),
        'status' => $status,
        'response_reason' => $arr['responseReason'] ?? '',
      ],
    ];
  }

  public function planPull(string $billerId): string
  {
    $startTime = microtime(true);
    $requestId = $this->makeRequestId();

    $xml = '
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      . '<planDetailsRequest>';

    $xml .= '<billerId>' . $billerId . '</billerId>';

    $xml .= '</planDetailsRequest>';

    $encRequest = $this->encrypt($xml);

    $queryParams = [
      'accessCode' => $this->accessCode,
      'requestId' => $requestId,
      'ver' => '1.0',
      'instituteId' => $this->instituteId
    ];

    $url = $this->baseUrl . $this->planPullApiUrl . '?' . http_build_query($queryParams);
    try {
      /** @var Response $response */
      $response = Http::withOptions(['verify' => false])
        ->withHeaders(['Content-Type' => 'application/json'])
        ->send('POST', $url, [
          'body' => urlencode($encRequest)
        ]);

      $statusCode = $response->status();
      $encryptedResponse = $response->body();
      $decryptedResponse = null;

      if ($response->ok()) {
        $decryptedResponse = $this->decrypt(trim($encryptedResponse));
      }

      $executionTime = round((microtime(true) - $startTime) * 1000, 2);
      $this->writeLogs(
        service: 'BBPS',
        api: 'PLAN PULL',
        url: $url,
        requestPayload: [
          'plain' => $xml,
          'encrypted' => $encRequest
        ],
        headers: null,
        responseCode: $statusCode,
        responseData: [
          'plain' => $decryptedResponse,
          'encrypted' => $encryptedResponse
        ],
        extra: [
          'request_id' => $requestId,
          'execution_time_ms' => $executionTime
        ]
      );
      if (!$response->ok()) {
        throw new \Exception("API Connection Failed: " . $statusCode);
      }

      return $decryptedResponse;
    } catch (\Exception $e) {

      $executionTime = round((microtime(true) - $startTime) * 1000, 2);
      $this->writeLogs(
        service: 'BBPS',
        api: 'PLAN PULL',
        url: $url,
        requestPayload: [
          'plain' => $xml,
          'encrypted' => $encRequest
        ],
        headers: null,
        responseCode: $statusCode,
        responseData: [
          'plain' => $e->getMessage(),
          'encrypted' => null
        ],
        extra: [
          'request_id' => $requestId,
          'execution_time_ms' => $executionTime
        ]
      );

      throw $e;
    }
  }

  public function billValidation(string $billerId, array $params, array $device = []): string
  {
    $startTime = microtime(true);
    $requestId = $this->makeRequestId();

    $inputArray = [];
    foreach ($params as $param) {
      $paramName = trim((string)($param['name'] ?? ''));
      $paramValue = $param['value'] ?? '';

      if ($paramName === '') {
        continue;
      }

      if (is_array($paramValue) || is_object($paramValue)) {
        $paramValue = json_encode($paramValue, JSON_UNESCAPED_SLASHES);
      }

      $inputArray[] = [
        'paramName' => $paramName,
        'paramValue' => trim((string)$paramValue),
      ];
    }

    $payload = [
      'agentId' => $this->agentID,
      'agentDeviceInfo' => [
        'ip' => (string)($device['ip'] ?? $this->clientIpv4()),
        'initChannel' => (string)($device['initChannel'] ?? 'AGT'),
        'mac' => (string)($device['mac'] ?? '01-23-45-67-89-ab'),
      ],
      'billerId' => $billerId,
      'inputParams' => [
        'input' => $inputArray,
      ],
    ];

    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $encRequest = $this->encrypt($json);
    $queryParams = [
      'accessCode' => $this->accessCode,
      'requestId' => $requestId,
      'ver' => '1.0',
      'instituteId' => $this->instituteId,
      'encRequest' => $encRequest,
    ];

    $url = $this->baseUrl . '/billpay/extBillValCntrl/billValidationRequest/json?' . http_build_query($queryParams);

    try {
      $response = Http::withOptions(['verify' => false])->send('POST', $url);
      /** @var \Illuminate\Http\Client\Response $response */
      $statusCode = $response->status();
      $encryptedResponse = (string) $response->body();
      $decryptedResponse = null;

      if ($response->ok()) {
        $decryptedResponse = $this->decrypt(trim($encryptedResponse));
      }

      $executionTime = round((microtime(true) - $startTime) * 1000, 2);
      $this->writeLogs(
        service: 'BBPS',
        api: 'BILL VAILD',
        url: $url,
        requestPayload: [
          'plain' => $json,
          'encrypted' => $encRequest
        ],
        headers: null,
        responseCode: $statusCode,
        responseData: [
          'plain' => $decryptedResponse,
          'encrypted' => $encryptedResponse
        ],
        extra: [
          'request_id' => $requestId,
          'execution_time_ms' => $executionTime
        ]
      );
      if (!$response->ok()) {
        throw new \Exception('API Connection Failed: ' . $statusCode);
      }

      return (string) $decryptedResponse;
    } catch (\Exception $e) {
      Log::channel('bbps')->error('BBPS BILL VALIDATION JSON API ERROR', [
        'request_id' => $requestId,
        'url' => $url,
        'request_json' => $json ?? null,
        'error_message' => $e->getMessage(),
      ]);

      throw $e;
    }
  }

  public function transactionStatus(array $data)
  {
    $startTime = microtime(true);
    $requestId = $this->makeRequestId();
    // build xml
    $xml = $this->buildTxnStatusXml($data);
    // encrypt
    $encRequest = $this->encrypt($xml);

    $queryParams = [
      'accessCode' => $this->accessCode,
      'encRequest' => $encRequest,
      'requestId' => $requestId,
      'ver' => '1.0',
      'instituteId' => $this->instituteId
    ];

    $url = $this->baseUrl . '/billpay/transactionStatus/fetchInfo/xml?' . http_build_query($queryParams);

    try {

      $response = Http::withOptions(['verify' => false])
        ->withHeaders(['Content-Type' => 'application/json'])
        ->send('GET', $url, []);
      /** @var Response $response */
      $statusCode = $response->status();
      $encryptedResponse = $response->body();
      $decryptedResponse = null;

      if ($response->ok()) {
        $decryptedResponse = $this->decrypt(trim($encryptedResponse));
      }

      Log::channel('bbps')->info('BBPS TXN STATUS API LOG', [
        'request_id' => $requestId,
        'url' => $url,
        'request_xml' => $xml,
        'encrypted_request' => $encRequest,
        'http_status' => $statusCode,
        'encrypted_response' => $encryptedResponse,
        'decrypted_response' => $decryptedResponse
      ]);

      if (!$response->ok()) {
        throw new \Exception("API Connection Failed: " . $statusCode);
      }

      return $decryptedResponse;
    } catch (\Exception $e) {

      Log::channel('bbps')->error('BBPS TXN STATUS API ERROR', [
        'request_id' => $requestId,
        'error_message' => $e->getMessage()
      ]);

      throw $e;
    }
  }
  private function buildTxnStatusXml($data)
  {
    $xml = '
<?xml version="1.0" encoding="UTF-8"?>
<transactionStatusReq>
  <trackType>' . $data['trackType'] . '</trackType>
  <trackValue>' . $data['trackValue'] . '</trackValue>';

    if ($data['trackType'] === 'MOBILE_NO') {
      $xml .= '
  <fromDate>' . $data['fromDate'] . '</fromDate>
  <toDate>' . $data['toDate'] . '</toDate>';
    }

    $xml .= '
</transactionStatusReq>';

    return $xml;
  }
  public function transactionStatusd($trackType, $trackValue, $fromDate = null, $toDate = null)
  {
    $startTime = microtime(true);

    $requestId = strtoupper(Str::random(35));

    $xml = '
<?xml version="1.0" encoding="UTF-8"?>';
    $xml .= '<transactionStatusReq>';
    $xml .= '<trackType>' . $trackType . '</trackType>';
    $xml .= '<trackValue>' . $trackValue . '</trackValue>';

    if ($trackType == 'MOBILE_NO') {
      $xml .= '<fromDate>' . $fromDate . '</fromDate>';
      $xml .= '<toDate>' . $toDate . '</toDate>';
    }

    $xml .= '</transactionStatusReq>';

    $encRequest = $this->encrypt($xml);

    $queryParams = [
      'accessCode' => $this->accessCode
    ];

    $url = $this->baseUrl . '/billpay/transactionStatus/fetchInfo/xml?' . http_build_query($queryParams);

    try {

      $response = Http::withOptions(['verify' => false])
        ->withHeaders([
          'Content-Type' => 'application/json'
        ])
        ->send('POST', $url, [
          'body' => urlencode($encRequest)
        ]);
      /** @var Response $response */
      $encryptedResponse = $response->body();

      $decryptedResponse = null;

      if ($response->ok()) {
        $decryptedResponse = $this->decrypt(trim($encryptedResponse));
      }

      Log::channel('bbps')->info('BBPS TXN STATUS API LOG', [
        'request_id' => $requestId,
        'url' => $url,
        'request_xml' => $xml,
        'encrypted_request' => $encRequest,
        'encrypted_response' => $encryptedResponse,
        'decrypted_response' => $decryptedResponse
      ]);

      if (!$response->ok()) {
        throw new \Exception("API Connection Failed");
      }

      return $decryptedResponse;
    } catch (\Exception $e) {

      Log::channel('bbps')->error('BBPS TXN STATUS API ERROR', [
        'request_xml' => $xml,
        'error' => $e->getMessage()
      ]);

      throw $e;
    }
  }
  /**
   * Remove empty keys so validator doesn't fail on ""
   */
  private function sanitizeBillerResponse(array $br): array
  {
    $out = [];

    // keep only non-empty
    foreach (['customerName', 'billAmount', 'billNumber', 'billDate', 'dueDate', 'billPeriod', 'amountOptions'] as $k) {
      if (!array_key_exists($k, $br)) continue;

      $v = $br[$k];

      // allow amountOptions arrays
      if ($k === 'amountOptions' && is_array($v)) {
        $out[$k] = $v;
        continue;
      }

      // strings: skip empty
      $sv = trim((string)$v);
      if ($sv !== '') $out[$k] = $sv;
    }

    return $out;
  }
  /**
   * Convert inputs map into BBPS input array
   */
  private function toInputArray(array $inputs): array
  {
    $out = [];
    foreach ($inputs as $k => $v) {
      $k = trim((string)$k);
      $v = trim((string)$v);
      if ($k === '' || $v === '') continue;
      $out[] = ['paramName' => $k, 'paramValue' => $v];
    }
    return $out;
  }
  private function normalizeInfoArray(array $info): array
  {
    $out = [];
    foreach ($info as $row) {
      if (!is_array($row)) continue;
      $name = trim((string)($row['infoName'] ?? ''));
      if ($name === '') continue;
      $out[] = [
        'infoName' => $name,
        'infoValue' => (string)($row['infoValue'] ?? ''),
      ];
    }
    return $out;
  }

  /**
   * Merge mandatory info tags, overriding any existing duplicates (case-insensitive).
   */
  private function mergeMandatoryPaymentInfo(array $existing, array $mandatory): array
  {
    $map = [];
    foreach ($existing as $row) {
      $key = mb_strtolower((string)$row['infoName']);
      $map[$key] = $row;
    }
    foreach ($mandatory as $row) {
      $key = mb_strtolower((string)$row['infoName']);
      $map[$key] = $row; // override
    }
    // Keep order: mandatory first, then remaining non-mandatory
    $mandatoryKeys = array_map(fn($r) => mb_strtolower((string)$r['infoName']), $mandatory);

    $final = [];
    foreach ($mandatoryKeys as $k) {
      if (isset($map[$k])) $final[] = $map[$k];
      unset($map[$k]);
    }
    foreach ($map as $row) {
      $final[] = $row;
    }
    return $final;
  }

  /**
   * Derive "Payment Account Info" based on paymentMode and provided paymentInfo.info fields.
   * New tag is mandatory; IFSC not needed for PG flows.
   */
  private function resolvePaymentAccountInfo(array $paymentMethod, array $paymentInfo): string
  {
    $mode = (string)($paymentMethod['paymentMode'] ?? '');
    $info = (array)($paymentInfo['info'] ?? []);

    $kv = [];
    foreach ($info as $i) {
      if (!is_array($i)) continue;
      $n = (string)($i['infoName'] ?? '');
      if ($n === '') continue;
      $kv[$n] = (string)($i['infoValue'] ?? '');
    }

    switch ($mode) {
      case 'UPI':
        return $kv['VPA'] ?? $kv['UPI ID'] ?? 'UPI';

      case 'Credit Card':
      case 'Debit Card':
      case 'Prepaid Card':
        $card = $kv['CardNum'] ?? $kv['Card Number'] ?? '';
        if ($card !== '' && strlen($card) >= 4) {
          return 'XXXX-XXXX-XXXX-' . substr($card, -4);
        }
        return 'CARD';

      case 'Internet Banking':
        return $kv['BankName'] ?? $kv['Bank Name'] ?? 'NETBANKING';

      case 'Wallet':
        return $kv['WalletId'] ?? $kv['Wallet ID'] ?? 'WALLET';

      case 'Cash':
        return 'CASH';

      case 'IMPS':
      case 'NEFT':
      case 'USSD':
      case 'Bharat QR':
      default:
        return strtoupper($mode ?: 'UNKNOWN');
    }
  }
  private function rupeesToPaise(string|int|float $amount): int
  {
    $amountStr = trim((string)$amount);
    if ($amountStr === '' || !is_numeric($amountStr)) {
      throw new \InvalidArgumentException('Invalid amount');
    }
    $rupees = (float)$amountStr;
    if ($rupees <= 0) {
      throw new \InvalidArgumentException('Amount must be greater than 0');
    }
    return (int) round($rupees *
      100);
  }
  private function billFetchUrl(string $requestId, string $encRequest): string
  {
    $query = http_build_query([
      'accessCode' => $this->accessCode,
      'requestId' => $requestId,
      'ver' => '1.0',
      'instituteId' => $this->instituteId,
      'encRequest' => $encRequest,
    ]);
    return $this->baseUrl . '/billpay/extBillCntrl/billFetchRequest/json?' . $query;
  }
  private function billPayUrl(string $requestId, string $encRequest): string
  {
    $query = http_build_query([
      'accessCode' => $this->accessCode,
      'requestId' => $requestId,
      'ver' => '1.0',
      'instituteId' => $this->instituteId,
      'encRequest' => $encRequest,
    ]);
    return $this->baseUrl . '/billpay/extBillPayCntrl/billPayRequest/json?' . $query;
  }
  private function decodeSchema(?string $json): array
  {
    if (!$json) {
      return [];
    }

    $decoded = json_decode($json, true);

    if (!is_array($decoded)) {
      throw new \InvalidArgumentException('Invalid customer_params JSON');
    }

    return $decoded;
  }

  private function normalizeInputs(array $inputs): array
  {
    $out = [];
    foreach ($inputs as $k => $v) {
      $out[trim((string) $k)] = trim((string) $v);
    }
    return $out;
  }

  private function validateInputs(array $schema, array $inputs): void
  {
    foreach ($schema as $p) {
      $name = trim((string) ($p['paramName'] ?? ''));
      if ($name === '') {
        continue;
      }

      $mandatory = strtoupper(trim((string) ($p['mandatory'] ?? 'N'))) === 'Y';
      $visible = strtoupper(trim((string) ($p['visibility'] ?? 'Y'))) !== 'N';

      if (!$visible) {
        continue;
      }

      $value = $inputs[$name] ?? '';

      if ($mandatory && $value === '') {
        throw new \InvalidArgumentException("Missing mandatory param: {$name}");
      }

      if ($value === '') {
        continue;
      }

      $min = (int) ($p['minLength'] ?? 0);
      $max = (int) ($p['maxLength'] ?? 0);
      $len = strlen($value);

      if ($min > 0 && $len < $min) {
        throw new \InvalidArgumentException("{$name} must be at least {$min} characters");
      }
      if ($max > 0 && $len > $max) {
        throw new \InvalidArgumentException("{$name} must be at most {$max} characters");
      }

      $type = strtoupper(trim((string) ($p['dataType'] ?? '')));

      if ($type === 'NUMERIC' && !preg_match('/^\d+$/', $value)) {
        throw new \InvalidArgumentException("{$name} must be numeric");
      }
    }
  }
  private function buildBillFetchJson(
    string $agentId,
    array $device,
    array $customer,
    string $billerId,
    bool $billerAdhoc,
    array $schema,
    array $inputs
  ): string {
    $payload = [
      'agentId' => $agentId,
      'billerAdhoc' => $billerAdhoc, // must be true/false (case sensitive in XML spec; in JSON keep boolean)
      'agentDeviceInfo' => [
        'ip' => (string)($device['ip'] ?? ''),
        'initChannel' => (string)($device['initChannel'] ?? 'MOB'),
        'mac' => (string)($device['mac'] ?? ''),
      ],
      'customerInfo' => [
        'customerMobile' => (string)($customer['mobile'] ?? ''),
        'customerEmail' => (string)($customer['email'] ?? ''),
        'customerAdhaar' => (string)($customer['aadhaar'] ?? ''),
        'customerPan' => (string)($customer['pan'] ?? ''),
      ],
      'billerId' => $billerId,
      'inputParams' => [
        'input' => [],
      ],
    ];

    foreach ($schema as $p) {
      $name = trim((string)($p['paramName'] ?? ''));
      if ($name === '') continue;

      $visible = strtoupper(trim((string)($p['visibility'] ?? 'Y'))) !== 'N';
      if (!$visible) continue;

      $value = $inputs[$name] ?? '';
      if ($value === '') continue;

      $payload['inputParams']['input'][] = [
        'paramName' => $name,
        'paramValue' => $value,
      ];
    }

    // JSON must be clean (no leading whitespace issues like your XML had)
    return json_encode($payload, JSON_UNESCAPED_SLASHES);
  }
  private function makeRequestId(): string
  {
    $rand = strtoupper(Str::random(27));

    $now = now();
    $Y = substr($now->format('Y'), -1); // last digit of year
    $DDD = str_pad((string)$now->dayOfYear, 3, '0', STR_PAD_LEFT);
    $hhmm = $now->format('Hi');

    return $rand . $Y . $DDD . $hhmm; // total 35
  }
  private function clientIpv4(): string
  {
    $ip = request()->ip() ?? '';
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
      return $ip;
    }

    // try X-Forwarded-For first IPv4
    $xff = (string) request()->header('X-Forwarded-For', '');
    foreach (array_map('trim', explode(',', $xff)) as $candidate) {
      if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return $candidate;
      }
    }

    // fallback (must be IPv4 to satisfy their regex/length)
    return '127.0.0.1';
  }
  private function x(string $value): string
  {
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
  }
  private function encrypt($plainText)
  {
    $key = pack("H*", md5(trim($this->workingKey)));
    $iv = pack(
      "C*",
      0x00,
      0x01,
      0x02,
      0x03,
      0x04,
      0x05,
      0x06,
      0x07,
      0x08,
      0x09,
      0x0a,
      0x0b,
      0x0c,
      0x0d,
      0x0e,
      0x0f
    );
    $encrypted = openssl_encrypt(
      $plainText,
      'AES-128-CBC',
      $key,
      OPENSSL_RAW_DATA,
      $iv
    );
    return bin2hex($encrypted);
  }
  private function decrypt($encryptedText)
  {
    $key = pack("H*", md5(trim($this->workingKey)));

    $iv = pack(
      "C*",
      0x00,
      0x01,
      0x02,
      0x03,
      0x04,
      0x05,
      0x06,
      0x07,
      0x08,
      0x09,
      0x0a,
      0x0b,
      0x0c,
      0x0d,
      0x0e,
      0x0f
    );

    $decoded = hex2bin(trim($encryptedText));

    if ($decoded === false) {
      return false;
    }

    return openssl_decrypt(
      $decoded,
      'AES-128-CBC',
      $key,
      OPENSSL_RAW_DATA,
      $iv
    );
  }
  private function writeLogs(
    string $service,
    string $api,
    string $url,
    $requestPayload = null,
    $headers = null,
    $responseCode = null,
    $responseData = null,
    $extra = []
  ) {
    $log = [
      'service' => $service,
      'api' => $api,
      'url' => $url,
      'ip' => request()->ip(),
      'datetime' => now()->toDateTimeString(),
      'headers' => $headers,
      'request' => $requestPayload['plain'] ?? null,
      'encrypted_request' => $requestPayload['encrypted'] ?? null,
      'http_status' => $responseCode,
      'response' => $responseData['plain'] ?? null,
      'encrypted_response' => $responseData['encrypted'] ?? null,
    ];
    $log = array_merge($log, $extra);
    Log::channel('bbps')->info("{$service} {$api} LOG", $log);
  }
}
