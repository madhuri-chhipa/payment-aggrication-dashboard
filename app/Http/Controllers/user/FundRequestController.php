<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\CompanyAccount;
use App\Models\FundRequest;
use App\Models\UserBankAccount;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;


class FundRequestController extends Controller
{

  public function fundRequestsList()
  {
    $companyAccounts = CompanyAccount::where('status', 'A')->get();
    $senderAccounts = UserBankAccount::where('status', 'A')->get();
    return view('user.fund_requests', compact('companyAccounts', 'senderAccounts'));
  }

  public function getFundRequests(Request $request)
  {
    $query = FundRequest // relation required
      ::select([
        'id',
        'user_id',
        'request_id',
        'wallet_txn_id',
        'amount',
        'sender_account_number',
        'company_account_number',
        'transaction_utr',
        'mode',
        'source',
        'status',
        'created_at'
      ])
      ->where('user_id', Auth::id())
      /* 🔍 FILTERS */
      ->when(
        $request->reqid,
        fn($q, $v) =>
        $q->where('request_id', 'like', "%{$v}%")
      )

      ->when(
        $request->wallet_txn_id,
        fn($q, $v) =>
        $q->where('wallet_txn_id', 'like', "%{$v}%")
      )

      ->when(
        $request->sender_account_number,
        fn($q, $v) =>
        $q->where('sender_account_number', 'like', "%{$v}%")
      )

      ->when(
        $request->company_account_number,
        fn($q, $v) =>
        $q->where('company_account_number', 'like', "%{$v}%")
      )

      ->when(
        $request->transaction_utr,
        fn($q, $v) =>
        $q->where('transaction_utr', 'like', "%{$v}%")
      )

      ->when(
        $request->status !== null,
        fn($q) =>
        $q->where('status', $request->status)
      )

      ->when(
        $request->source !== null,
        fn($q) =>
        $q->where('source', $request->source)
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
      ->editColumn('amount', fn($t) => '₹ ' . number_format($t->amount, 2))
      ->editColumn('request_id', function ($t) {
        return '
                <a href="javascript:void(0)"
                    class="view-request text-primary fw-semibold"
                    data-id=' . $t->id . '>
                    ' . $t->request_id . '
                </a>';
      })
      /* 🚦 STATUS BADGES */
      ->editColumn('status', function ($t) {
        return match ($t->status) {
          'A'   => '<span class="badge rounded-pill bg-success">Accepted</span>',
          'R'   => '<span class="badge rounded-pill bg-danger">Rejected</span>',
          'P'    => '<span class="badge rounded-pill bg-warning">Pending</span>',
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
        'request_id',
        'amount',
        'status',
        'created_at'
      ])

      ->toJson();
  }

  public function store(Request $request)
  {
    $request->validate([
      'amount' => 'required|numeric|min:1',
      'sender_account_number' => 'required|string|max:50',
      'company_account_number' => 'required|string|max:50',
      'transaction_utr' => 'required|string|max:100|unique:fund_requests,transaction_utr',
      'mode' => 'required|string',
      'source' => 'required|string',
    ]);

    // Generate unique IDs
    $requestId   = $this->generateUniqueId('FR', 'request_id');
    $walletTxnId = $this->generateUniqueId('WT', 'wallet_txn_id');

    FundRequest::create([
      'user_id' => Auth::id(),
      'request_id' => $requestId,
      'wallet_txn_id' => $walletTxnId,
      'amount' => $request->amount,
      'sender_account_number' => $request->sender_account_number,
      'company_account_number' => $request->company_account_number,
      'transaction_utr' => $request->transaction_utr,
      'mode' => $request->mode,
      'status' => 'P',
      'source' => $request->source,
    ]);

    return back()->with('success', 'Fund request submitted successfully!');
  }
  private function generateUniqueId($prefix, $column)
  {
    do {
      $id = $prefix . strtoupper(Str::random(10));
    } while (FundRequest::where($column, $id)->exists());

    return $id;
  }
  public function details($id)
  {
    $request = FundRequest::findOrFail($id);

    $statusMap = [
      'P' => ['text' => 'Pending',  'class' => 'warning'],
      'A' => ['text' => 'Accepted', 'class' => 'success'],
      'R' => ['text' => 'Rejected', 'class' => 'danger'],
    ];

    $status = $statusMap[$request->status] ?? ['text' => 'Unknown', 'class' => 'secondary'];
    $response = '
       <div class="container-fluid small">
        <div class="row mb-2">
            <div class="col-5 text-muted">Request ID</div>
            <div class="col-7 fw-semibold">' . $request->request_id . '</div>
        </div>

        <div class="row mb-2">
            <div class="col-5 text-muted">Wallet Transaction ID</div>
            <div class="col-7 fw-semibold">' . ($request->wallet_txn_id ?? '-') . '</div>
        </div>

        <div class="row mb-2">
            <div class="col-5 text-muted">Amount</div>
            <div class="col-7 fw-semibold text-success">₹ ' . number_format($request->amount, 2) . '</div>
        </div>

        <div class="row mb-2">
            <div class="col-5 text-muted">Sender Account Number</div>
            <div class="col-7 fw-semibold">' . ($request->sender_account_number ?? '-') . '</div>
        </div>

        <div class="row mb-2">
            <div class="col-5 text-muted">Company Account Number</div>
            <div class="col-7 fw-semibold">' . ($request->company_account_number ?? '-') . '</div>
        </div>

        <div class="row mb-2">
            <div class="col-5 text-muted">Transaction UTR</div>
            <div class="col-7 fw-semibold">' . ($request->transaction_utr ?? '-') . '</div>
        </div>

        <div class="row mb-2">
            <div class="col-5 text-muted">Mode</div>
            <div class="col-7 fw-semibold">' . ($request->mode ?? '-') . '</div>
        </div>

        <div class="row mb-2">
            <div class="col-5 text-muted">Source</div>
            <div class="col-7 fw-semibold">' . ($request->source ?? '-') . '</div>
        </div>

        <div class="row mb-2">
            <div class="col-5 text-muted">Remark</div>
            <div class="col-7 fw-semibold">' . ($request->remark ?? '-') . '</div>
        </div>

        <div class="row mb-2">
            <div class="col-5 text-muted">Status</div>
            <div class="col-7">
                <span class="badge bg-' . $status['class'] . '">' . $status['text'] . '</span>
            </div>
        </div>

        <div class="row mb-2">
            <div class="col-5 text-muted">Created At</div>
            <div class="col-7 fw-semibold">' . $request->created_at->format('d M Y, h:i A') . '</div>
        </div>

    </div>';

    return response($response);
  }

  // public function show(string $id)
  // {
  //     $transaction = PayoutTransaction::where('id', $id)->first();
  //     return view('user.reports.payout_transaction_view', compact('transaction'));
  // }
}

// ' . route('admin.transactions.view', $t->id) . '
