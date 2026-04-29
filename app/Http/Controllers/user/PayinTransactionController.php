<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\PayinTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\Facades\DataTables;


class PayinTransactionController extends Controller
{
  public function payinTxnLog()
  {
    return view('user.reports.payin_transaction_logs');
  }

  public function getTransactionLogs(Request $request)
  {
    $query = PayinTransaction // relation required
      ::select([
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

      ->where('user_id', Auth::id())
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
      /* 💰 AMOUNT FORMAT */
      ->editColumn('txn_id', fn($u) => $u->txn_id
        ? '<a class="dropdown-item view-txn text-primary"
                style="white-space: normal;"
                href="javascript:void(0)" data-id="' . $u->id . '">' . $u->txn_id . '</a>'
        : '-')
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

      ->rawColumns([
        'txn_id',
        'total_charge',
        'status',
        'actions'
      ])

      ->toJson();
  }

  public function showAjax($id)
  {
    $txn = PayinTransaction::findOrFail($id);
    return response()->json($txn);
  }
}
