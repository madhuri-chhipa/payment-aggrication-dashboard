<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\UserWalletTransaction;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;

class UserWalletController extends Controller
{
  public function index()
  {
    $userId = Auth::id();
    $virtualAccount = DB::table('users_virtual_accounts')
      ->where('user_id', $userId)
      ->where('status', 'A')
      ->orderByDesc('id')
      ->first();

    // Wallet balance (adjust column if needed)
    $walletBalance = DB::table('users')
      ->where('id', $userId)
      ->value('virtual_balance') ?? 0;

    $payinBalance = DB::table('users')
      ->where('id', $userId)
      ->value('payin_balance') ?? 0;

    $payoutBalance = DB::table('users')
      ->where('id', $userId)
      ->value('payout_balance') ?? 0;
    // History (optional – you already handle this)z
    $history = DB::table('fund_requests')
      ->where('user_id', $userId)
      ->orderByDesc('id')
      ->where('source', 'Virtual')
      ->get();

    return view('user.wallet', compact(
      'walletBalance',
      'payinBalance',
      'payoutBalance',
      'virtualAccount',
      'history'
    ));
  }
  public function refreshVirtualBalance()
  {
    $userId = Auth::id(); // returns uid (already fixed)

    if (!$userId) {
      return response()->json([
        'success' => false,
        'message' => 'Unauthenticated'
      ], 401);
    }

    $balance = DB::table('users')
      ->where('id', $userId)
      ->select('virtual_balance', 'payin_balance', 'payout_balance')
      ->first();

    return response()->json([
      'success' => true,
      'message' => 'Balance refreshed',
      'data' => [
        'virtual_balance' => number_format((float)($balance->virtual_balance ?? 0), 2, '.', ''),
        'payin_balance'   => number_format((float)($balance->payin_balance ?? 0), 2, '.', ''),
        'payout_balance'  => number_format((float)($balance->payout_balance ?? 0), 2, '.', ''),
      ]
    ]);
  }
  private function baseQuery(Request $request)
  {
    $q = DB::table('user_fund_requests')
      ->where('user_id', Auth::id());

    // ---- Filters from request (custom filters) ----
    if ($request->filled('request_id')) {
      $q->where('request_id', 'like', '%' . $request->request_id . '%');
    }
    if ($request->filled('amount')) {
      $q->where('amount', $request->amount);
    }
    if ($request->filled('status')) { // A/P/R
      $q->where('status', $request->status);
    }
    if ($request->filled('wallet_txn_id')) {
      $q->where('wallet_txn_id', 'like', '%' . $request->wallet_txn_id . '%');
    }
    if ($request->filled('utr')) {
      $q->where('transaction_utr', 'like', '%' . $request->utr . '%');
    }
    if ($request->filled('sender_account_number')) {
      $q->where('sender_account_number', 'like', '%' . $request->sender_account_number . '%');
    }
    if ($request->filled('date_from')) {
      $q->whereDate('created_at', '>=', $request->date_from);
    }
    if ($request->filled('date_to')) {
      $q->whereDate('created_at', '<=', $request->date_to);
    }

    // ---- DataTables global search ----
    $search = $request->input('search.value');
    if (!empty($search)) {
      $q->where(function ($w) use ($search) {
        $w->where('request_id', 'like', "%{$search}%")
          ->orWhere('wallet_txn_id', 'like', "%{$search}%")
          ->orWhere('transaction_utr', 'like', "%{$search}%")
          ->orWhere('sender_account_number', 'like', "%{$search}%")
          ->orWhere('amount', 'like', "%{$search}%");
      });
    }

    return $q;
  }

  public function datatables(Request $request)
  {
    // DataTables params
    $draw = (int) $request->input('draw', 1);
    $start = (int) $request->input('start', 0);
    $length = (int) $request->input('length', 10);

    // ordering
    $columns = [
      0 => 'created_at',
      1 => 'request_id',
      2 => 'wallet_txn_id',
      3 => 'amount',
      4 => 'status',
      5 => 'transaction_utr',
      6 => 'sender_account_number',
      7 => 'mode',
      8 => 'description',
    ];
    $orderColIndex = (int) $request->input('order.0.column', 0);
    $orderDir = $request->input('order.0.dir', 'desc');
    $orderCol = $columns[$orderColIndex] ?? 'created_at';
    $orderDir = in_array($orderDir, ['asc', 'desc']) ? $orderDir : 'desc';

    // total records (without any filters except user_id)
    $total = DB::table('user_fund_requests')
      ->where('user_id', Auth::id())
      ->count();

    // filtered records
    $filteredQuery = $this->baseQuery($request);
    $filtered = (clone $filteredQuery)->count();

    // page data
    $rows = $filteredQuery
      ->orderBy($orderCol, $orderDir)
      ->offset($start)
      ->limit($length)
      ->get();

    // format response
    $data = $rows->map(function ($r) {
      $statusText = $r->status === 'A' ? 'Approved' : ($r->status === 'P' ? 'Pending' : 'Failed');

      return [
        'created_at' => $r->created_at,
        'request_id' => $r->request_id,
        'wallet_txn_id' => $r->wallet_txn_id,
        'amount' => $r->amount,
        'status' => $r->status,
        'status_text' => $statusText,
        'transaction_utr' => $r->transaction_utr,
        'sender_account_number' => $r->sender_account_number,
        'mode' => $r->mode,
        'description' => $r->description,
      ];
    });

    return response()->json([
      'draw' => $draw,
      'recordsTotal' => $total,
      'recordsFiltered' => $filtered,
      'data' => $data,
    ]);
  }

