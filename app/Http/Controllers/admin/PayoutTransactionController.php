<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\PayoutTransaction;
use App\Models\User;
use Carbon\Carbon;
use App\Models\UserApiKey;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use App\Services\UnionbankPayoutService;
use Illuminate\Pagination\LengthAwarePaginator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;


class PayoutTransactionController extends Controller
{
  protected $unionbankService;

  public function __construct(UnionbankPayoutService $unionbankService)
  {
    $this->unionbankService = $unionbankService;
  }
  public function payoutTxnLog()
  {
    return view('admin.reports.payout_transaction_logs');
  }

  public function getTransactionLogs(Request $request)
  {
    $admin = auth()->guard('admin')->user();
    $query = PayoutTransaction::with(['user'])
      ->when($admin->admin_type === 'employee', function ($q) use ($admin) {
        $q->whereHas('user', function ($userQuery) use ($admin) {
          $userQuery->where('created_by', $admin->id);
        });
      })
      ->select([
        'id',
        'user_id',
        'txn_id',
        'bene_account',
        'bene_ifsc',
        'bene_name',
        'bene_email',
        'bene_mobile',
        'transfer_mode',
        'platform_fee',
        'amount',
        'charge_amount',
        'gst_amount',
        'total_charge',
        'total_amount',
        'status',
        'payment_status',
        'api_payment_status',
        'api',
        'api_txn_id',
        'utr',
        'description',
        'response_message',
        'created_at'
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

      ->when($request->account, function ($q, $v) {
        $q->where('bene_account', 'like', "%{$v}%")
          ->orWhere('bene_ifsc', 'like', "%{$v}%")
          ->orWhere('bene_name', 'like', "%{$v}%");
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

      /* 🏦 BENEFICIARY INFO COLUMN */
      ->addColumn('account_info', function ($t) {
        return '
                <div>
                    <strong>' . e($t->bene_name) . '</strong><br>
                    <span class="text-muted">' . e($t->bene_account) . '</span><br>
                    <span class="badge bg-dark">' . e($t->bene_ifsc) . '</span>
                </div>
            ';
      })
      ->addColumn('contact_details', function ($t) {
        return '
                <div>
                    <strong>' . e($t->bene_email) . '</strong><br>
                    <span class="text-muted">' . e($t->bene_mobile) . '</span><br>
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
                            <a class="dropdown-item d-flex align-items-center gap-2 payoutcheckstatus"
                            href="javascript:void(0)"
                            data-id="' . $t->id . '">
                                <i class="fa fa-rotate text-info"></i>
                                <span>Check Status</span>
                            </a>
                        </li>
                    </ul>
                </div>';
      })


      ->rawColumns([
        'user_info',
        'account_info',
        'contact_details',
        'total_charge',
        'status',
        'actions'
      ])

      ->toJson();
  }

  public function showAjax($id)
  {
    $txn = PayoutTransaction::with('user')->findOrFail($id);
    return response()->json($txn);
  }
  public function ajaxCheckStatus(Request $request)
  {
    $request->validate([
      'id' => 'required|integer'
    ]);

    $transaction = PayoutTransaction::find($request->id);

    if (!$transaction) {
      return response()->json([
        'status' => false,
        'message' => 'Transaction not found.'
      ], 404);
    }

    $txn_id = $transaction->txn_id;
    if (empty($txn_id)) {
      return response()->json([
        'status' => false,
        'message' => 'Transaction txn_id is missing.'
      ], 400);
    }
    try {
      $serviceResponse = $this->unionbankService->TransactionStatus($txn_id);
      if (is_object($serviceResponse)) {
        $serviceResponse = (array) $serviceResponse;
      }

      if ($serviceResponse instanceof \Illuminate\Http\JsonResponse) {
        $serviceResponse = $serviceResponse->getData(true);
      }

      $status  = $serviceResponse['status'] ?? 'P';
      $message = $serviceResponse['message'] ?? 'Status updated';
      $utr     = $serviceResponse['utr'] ?? null;

      $updateCommon = [
        'utr'              => $utr,
        'response_message' => $message,
        'updated_at'       => now(),
        'updated_by'       => 'API',
      ];
      $user = User::find($transaction->user_id);
      if ($status == 'F' && $transaction->status !== 'R') {
        DB::transaction(function () use ($transaction, $user, $updateCommon) {
          $this->creditUserWalletAmount(
            $user,
            $transaction->txn_id,
            $transaction->amount,
            $transaction->charge_amount,
            $transaction->gst_amount
          );
          $transaction->update(array_merge($updateCommon, [
            'status'       => 'R',
            'processed_at' => now(),
            'processed_by' => 'API',
            'description'  => 'Transaction Failed',
          ]));
        });

        $callbackStatus = 'FAILED';
        $httpCode = 400;
      } elseif ($status === 'S') {

        $transaction->update(array_merge($updateCommon, [
          'status'       => 'S',
          'processed_at' => now(),
          'processed_by' => 'API',
          'description'  => $message ?? 'Transaction Successful',
        ]));

        $callbackStatus = 'SUCCESS';
        $httpCode = 200;
      } else {

        $transaction->update(array_merge($updateCommon, [
          'status'       => 'P',
          'processed_at' => now(),
          'processed_by' => 'API',
          'description'  => $message ?? 'Pending',
        ]));

        $callbackStatus = 'PENDING';
        $httpCode = 201;
      }

      // 🔥 CALLBACK PAYLOAD
      $callbackPayload = [
        'http_code' => $httpCode,
        'status'    => $callbackStatus,
        'message'   => 'Transaction updated successfully.',
        'data'      => [
          'transaction_id' => $transaction->txn_id,
          'reference_id'   => $transaction->api_txn_id,
          'utr'            => $utr,
          'transfer_mode'  => $transaction->transfer_mode ?? 'IMPS',
          'amount'         => $transaction->amount,
          'timestamp'      => now()->toDateTimeString(),
        ],
      ];

      // 🔥 GET CALLBACK URL
      $callback_url = DB::table('user_api_keys')
        ->where('user_id', $transaction->user_id)
        ->value('payout_webhooks');

      // 🔥 HIT CALLBACK
      if ($callback_url) {
        try {
          Http::withHeaders([
            'Content-Type' => 'application/json'
          ])
            ->timeout(30)
            ->post($callback_url, $callbackPayload);
        } catch (\Exception $e) {
          Log::error("Callback Failed: " . $e->getMessage());
        }
      }

      return response()->json([
        'status'  => true,
        'message' => $message
      ]);
    } catch (\Exception $e) {

      Log::error('Payout status check failed: ' . $e->getMessage());

      return response()->json([
        'status' => false,
        'message' => 'Status check failed. ' . $e->getMessage()
      ], 500);
    }
  }
  private function creditUserWalletAmount($user, $refid, $amount, $charge_amount, $gst_amount)
  {
    $isInTransaction = DB::transactionLevel() > 0;
    if (!$isInTransaction) {
      DB::beginTransaction();
    }

    try {
      $user = User::where('id', $user->id)
        ->lockForUpdate()
        ->first();

      $wallet_balance = $user->payout_balance;
      $total_charge = $charge_amount + $gst_amount;
      $total_amount = $amount + $total_charge;

      $opening_balance = $wallet_balance;
      $closing_balance = $opening_balance + $total_amount;

      DB::table('user_wallet_transactions')->insert([
        'user_id'         => $user->id,
        'service_name'    => 'REFUND',
        'refid' => $refid,
        'opening_balance' => $opening_balance,
        'total_charge'    => $total_charge,
        'amount'          => $amount,
        'total_amount'    => $total_amount,
        'closing_balance' => $closing_balance,
        'credit'          => $total_amount,
        'debit'           => 0.00,
        'description'     => $user->uid . '/REFUND/' . $refid,
        'created_at'      => now(),
        'updated_at'      => now(),
      ]);

      DB::table('users')
        ->where('id', $user->id)
        ->update([
          'payout_balance' => $closing_balance,
        ]);

      if (!$isInTransaction) {
        DB::commit();
      }

      return [
        'status'          => true,
        'opening_balance' => $opening_balance,
        'closing_balance' => $closing_balance,
        'total_amount'    => $total_amount,
      ];
    } catch (\Exception $e) {
      if (!$isInTransaction) {
        DB::rollBack();
      }
      Log::error("Wallet credit failed: " . $e->getMessage());
      return ['status' => false, 'message' => 'Wallet credit failed: ' . $e->getMessage()];
    }
  }
  private function normalizeInputDate(?string $date): ?string
  {
    if (empty($date)) {
      return null;
    }

    try {
      if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return Carbon::createFromFormat('Y-m-d', $date)->format('d-m-Y');
      }

      if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $date)) {
        return Carbon::createFromFormat('d-m-Y', $date)->format('d-m-Y');
      }
    } catch (\Exception $e) {
      return null;
    }

    return null;
  }

  private function formatForDateInput(?string $date): string
  {
    if (empty($date)) {
      return now()->format('Y-m-d');
    }

    try {
      if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $date)) {
        return Carbon::createFromFormat('d-m-Y', $date)->format('Y-m-d');
      }

      if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return Carbon::createFromFormat('Y-m-d', $date)->format('Y-m-d');
      }
    } catch (\Exception $e) {
      return now()->format('Y-m-d');
    }

    return now()->format('Y-m-d');
  }

  public function getBankStatement(Request $request)
  {
    $rawStartDate = $request->get('startDate');
    $rawEndDate   = $request->get('endDate');

    $startDate = $this->normalizeInputDate($rawStartDate) ?? now()->format('d-m-Y');
    $endDate   = $this->normalizeInputDate($rawEndDate) ?? now()->format('d-m-Y');

    $transactions = $this->unionbankService->bankstatement($startDate, $endDate);

    if (!is_array($transactions)) {
      $transactions = [];
    }

    $collection = collect($transactions)
      ->sortByDesc(function ($item) {
        return $item['tranPostDate'] ?? '';
      })
      ->values();

    $perPage = 25;
    $currentPage = LengthAwarePaginator::resolveCurrentPage();

    $currentItems = $collection
      ->slice(($currentPage - 1) * $perPage, $perPage)
      ->values();

    $paginatedTransactions = new LengthAwarePaginator(
      $currentItems,
      $collection->count(),
      $perPage,
      $currentPage,
      [
        'path'  => url()->current(),
        'query' => $request->query(),
      ]
    );

    return view('admin.reports.bank_statement', [
      'transactions'   => $paginatedTransactions,
      'startDate'      => $startDate, // d-m-Y for API/internal
      'endDate'        => $endDate,   // d-m-Y for API/internal
      'startDateInput' => $this->formatForDateInput($rawStartDate ?: $startDate), // Y-m-d for input
      'endDateInput'   => $this->formatForDateInput($rawEndDate ?: $endDate),     // Y-m-d for input
    ]);
  }

  public function exportBankStatementExcel(Request $request)
  {
    $startDate = $this->normalizeInputDate($request->get('startDate')) ?? now()->format('d-m-Y');
    $endDate   = $this->normalizeInputDate($request->get('endDate')) ?? now()->format('d-m-Y');

    $transactions = $this->unionbankService->bankstatement($startDate, $endDate);

    if (!is_array($transactions)) {
      $transactions = [];
    }

    $transactions = collect($transactions)
      ->sortByDesc(function ($item) {
        return $item['tranPostDate'] ?? '';
      })
      ->values();

    $fileName = 'bank_statement_' . now()->format('Ymd_His') . '.csv';

    $headers = [
      'Content-Type' => 'text/csv; charset=UTF-8',
      'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
    ];

    $callback = function () use ($transactions) {
      $file = fopen('php://output', 'w');

      fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

      fputcsv($file, [
        '#',
        'Tran ID',
        'Value Date',
        'Tran Date',
        'Post Date',
        'Particulars',
        'Amount',
        'Dr/Cr',
        'Type',
        'Sub Type',
        'Balance After Tran',
        'Instrument No',
        'Serial No',
        'Last Tran',
      ]);

      foreach ($transactions as $index => $txn) {
        fputcsv($file, [
          $index + 1,
          $txn['tranId'] ?? '',
          !empty($txn['tranValueDate']) ? Carbon::createFromFormat('Ymd', $txn['tranValueDate'])->format('d-m-Y') : '',
          !empty($txn['tranDate']) ? Carbon::createFromFormat('Ymd', $txn['tranDate'])->format('d-m-Y') : '',
          !empty($txn['tranPostDate']) ? Carbon::createFromFormat('YmdHis', $txn['tranPostDate'])->format('d-m-Y h:i:s A') : '',
          $txn['tranParticulars'] ?? '',
          $txn['tranAmount'] ?? '',
          $txn['drCRIndicator'] ?? '',
          $txn['tranType'] ?? '',
          $txn['tranSubType'] ?? '',
          $txn['balAfterTran'] ?? '',
          $txn['instrumentNumber'] ?? '',
          $txn['serialNo'] ?? '',
          $txn['isLastTran'] ?? '',
        ]);
      }

      fclose($file);
    };

    return response()->stream($callback, 200, $headers);
  }

  public function exportBankStatementPdf(Request $request)
  {
    $startDate = $this->normalizeInputDate($request->get('startDate')) ?? now()->format('d-m-Y');
    $endDate   = $this->normalizeInputDate($request->get('endDate')) ?? now()->format('d-m-Y');

    $transactions = $this->unionbankService->bankstatement($startDate, $endDate);

    if (!is_array($transactions)) {
      $transactions = [];
    }

    $transactions = collect($transactions)
      ->sortByDesc(function ($item) {
        return $item['tranPostDate'] ?? '';
      })
      ->values();

    $pdf = Pdf::loadView('admin.reports.bank_statement_pdf', [
      'transactions' => $transactions,
      'startDate'    => $startDate,
      'endDate'      => $endDate,
    ])->setPaper('a4', 'landscape');

    return $pdf->download('bank_statement_' . now()->format('Ymd_His') . '.pdf');
  }
}
