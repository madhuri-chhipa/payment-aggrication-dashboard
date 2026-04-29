<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\PayoutTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;


class PayoutTransactionController extends Controller
{
  public function payoutTxnLog()
  {
    return view('user.reports.payout_transaction_logs');
  }

  public function getTransactionLogs(Request $request)
  {
    $query = PayoutTransaction
      ::select([
        'id',
        'user_id',
        'txn_id',
        'bene_account',
        'bene_ifsc',
        'bene_name',
        'bene_email',
        'bene_mobile',
        'transfer_mode',
        'amount',
        'charge_amount',
        'gst_amount',
        'platform_fee',
        'total_charge',
        'total_amount',
        'status',
        'payment_status',
        'api_payment_status',
        'api_txn_id',
        'utr',
        'description',
        'response_message',
        'created_at'
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

      ->when($request->account, function ($q, $v) {
        $q->where('bene_account', 'like', "%{$v}%")
          ->orWhere('bene_ifsc', 'like', "%{$v}%")
          ->orWhere('bene_name', 'like', "%{$v}%");
      })

      ->when($request->contact, function ($q, $v) {
        $q->where('bene_email', 'like', "%{$v}%")
          ->orWhere('bene_mobile', 'like', "%{$v}%");
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
      ->editColumn('txn_id', fn($u) => $u->txn_id
        ? '<a class="dropdown-item view-txn text-primary"
                style="white-space: normal;"
                href="javascript:void(0)" data-id="' . $u->id . '">' . $u->txn_id . '</a>'
        : '-')
      /* 🏦 BENEFICIARY INFO COLUMN */
      ->addColumn('contact_details', function ($t) {
        return '
                <div>
                    <strong>' . e($t->bene_email) . '</strong><br>
                    <span class="text-muted">' . e($t->bene_mobile) . '</span><br>
                </div>
            ';
      })
      ->addColumn('account_info', function ($t) {
        return '
                <div>
                    <strong>' . e($t->bene_name) . '</strong><br>
                    <span class="text-muted">' . e($t->bene_account) . '</span><br>
                    <span class="badge bg-dark">' . e($t->bene_ifsc) . '</span>
                </div>
            ';
      })
      /* 💰 AMOUNT FORMAT */
      ->editColumn('amount', fn($t) => '₹ ' . number_format($t->amount, 2))
      ->editColumn('total_charge', function ($t) {
        $gst = $t->gst_amount ?? 0;
        $charge = $t->charge_amount ?? 0;
        $platform = $t->platform_fee ?? 0;
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
        'contact_details',
        'account_info',
        'total_charge',
        'status',
        'created_at'
      ])

      ->toJson();
  }

  public function showAjax($id)
  {
    $txn = PayoutTransaction::findOrFail($id);
    return response()->json($txn);
  }
}