  public function exportCsv(Request $request): StreamedResponse
  {
    $q = $this->baseQuery($request)->orderByDesc('id');

    $filename = 'virtual_load_history_' . now()->format('Ymd_His') . '.csv';

    return response()->streamDownload(function () use ($q) {
      $out = fopen('php://output', 'w');

      fputcsv($out, [
        'Date',
        'Request ID',
        'Wallet Txn ID',
        'Amount',
        'Status',
        'UTR',
        'Sender Account',
        'Mode',
        'Description'
      ]);

      $q->chunk(500, function ($rows) use ($out) {
        foreach ($rows as $r) {
          fputcsv($out, [
            $r->created_at,
            $r->request_id,
            $r->wallet_txn_id,
            $r->amount,
            $r->status,
            $r->transaction_utr,
            $r->sender_account_number,
            $r->mode,
            $r->description,
          ]);
        }
      });

      fclose($out);
    }, $filename, [
      'Content-Type' => 'text/csv; charset=UTF-8',
    ]);
  }

  public function exportXlsx(Request $request)
  {
    $q = $this->baseQuery($request)->orderByDesc('id')->get();

    $filename = 'virtual_load_history_' . now()->format('Ymd_His') . '.xls';

    $html = view('user.exports.virtual_load_history_xls', ['rows' => $q])->render();

    return response($html, 200, [
      'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
      'Content-Disposition' => "attachment; filename=\"{$filename}\"",
    ]);
  }
  public function WalletHistory()
  {
    return view('user.walletlogs');
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
    $query = UserWalletTransaction // relation required
      ::select([
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
      ->where('user_id', Auth::id())
      /* 🔍 FILTERS */
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
        'ref_id',
        'total_amount',
        'created_at'
      ])

      ->toJson();
  }
  // public function WalletHistoryDatatable(Request $request)
  // {
  //   $userId = Auth::id();

  //   $draw   = (int) $request->draw;
  //   $start  = (int) $request->start;
  //   $length = (int) $request->length;

  //   $columns = [
  //     'created_at',
  //     'refid',
  //     'service_name',
  //     'amount',
  //     'credit',
  //     'debit',
  //     'closing_balance',
  //     'description',
  //   ];

  //   $orderCol = $columns[$request->order[0]['column'] ?? 0] ?? 'created_at';
  //   $orderDir = $request->order[0]['dir'] ?? 'desc';

  //   $query = UserWalletTransaction::where('user_id', $userId);

  //   // 🔎 Filters
  //   if ($request->filled('refid')) {
  //     $query->where('refid', 'like', '%' . $request->refid . '%');
  //   }

  //   if ($request->filled('service_name')) {
  //     $query->where('service_name', $request->service_name);
  //   }

  //   if ($request->filled('date_from')) {
  //     $query->whereDate('created_at', '>=', $request->date_from);
  //   }

  //   if ($request->filled('date_to')) {
  //     $query->whereDate('created_at', '<=', $request->date_to);
  //   }

  //   $total = $query->count();

  //   $rows = $query
  //     ->orderBy($orderCol, $orderDir)
  //     ->skip($start)
  //     ->take($length)
  //     ->get();

  //   return response()->json([
  //     'draw' => $draw,
  //     'recordsTotal' => $total,
  //     'recordsFiltered' => $total,
  //     'data' => $rows->map(function ($row) {
  //       return [
  //         'date' => $row->created_at->format('d M Y, h:i A'),
  //         'refid' => $row->refid,
  //         'service' => ucfirst($row->service_name),
  //         'amount' => number_format($row->amount, 2),
  //         'credit' => number_format($row->credit, 2),
  //         'debit' => number_format($row->debit, 2),
  //         'closing' => number_format($row->closing_balance, 2),
  //         'description' => $row->description ?? '-',
  //       ];
  //     }),
  //   ]);
  // }

  // 📤 Export (CSV)
  public function WalletHistoryExport(Request $request): StreamedResponse
  {
    $userId = Auth::id();

    $query = UserWalletTransaction::where('user_id', $userId)
      ->orderBy('created_at', 'desc');

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