<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\PayinTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;


class PayinTransactionController extends Controller
{
  public function payinTxnLog()
  {
    return view('admin.reports.payin_transaction_logs');
  }

  public function getTransactionLogs(Request $request)
  {
    $admin = auth()->guard('admin')->user();
    $query = PayinTransaction::with(['user']) // relation required
      ->when($admin->admin_type === 'employee', function ($q) use ($admin) {
        $q->whereHas('user', function ($userQuery) use ($admin) {
          $userQuery->where('created_by', $admin->id);
        });
      })
      ->select([
        'id',
        'user_id',
        'txn_id',
        'payer_name',
        'basic_details',
        'transfer_mode',
        'amount',
        'charge_amount',
        'gst_amount',
        'total_charge',
        'total_amount',
        'status',
        'payment_status',
        'api_payment_status',
        'created_at',
        'api',
        'api_txn_id',
        'utr',
        'description',
        'response_message',
        'ip',
      ])

      /* 🔍 FILTERS */
      ->when(
        $request->txn_id,
        fn($q, $v) =>
        $q->where('txn_id', 'like', "%{$v}%")
      )

      ->when(
        $request->api_txn_id,
        fn($q, $v) =>
        $q->where('api_txn_id', 'like', "%{$v}%")
      )

      ->when($request->user, function ($q, $v) {
        $q->whereHas('user', function ($uq) use ($v) {
          $uq->where(function ($sub) use ($v) {
            $sub->where('company_name', 'like', "%{$v}%")
              ->orWhere('email', 'like', "%{$v}%")
              ->orWhere('mobile_number', 'like', "%{$v}%");
          });
        });
      })

      ->when(
        $request->status !== null,
        fn($q) =>
        $q->where('status', $request->status)
      )
      ->when($request->from_date || $request->to_date, function ($q) use ($request) {
        if ($request->from_date && $request->to_date) {
          $q->whereBetween('created_at', [
            Carbon::parse($request->from_date)->startOfDay(),
            Carbon::parse($request->to_date)->endOfDay()
          ]);
        } elseif ($request->from_date) {
          $q->whereDate('created_at', '>=', $request->from_date);
        } elseif ($request->to_date) {
          $q->whereDate('created_at', '<=', $request->to_date);
        }
      });

    return DataTables::of($query)
      ->addIndexColumn()
      /* 👤 USER INFO COLUMN */
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
      /* 💰 AMOUNT FORMAT */
      ->editColumn('amount', fn($t) => '₹ ' . number_format($t->amount, 2))
      ->editColumn('total_charge', function ($t) {
        $gst = $t->gst_amount ?? 0;
        $charge = $t->charge_amount ?? 0;
        $platform = $t->platform_fee;
        return '
                    <div>
                        <strong>₹ ' . number_format($t->total_charge, 2) . '</strong><br>
                        <small class="text-muted">
                            (GST ₹ ' . number_format($gst, 2) . ' +
                            Charge ₹ ' . number_format($charge, 2) . ' +
                            Platform ₹ ' . number_format($platform, 2) . ')
                        </small>
                    </div>
                ';
      })
      ->editColumn('total_amount', fn($t) => '₹ ' . number_format($t->total_amount, 2))

      /* 🚦 STATUS BADGES */
      ->editColumn('status', function ($t) {
        return match ($t->status) {
          'S'   => '<span class="badge rounded-pill bg-primary">Success</span>',
          'P'   => '<span class="badge rounded-pill bg-warning">Pending</span>',
          'F'    => '<span class="badge rounded-pill bg-danger">Failed</span>',
          'R'    => '<span class="badge rounded-pill bg-danger">Refund</span>',
          'Q'    => '<span class="badge rounded-pill bg-info">Queued</span>',
          default     => '<span class="badge rounded-pill bg-secondary">' . e($t->status) . '</span>',
        };
      })

      /* 📅 DATE */
      ->editColumn('created_at', function ($t) {
        return $t->created_at
          ? \Carbon\Carbon::parse($t->created_at)->format('d/m/Y H:i:s')
          : '-';
      })

      /* ⚙️ ACTIONS DROPDOWN */
      ->addColumn('actions', function ($t) {
        return '
                <div class="dropdown">
                    <button class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                        Actions
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 view-txn"
                            href="javascript:void(0)"
                            data-id="' . $t->id . '">
                                <i class="fa fa-eye text-primary"></i>
                                <span>View Details</span>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2"
                            href="javascript:void(0)"
                            data-id="' . $t->id . '"
                            onclick="updateUtr(this)">
                                <i class="fa fa-pen-to-square text-warning"></i>
                                <span>Update UTR</span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2"
                            href="javascript:void(0)"
                            data-id="' . $t->id . '"
                            onclick="checkStatus(this)">
                                <i class="fa fa-rotate text-info"></i>
                                <span>Check Status</span>
                            </a>
                        </li>
                    </ul>
                </div>';
      })


      ->rawColumns([
        'user_info',
        'total_charge',
        'status',
        'actions'
      ])

      ->toJson();
  }

  public function showAjax($id)
  {
    $txn = PayinTransaction::with('user')->findOrFail($id);
    return response()->json($txn);
  }
}
