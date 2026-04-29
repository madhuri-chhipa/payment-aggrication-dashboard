<?php

namespace App\Http\Controllers\user;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\BbpsBillTransaction;
use App\Models\BbpsComplaint;
use App\Services\ComplaintService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Services\SmsService;

class ComplaintController extends Controller
{
  protected $complaintService;
  protected $smsService;

  public function __construct(ComplaintService $complaintService, SmsService $smsService)
  {
    $this->complaintService = $complaintService;
    $this->smsService = $smsService;
  }

  public function create()
  {
    $transactions = BbpsBillTransaction::where('user_id', Auth::id())
      ->whereNotNull('bbps_txn_ref_id')
      ->orderBy('id', 'desc')
      ->get();
    return view('user.services.complaint_create', compact('transactions'));
  }

  /**
   * Register Complaint
   */
  public function register(Request $request)
  {
    try {
      $request->validate([
        'txnRefId' => ['required', 'string'],
        'mobileNumber' => ['required', 'string'],
        'complaintType' => ['required', 'in:Service,Transaction'],
        'complaintDesc' => ['required', 'max:255'],
        'servReason' => ['nullable', 'max:255'],
        'complaintDisposition' => ['nullable', 'max:255'],
        'otherDisposition' => ['nullable', 'max:255'],
      ]);
      $userId = Auth::id();
      $mobileNumber = $request->mobileNumber;
      $txnRefId = $request->txnRefId;
      $billerId = BbpsBillTransaction::where('bbps_txn_ref_id', $request->txnRefId)->first()->biller_id;
      $agentId = config('services.bbps.agent_id');
      $participationType = 'BILLER';
      $disposition = $request->complaintDisposition;
      if ($request->complaintDisposition === 'Others') {
        $disposition = $request->otherDisposition;
      }
      $data = [
        'complaintType' => $request->complaintType,
        'participationType' => $request->complaintType === 'Service' ? $participationType : '',
        'agentId' => $request->complaintType === 'Service' ? $agentId : '',
        'txnRefId' => $request->complaintType === 'Transaction' ? $txnRefId : '',
        'billerId' => $request->complaintType === 'Service' ? $billerId : '',
        'complaintDesc' => $request->complaintDesc,
        'servReason' => $request->complaintType === 'Service' ? $request->servReason : '',
        'complaintDisposition' => $request->complaintType === 'Transaction' ? $disposition : '',
      ];

      // 1️⃣ Call API
      $responseXml = $this->complaintService->registerComplaint($data);

      // 2️⃣ Parse XML (Same Pattern as billerInfo)
      $xml = simplexml_load_string($responseXml);

      if (!$xml) {
        return back()->with('error', 'Invalid API Response');
      }

      $responseCode   = (string) $xml->responseCode;
      $responseReason = (string) $xml->responseReason;
      $complaintId    = (string) $xml->complaintId;
      $complaintAssigned = (string) $xml->complaintAssigned;
      $errorCode      = (string) ($xml->errorInfo->error->errorCode ?? '');
      $errorMessage   = (string) ($xml->errorInfo->error->errorMessage ?? '');
      $status = 'pending';
      if (!empty($errorCode)) {
        $status = 'failed';
      } elseif ($responseCode == '000') {
        $status = 'success';
      }
      // 3️⃣ Save in DB
      BbpsComplaint::create([
        'txn_ref_id' => $txnRefId,
        'user_id' => $userId,
        'mobile_number' => $mobileNumber,
        'complaint_type' => $request->complaintType,
        'biller_id' => $data['billerId'],
        'agent_id' => $data['agentId'],
        'participation_type' => $data['participationType'],
        'serv_reason' => $data['servReason'],
        'complaint_disposition' => $data['complaintDisposition'],
        'complaint_desc' => $request->complaintDesc,
        'bbps_complaint_assigned' => $complaintAssigned,
        'bbps_complaint_id' => $complaintId,
        'response_code' => $responseCode,
        'response_reason' => $responseReason,
        'error_code' => $errorCode ?? '',
        'error_message' => $errorMessage ?? '',
        'status' => $status ??  '',
      ]);
      // ✅ Prepare SMS
      // $message = "Your complaint is successfully registered for Txn Ref ID {$txnRefId} with complaint ID {$complaintId}. You can track status using complaint ID. -Finsova";
      $complaintId = 'COM123';
      $txnRefId = 'CA1231';
      $templateId = config('services.sms.complaint_template_id');

      $message = "Your complaint has been registered successfully for B-Connect TXN ID {$txnRefId}. Your Complaint ID is {$complaintId}. You can track the status of your complaint using this Complaint ID. -Finsova";

      $this->smsService->sendSms($mobileNumber, $message, $templateId);

      // ✅ Send SMS

      return back()->with(
        'success',
        'Complaint registered successfully. Complaint ID: ' . $complaintId
      );
      // 4️⃣ Toaster Message Handling
      if ($responseCode === '000') {
        return back()->with(
          'success',
          'Complaint registered successfully. Complaint ID: ' . $complaintId
        );
      }

      // Duplicate Complaint Case
      if ($responseCode === '001' && $errorCode === 'TS001') {

        preg_match('/CC\d+/', $responseReason, $matches);
        $existingComplaintId = $matches[0] ?? null;

        return back()->with(
          'info',
          'Complaint already exists with ID: ' . $existingComplaintId
        );
      }

      return back()->with(
        'error',
        $errorMessage ?: 'Complaint registration failed.'
      );
    } catch (\Exception $e) {

      return back()->with('error', $e->getMessage());
    }
  }

