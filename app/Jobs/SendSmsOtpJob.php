<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;

class SendSmsOtpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $to;
    public $message;
    public $entity_id;
    public $template_id;

    /**
     * Create a new job instance.
     */
    public function __construct($to, $message, $entity_id, $template_id = null)
    {
        $this->to = $to;
        $this->message = $message;
        $this->entity_id = $entity_id;
        $this->template_id = $template_id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $url       = config('services.sms.url');
        $payload   = [
            'username'   => config('services.sms.username'),
            'dest'       => $this->to,
            'apikey'     => config('services.sms.api_key'),
            'signature'  => config('services.sms.sender'),
            'msgtype'    => 'PM',
            'msgtxt'     => $this->message,
            'entityid'   => $this->entity_id,
            'templateid' => $this->template_id,
        ];

        try {
            $response = Http::timeout(30)->get($url, $payload);

            // Write structured logs
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
                    'to'       => $this->to,
                    'status'   => $response->status(),
                    'response' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('SMS Job Exception', [
                'to'      => $this->to,
                'message' => $this->message,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Structured log writer
     */
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
            'service'       => $service,
            'api'           => $api,
            'url'           => $url,
            'ip'            => request()->ip(),
            'datetime'      => now()->toDateTimeString(),
            'headers'       => $headers,
            'payload'       => $requestPayload,
            'http_code'     => $responseCode,
            'response_data' => $responseData,
        ];

        Log::channel('sms')->info('SMS Log', $log);
    }
}