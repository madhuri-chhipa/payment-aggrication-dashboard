<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ComplaintService
{
    private $workingKey;
    private $baseUrl;
    private $complaintRegisterApiurl;
    private $complaintTrackApiurl;
    private $accessCode;
    private $instituteId;

    public function __construct()
    {
        $this->baseUrl     = config('services.bbps.base_url');
        $this->complaintRegisterApiurl = config('services.bbps.complaint_register_api_url');
        $this->complaintTrackApiurl = config('services.bbps.complaint_track_api_url');
        $this->workingKey  = config('services.bbps.working_key');
        $this->accessCode  = config('services.bbps.access_code');
        $this->instituteId = config('services.bbps.institute_id');
    }

    /**
     * Register Complaint (BBPS)
     */
    public function registerComplaint(array $data)
    {
        $startTime = microtime(true);
        $requestId = $this->makeRequestId();

        // 1️⃣ Build XML
        $xml = $this->buildRegistrationXml($data);
        // 2️⃣ Encrypt XML
        $encRequest = $this->encrypt($xml);

        // 3️⃣ Query Params
        $queryParams = [
            'accessCode'  => $this->accessCode,
            'encRequest' => $encRequest,
            'requestId'   => $requestId,
            'ver'         => '1.0',
            'instituteId' => $this->instituteId
        ];

        $url = $this->baseUrl . $this->complaintRegisterApiurl . '?' . http_build_query($queryParams);

        try {
            $response = Http::withOptions(['verify' => false])
                ->withHeaders(['Content-Type' => 'application/json'])
                ->send('POST', $url, []);

            $statusCode = $response->status();
            $encryptedResponse = $response->body();
            $decryptedResponse = null;

            if ($response->ok()) {
                $decryptedResponse = $this->decrypt(trim($encryptedResponse));
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            // ✅ SINGLE STRUCTURED LOG
            Log::channel('bbps')->info('BBPS COMPLAINT REGISTER API LOG', [
                'request_id' => $requestId,
                'url' => $url,
                'request_xml' => $xml,
                'encrypted_request' => $encRequest,
                'http_status' => $statusCode,
                'encrypted_response' => $encryptedResponse,
                'decrypted_response' => $decryptedResponse,
                'execution_time_ms' => $executionTime
            ]);

            if (!$response->ok()) {
                throw new \Exception("API Connection Failed: " . $statusCode);
            }

            return $decryptedResponse;
        } catch (\Exception $e) {

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::channel('bbps')->error('BBPS COMPLAINT REGISTER API LOG', [
                'request_id' => $requestId,
                'url' => $url,
                'request_xml' => $xml,
                'encrypted_request' => $encRequest,
                'http_status' => 'FAILED',
                'error_message' => $e->getMessage(),
                'execution_time_ms' => $executionTime
            ]);

            throw $e;
        }
    }
    /**
     * Track Complaint
     */
    public function trackComplaint(array $data)
    {
        $startTime = microtime(true);
        $requestId = $this->makeRequestId();

        // 1️⃣ Build XML
        $xml = $this->buildTrackingXml($data);

        // 2️⃣ Encrypt
        $encRequest = $this->encrypt($xml);

        // 3️⃣ Query Params (ver = 2.0 as per doc)
        $queryParams = [
            'accessCode'  => $this->accessCode,
            'encRequest'  => $encRequest,
            'requestId'   => $requestId,
            'ver'         => '1.0',
            'instituteId' => $this->instituteId
        ];

        $url = $this->baseUrl . $this->complaintTrackApiurl . '?' . http_build_query($queryParams);

        try {

            $response = Http::withOptions(['verify' => false])
                ->withHeaders(['Content-Type' => 'application/json'])
                ->send('GET', $url, []);

            $statusCode = $response->status();
            $encryptedResponse = $response->body();
            $decryptedResponse = null;

            if ($response->ok()) {
                $decryptedResponse = $this->decrypt(trim($encryptedResponse));
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::channel('bbps')->info('BBPS COMPLAINT TRACK API LOG', [
                'request_id' => $requestId,
                'url' => $url,
                'request_xml' => $xml,
                'encrypted_request' => $encRequest,
                'http_status' => $statusCode,
                'encrypted_response' => $encryptedResponse,
                'decrypted_response' => $decryptedResponse,
                'execution_time_ms' => $executionTime
            ]);

            if (!$response->ok()) {
                throw new \Exception("API Connection Failed: " . $statusCode);
            }

            return $decryptedResponse;
        } catch (\Exception $e) {

            Log::channel('bbps')->error('BBPS COMPLAINT TRACK API ERROR', [
                'request_id' => $requestId,
                'error_message' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Build Tracking XML
     */
    private function buildTrackingXml($data)
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <complaintTrackingReq>
            <complaintType>' . $data['complaintType'] . '</complaintType>
            <complaintId>' . $data['complaintId'] . '</complaintId>
        </complaintTrackingReq>';
    }

    /**
     * Parse XML Response
     */
    private function buildRegistrationXml($data)
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
        <complaintRegistrationReq>
            <complaintType>' . $data['complaintType'] . '</complaintType>
            <participationType>' . $data['participationType'] . '</participationType>
            <agentId>' . $data['agentId'] . '</agentId>
            <txnRefId>' . $data['txnRefId'] . '</txnRefId>
            <billerId>' . $data['billerId'] . '</billerId>
            <complaintDesc>' . $data['complaintDesc'] . '</complaintDesc>
            <servReason>' . $data['servReason'] . '</servReason>
            <complaintDisposition>' . $data['complaintDisposition'] . '</complaintDisposition>
        </complaintRegistrationReq>';
    }
    private function parseXmlResponse($xmlString)
    {
        $xml = simplexml_load_string($xmlString);

        if (!$xml) {
            return [
                'status' => false,
                'message' => 'Invalid XML Response'
            ];
        }

        return [
            'status' => ((string)$xml->responseCode === '000'),
            'responseCode' => (string)($xml->responseCode ?? $xml->complaintResponseCode),
            'responseReason' => (string)($xml->responseReason ?? $xml->complaintResponseReason),
            'complaintId' => (string)($xml->complaintId ?? ''),
            'assignedTo' => (string)($xml->complaintAssigned ?? ''),
            'raw' => $xmlString
        ];
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
}
