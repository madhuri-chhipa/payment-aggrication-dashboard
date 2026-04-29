<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\UserWalletTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\Facades\DataTables;

class WalletHistoryController extends Controller
{
  public function WalletHistory()
  {
    return view('admin.wallet-logs');
  }
  public function WalletHistoryDatatable(Request $request)
  {
    // $userId = Auth::id();
    // $history = DB::table('user_wallet_transactions')
    //   ->where('user_id', $userId)
    //   ->orderByDesc('id')
    //   ->get();
    // return view('user.walletlogs', compact(
    //   'history'
    // ));
    $admin = auth()->guard('admin')->user();
    $query = UserWalletTransaction::with('user') // relation required
      ->when($admin->admin_type === 'employee', function ($q) use ($admin) {
        $q->whereHas('user', function ($userQuery) use ($admin) {
          $userQuery->where('created_by', $admin->id);
        });
      })
      ->select([
        'id',
        'user_id',
        'service_name',
        'refid',
        'opening_balance',
        'amount',
        'total_charge',
        'total_amount',
        'closing_balance',
        'credit',
        'debit',
        'description',
        'created_at',
      ])

      /* 🔍 FILTERS */
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
        $request->refid,
        fn($q, $v) =>
        $q->where('refid', 'like', "%{$v}%")
      )

      ->when($request->wallet, function ($q, $v) {
        $q->where('service_name', 'like', "%{$v}%");
      })

      ->when($request->amount, function ($q, $v) {
        $q->where('total_amount', 'like', "%{$v}%");
      })

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
      ->addColumn('user_info', function ($log) {
        return '
          <strong>' . ($log->user->company_name ?? '-') . '</strong><br>
          <small>' . ($log->user->email ?? '-') . '</small><br>
          <small>' . ($log->user->mobile_number ?? '-') . '</small>
        ';
      })
      ->editColumn('ref_id', fn($u) => $u->ref_id ? '<a class="text-primary" href="' . route('report.payout-transactions.show', $u->id) . '">' . $u->txn_id . '</a>' : '-')
      /* 🏦 BENEFICIARY INFO COLUMN */
      ->editColumn('total_amount', function ($t) {
        $amount = $t->amount ?? 0;
        $charge = $t->total_charge ?? 0;
        return '
          <div>
              <strong>₹ ' . number_format($t->total_amount, 2) . '</strong><br>
              <small class="text-muted">
                (Amount ₹ ' . number_format($amount, 2) . ' + 
                Total Charge ₹ ' . number_format($charge, 2) . ')
              </small>
          </div>
        ';
      })
      /* 📅 DATE */
      ->editColumn('created_at', function ($t) {
        return $t->created_at
          ? \Carbon\Carbon::parse($t->created_at)->format('d/m/Y H:i:s')
          : '-';
      })

      ->rawColumns([
        'user_info',
        'ref_id',
        'total_amount',
        'created_at'
      ])

      ->toJson();
  }

  // 📤 Export (CSV)
  public function WalletHistoryExport(Request $request)
  {
    $query = UserWalletTransaction::orderBy('created_at', 'desc');

    return response()->streamDownload(function () use ($query) {
      $handle = fopen('php://output', 'w');
      fputcsv($handle, [
        'Date',
        'Ref ID',
        'Wallet Type',
        'Amount',
        'Credit',
        'Debit',
        'Closing Balance',
        'Description'
      ]);

      $query->chunk(500, function ($rows) use ($handle) {
        foreach ($rows as $row) {
          fputcsv($handle, [
            $row->created_at,
            $row->refid,
            $row->service_name,
            $row->amount,
            $row->credit,
            $row->debit,
            $row->closing_balance,
            $row->description,
          ]);
        }
      });

      fclose($handle);
    }, 'wallet-history.csv');
  }
}