  public function track(Request $request)
  {
    try {
      $complaintId   = $request->complaintId;
      $complaintType = $request->complaintType;
      $data = [
        'complaintId' => $complaintId,
        'complaintType' => $complaintType
      ];
      $complaint = BbpsComplaint::where('bbps_complaint_id', $complaintId)->first();
      if (!$complaint) {
        return response()->json([
          'status' => false,
          'message' => 'Complaint ID not found.'
        ]);
      }
      $responseXml = $this->complaintService->trackComplaint($data);
      $xml = simplexml_load_string($responseXml);
      if (!$xml) {
        return response()->json([
          'status' => false,
          'message' => 'Invalid API Response'
        ]);
      }
      $responseCode   = (string) $xml->responseCode;
      $responseReason = (string) $xml->responseReason;
      $complaintStatus = (string) $xml->complaintStatus;
      $complaintRemarks = (string) $xml->complaintRemarks;

      $errorCode      = (string) ($xml->errorInfo->error->errorCode ?? '');
      $errorMessage   = (string) ($xml->errorInfo->error->errorMessage ?? '');

      // ❌ If API response code not success
      if ($responseCode !== '000') {

        $complaint->update([
          'response_code' => $responseCode,
          'response_reason' => $responseReason,
          'error_code' => $errorCode,
          'error_message' => $errorMessage,
          'status' => 'failed'
        ]);

        return response()->json([
          'status' => false,
          'message' => $errorMessage ?: $responseReason ?: 'Complaint tracking failed'
        ]);
      }

      // Determine complaint status
      $status = 'pending';

      if ($complaintStatus === 'RESOLVED') {
        $status = 'success';
      } elseif ($complaintStatus === 'ASSIGNED') {
        $status = 'processing';
      }

      // Update DB
      $complaint->update([
        'response_code' => $responseCode,
        'response_reason' => $responseReason,
        'error_code' => $errorCode,
        'error_message' => $errorMessage,
        'status' => $status
      ]);

      return response()->json([
        'status' => true,
        'message' => 'Complaint status fetched successfully',
        'data' => [
          'complaint_id' => (string)$xml->complaintId,
          'status' => $complaintStatus,
          'assigned' => (string)$xml->complaintAssigned,
          'remarks' => $complaintRemarks,
          'code' => $responseCode,
          'reason' => $responseReason
        ]
      ]);
    } catch (\Exception $e) {

      return response()->json([
        'status' => false,
        'message' => $e->getMessage()
      ]);
    }
  }
}
