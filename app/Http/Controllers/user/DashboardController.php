<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\FundRequest;
use App\Models\PayinTransaction;
use App\Models\PayoutTransaction;
use App\Models\UserLoginLog;
use App\Models\UserWalletTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
  public function index(Request $request)
  {
    $user = Auth::user(); // logged-in user

    // =========================
    // USER BALANCES
    // =========================
    $virtualBalance = $user->virtual_balance;
    $payinBalance   = $user->payin_balance;
    $payoutBalance  = $user->payout_balance;
    $reserveBalance = $user->reserve_balance;
    $freezeBalance  = $user->freeze_balance;

    // =========================
    // USER LOGIN LOGS (Only this user)
    // =========================
    $logs = UserLoginLog::where('user_id', $user->id)
      ->orderByDesc('logged_at')
      ->limit(10)
      ->get();

    return view('user.dashboard', compact(
      'user',
      'virtualBalance',
      'payinBalance',
      'payoutBalance',
      'reserveBalance',
      'freezeBalance',
      'logs'
    ));
  }
  public function filterData(Request $request)
  {
    $userId = auth()->id(); // 👈 important change

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
    // FUND REQUEST STATS (User Only)
    // =========================
    $successFundAmount = FundRequest::where('user_id', $userId)
      ->where('status', 'A')
      ->whereBetween('created_at', [$from, $to])
      ->sum('amount');

    $successFundTxn = FundRequest::where('user_id', $userId)
      ->where('status', 'A')
      ->whereBetween('created_at', [$from, $to])
      ->count();

    $pendingFundAmount = FundRequest::where('user_id', $userId)
      ->where('status', 'P')
      ->whereBetween('created_at', [$from, $to])
      ->sum('amount');

    $pendingFundTxn = FundRequest::where('user_id', $userId)
      ->where('status', 'P')
      ->whereBetween('created_at', [$from, $to])
      ->count();

    // =========================
    // TOTAL CREDIT (User Wallet Logs)
    // =========================
    $totalCreditAmount = UserWalletTransaction::where('user_id', $userId)
      ->whereBetween('created_at', [$from, $to])
      ->where('service_name', 'CREDIT')   // ✅ added
      ->sum('credit');

    $totalCreditTxn = UserWalletTransaction::where('user_id', $userId)
      ->whereBetween('created_at', [$from, $to])
      ->where('service_name', 'CREDIT')   // ✅ added
      ->where('credit', '>', 0)
      ->count();

    // =========================
    // PAYOUT STATS (User Only)
    // =========================
    $successPayoutAmount = PayoutTransaction::where('user_id', $userId)
      ->where('status', 'S')
      ->whereBetween('created_at', [$from, $to])
      ->sum('total_amount');

    $successPayoutTxn = PayoutTransaction::where('user_id', $userId)
      ->where('status', 'S')
      ->whereBetween('created_at', [$from, $to])
      ->count();

    $pendingPayoutAmount = PayoutTransaction::where('user_id', $userId)
      ->where('status', 'P')
      ->whereBetween('created_at', [$from, $to])
      ->sum('total_amount');

    $pendingPayoutTxn = PayoutTransaction::where('user_id', $userId)
      ->where('status', 'P')
      ->whereBetween('created_at', [$from, $to])
      ->count();

    $failedPayoutAmount = PayoutTransaction::where('user_id', $userId)
      ->where('status', 'R')
      ->whereBetween('created_at', [$from, $to])
      ->sum('total_amount');

    $failedPayoutTxn = PayoutTransaction::where('user_id', $userId)
      ->where('status', 'R')
      ->whereBetween('created_at', [$from, $to])
      ->count();

    // =========================
    // PAYIN STATS (User Only)
    // =========================
    $successPayinAmount = PayinTransaction::where('user_id', $userId)
      ->where('status', 'S')
      ->whereBetween('created_at', [$from, $to])
      ->sum('total_amount');

    $successPayinTxn = PayinTransaction::where('user_id', $userId)
      ->where('status', 'S')
      ->whereBetween('created_at', [$from, $to])
      ->count();

    $pendingPayinAmount = PayinTransaction::where('user_id', $userId)
      ->where('status', 'P')
      ->whereBetween('created_at', [$from, $to])
      ->sum('total_amount');

    $pendingPayinTxn = PayinTransaction::where('user_id', $userId)
      ->where('status', 'P')
      ->whereBetween('created_at', [$from, $to])
      ->count();

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
    ]);
  }
}
