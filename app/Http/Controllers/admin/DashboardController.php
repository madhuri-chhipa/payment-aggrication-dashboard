<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\FundRequest;
use App\Models\PayoutTransaction;
use App\Models\PayinTransaction;
use App\Models\UserLoginLog;
use App\Models\UserWalletTransaction;
use App\Services\UnionbankPayoutService;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
  protected $unionbankService;

  public function __construct(UnionbankPayoutService $unionbankService)
  {
    $this->unionbankService = $unionbankService;
  }
  public function index(Request $request)
  {
    $loggedAdmin = Auth::guard('admin')->user();
    // Total Users (excluding soft deleted)
    $totalUsers = User::whereNull('deleted_at')->count();
    // Active Users (A = Active)
    $activeUsers = User::whereNull('deleted_at')->where('active', 'A')->count();

    $bankBalancedata = $this->unionbankService->getBalance();
    $datade = json_decode($bankBalancedata->getContent(), true);
    $bankBalance = $datade['balance'];
    // Wallet Totals
    $totalVirtualBalance = User::whereNull('deleted_at')->sum('virtual_balance');
    $totalPayinBalance   = User::whereNull('deleted_at')->sum('payin_balance');
    $totalPayoutBalance  = User::whereNull('deleted_at')->sum('payout_balance');

    $logs = UserLoginLog::with('user')
      ->orderByDesc('logged_at')
      ->limit(10)
      ->get();
    return view('admin.dashboard', compact(
      'loggedAdmin',
      'bankBalance',
      'activeUsers',
      'totalVirtualBalance',
      'totalPayinBalance',
      'totalPayoutBalance',
      'logs'
    ));
  }
  public function filterData(Request $request)
  {
    // =========================
    // Date Filter Logic
    // =========================
    $filter = $request->filter_type;

    switch ($filter) {
      case 'today':
        $from = now()->startOfDay();
        $to   = now()->endOfDay();
        break;

      case 'yesterday':
        $from = now()->subDay()->startOfDay();
        $to   = now()->subDay()->endOfDay();
        break;

      case '7days':
        $from = now()->subDays(6)->startOfDay();
        $to   = now()->endOfDay();
        break;

      case '30days':
        $from = now()->subDays(29)->startOfDay();
        $to   = now()->endOfDay();
        break;

      case 'lastmonth':
        $from = now()->subMonth()->startOfMonth();
        $to   = now()->subMonth()->endOfMonth();
        break;

      case '3months':
        $from = now()->subMonths(3)->startOfDay();
        $to   = now()->endOfDay();
        break;

      case 'custom':
        $from = $request->from_date
          ? Carbon::parse($request->from_date)->startOfDay()
          : now()->startOfDay();

        $to = $request->to_date
          ? Carbon::parse($request->to_date)->endOfDay()
          : now()->endOfDay();
        break;

      default:
        $from = now()->startOfDay();
        $to   = now()->endOfDay();
    }

    // =========================
    // FUND REQUEST STATS
    // =========================
    $successFund = FundRequest::where('status', 'A')
      ->whereBetween('created_at', [$from, $to]);

    $pendingFund = FundRequest::where('status', 'P')
      ->whereBetween('created_at', [$from, $to]);

    $successFundAmount = $successFund->sum('amount');
    $successFundTxn    = $successFund->count();

    $pendingFundAmount = $pendingFund->sum('amount');
    $pendingFundTxn    = $pendingFund->count();

    // =========================
    // TOTAL CREDIT (Wallet Logs Credit)
    // =========================
    $creditStats = UserWalletTransaction::where('service_name', 'CREDIT')->whereBetween('created_at', [$from, $to]);

    $totalCreditAmount = $creditStats->sum('credit');
    $totalCreditTxn    = $creditStats->where('credit', '>', 0)->count();

    // =========================
    // PAYOUT STATS
    // =========================
    $successPayout = PayoutTransaction::where('status', 'S')
      ->whereBetween('created_at', [$from, $to]);

    $pendingPayout = PayoutTransaction::where('status', 'P')
      ->whereBetween('created_at', [$from, $to]);

    $failedPayout = PayoutTransaction::where('status', 'R')
      ->whereBetween('created_at', [$from, $to]);

    $successPayoutAmount = $successPayout->sum('total_amount');
    $successPayoutTxn    = $successPayout->count();

    $pendingPayoutAmount = $pendingPayout->sum('total_amount');
    $pendingPayoutTxn    = $pendingPayout->count();

    $failedPayoutAmount = $failedPayout->sum('total_amount');
    $failedPayoutTxn    = $failedPayout->count();

    // =========================
    // PAYIN STATS
    // =========================
    $successPayin = PayinTransaction::where('status', 'S')
      ->whereBetween('created_at', [$from, $to]);

    $pendingPayin = PayinTransaction::where('status', 'P')
      ->whereBetween('created_at', [$from, $to]);

    $successPayinAmount = $successPayin->sum('total_amount');
    $successPayinTxn    = $successPayin->count();

    $pendingPayinAmount = $pendingPayin->sum('total_amount');
    $pendingPayinTxn    = $pendingPayin->count();
    return response()->json([
      'successFundAmount' => number_format($successFundAmount, 2),
      'successFundTxn'    => $successFundTxn,
      'pendingFundAmount' => number_format($pendingFundAmount, 2),
      'pendingFundTxn'    => $pendingFundTxn,

      'totalCreditAmount' => number_format($totalCreditAmount, 2),
      'totalCreditTxn'    => $totalCreditTxn,

      'successPayoutAmount' => number_format($successPayoutAmount, 2),
      'successPayoutTxn'    => $successPayoutTxn,
      'pendingPayoutAmount' => number_format($pendingPayoutAmount, 2),
      'pendingPayoutTxn'    => $pendingPayoutTxn,
      'failedPayoutAmount'  => number_format($failedPayoutAmount, 2),
      'failedPayoutTxn'     => $failedPayoutTxn,

      'successPayinAmount' => number_format($successPayinAmount, 2),
      'successPayinTxn'    => $successPayinTxn,
      'pendingPayinAmount' => number_format($pendingPayinAmount, 2),
      'pendingPayinTxn'    => $pendingPayinTxn,
      // Add all other stats same way
    ]);
  }
}
