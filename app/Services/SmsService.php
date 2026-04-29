<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
  public function sendSms($to, $message, $templateId)
  {
    try {
      $url = config('services.sms.url');

      $payload = [
        'username'  => config('services.sms.username'),
        'dest'      => $to,
        'apikey'    => config('services.sms.api_key'),
        'signature' => config('services.sms.sender'),
        'msgtype'   => 'PM',
        'msgtxt'    => $message,
        'entityid'  => config('services.sms.entity_id'),
        'templateid' => $templateId,
      ];

      $response = Http::timeout(30)->get($url, $payload);
      /** @var Response $response */
      $this->writeLogs(
        'SMS',
        'SendMsg',
        $url,
        $payload,
        $response->headers(),
        $response->status(),
        $response->body()
      );

      if (! $response->successful()) {
        Log::warning('SMS sending failed', [
          'to'       => $to,
          'status'   => $response->status(),
          'response' => $response->body(),
        ]);

        return false;
      }

      return $response->body();
    } catch (\Throwable $e) {
      Log::error('SMS Exception', [
        'to'      => $to,
        'message' => $message,
        'error'   => $e->getMessage(),
      ]);

      return false;
    }
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

    Log::channel('sms')->info('SMS Log', $log);
  }
}
