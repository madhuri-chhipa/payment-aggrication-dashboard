<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\BbpsComplaint;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class ComplaintReportController extends Controller
{
  public function complaintLog()
  {
    return view('user.reports.complaint_logs');
  }

  public function getComplaintLogs(Request $request)
  {
    $query = BbpsComplaint::query()
      ->where('user_id', Auth::id())
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
                        <a class="dropdown-item"
                          href="' . route('complaint.track.page') . '">
                          <i class="fa fa-rotate text-info me-1"></i>
                          Track Status
                        </a>
                      </li>
                    </ul>
                </div>';
      })

      ->rawColumns(['status', 'actions'])
      ->toJson();
  }

  public function showAjax($id)
  {
    return response()->json(
      BbpsComplaint::findOrFail($id)
    );
  }
}
