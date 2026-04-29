<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\BbpsComplaint;
use App\Services\ComplaintService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class ComplaintReportController extends Controller
{
  protected $complaintService;

  public function __construct(ComplaintService $complaintService)
  {
    $this->complaintService = $complaintService;
  }
  public function complaintLog()
  {
    return view('admin.reports.complaint_logs');
  }

  public function getComplaintLogs(Request $request)
  {
    $query = BbpsComplaint::with('user')
      ->select([
        'id',
        'user_id',
        'mobile_number',
        'txn_ref_id',
        'complaint_type',
        'biller_id',
        'agent_id',
        'complaint_desc',
        'bbps_complaint_id',
        'response_code',
        'response_reason',
        'error_code',
        'error_message',
        'status',
        'created_at'
      ])

      /* 🔍 FILTERS */
      ->when($request->txn_ref_id, fn($q, $v) =>
      $q->where('txn_ref_id', 'like', "%{$v}%"))

      ->when($request->user, function ($q, $v) {
        $q->whereHas('user', function ($uq) use ($v) {
          $uq->where(function ($sub) use ($v) {
            $sub->where('company_name', 'like', "%{$v}%")
              ->orWhere('email', 'like', "%{$v}%")
              ->orWhere('mobile_number', 'like', "%{$v}%");
          });
        });
      })

      ->when($request->bbps_complaint_id, fn($q, $v) =>
      $q->where('bbps_complaint_id', 'like', "%{$v}%"))

      ->when($request->complaint_type, fn($q, $v) =>
      $q->where('complaint_type', $v))

      ->when($request->status, function ($q) use ($request) {

        if ($request->status == 'success') {
          $q->where('response_code', '000')
            ->whereNull('error_code');
        }

        if ($request->status == 'failed') {
          $q->whereNotNull('error_code');
        }

        if ($request->status == 'pending') {
          $q->whereNull('error_code')
            ->where(function ($query) {
              $query->whereNull('response_code')
                ->orWhere('response_code', '!=', '000');
            });
        }
      })

      ->when($request->from_date || $request->to_date, function ($q) use ($request) {
        if ($request->from_date && $request->to_date) {
          $q->whereBetween('created_at', [
            Carbon::parse($request->from_date)->startOfDay(),
            Carbon::parse($request->to_date)->endOfDay()
          ]);
        }
      });

    return DataTables::of($query)
      ->addIndexColumn()
      ->addColumn('user_info', function ($t) {
        if (!$t->user) return '-';
        return '
          <div>
            <strong>' . e($t->user->company_name) . '</strong><br>
            <small class="text-muted">' . e($t->user->email) . '</small><br>
            <small class="text-muted">' . e($t->user->mobile_number) . '</small>
          </div>
        ';
      })

      ->editColumn('complaint_desc', fn($c) =>
      Str::limit($c->complaint_desc, 50))

      ->editColumn('created_at', fn($c) =>
      $c->created_at
        ? $c->created_at->format('d/m/Y H:i:s')
        : '-')

      ->editColumn('status', function ($c) {

        if ($c->status == 'success') {
          return '<span class="badge bg-success">Success</span>';
        }

        if ($c->status == 'failed') {
          return '<span class="badge bg-danger">Failed</span>';
        }

        return '<span class="badge bg-warning">Pending</span>';
      })
      /* ⚙ ACTION DROPDOWN */
      ->addColumn('actions', function ($c) {
        return '
                <div class="dropdown">
                    <button class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                        Actions
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">

                        <li>
                            <a class="dropdown-item view-complaint"
                               href="javascript:void(0)"
                               data-id="' . $c->id . '">
                               <i class="fa fa-eye text-primary me-1"></i>
                               View
                            </a>
                        </li>

                        <li>
                          <a class="dropdown-item check-status"
                              href="javascript:void(0)"
                              data-id="' . $c->id . '">
                              <i class="fa fa-rotate text-info me-1"></i>
                              Track Status
                          </a>
                        </li>

                    </ul>
                </div>';
      })

      ->rawColumns(['user_info', 'status', 'actions'])
      ->toJson();
  }

  /**
   * Track Complaint
   */
  public function track($id)
  {
    try {
      $complaint = BbpsComplaint::findOrFail($id);
      if (!$complaint->bbps_complaint_id) {
        return response()->json([
          'status' => false,
          'message' => 'Complaint ID not found.'
        ]);
      }

      $data = [
        'complaintId' => $complaint->bbps_complaint_id,
        'complaintType' => $complaint->complaint_type
      ];

      // Call API
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
        'complaint_status' => $complaintStatus,
        'remarks' => $complaintRemarks,
        'message' => 'Status updated successfully'
      ]);
    } catch (\Exception $e) {
      return response()->json([
        'status' => false,
        'message' => $e->getMessage()
      ]);
    }
  }

  public function showAjax($id)
  {
    return response()->json(
      BbpsComplaint::with('user')->findOrFail($id)
    );
  }
}
