<?php

use App\Http\Controllers\admin\ApiLogController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\admin\auth\ForgotPasswordController;
use App\Http\Controllers\user\auth\ForgotPasswordController as UserForgotPasswordController;
use App\Http\Controllers\admin\DashboardController;
use App\Http\Controllers\admin\auth\LoginController as AdminLoginController;
use App\Http\Controllers\admin\auth\OtpController as AdminOtpController;
use App\Http\Controllers\admin\auth\ResetPasswordController;
use App\Http\Controllers\admin\BbpsTransactionController;
use App\Http\Controllers\admin\CompanyAccountController;
use App\Http\Controllers\admin\ComplaintReportController;
use App\Http\Controllers\admin\PayoutTransactionController;
use App\Http\Controllers\admin\FundRequestController as AdminFundRequestController;
use App\Http\Controllers\admin\PayinTransactionController;
use App\Http\Controllers\user\auth\ResetPasswordController as UserResetPasswordController;
use App\Http\Controllers\user\ComplaintReportController as UserComplaintReportController;
use App\Http\Controllers\admin\ReportController;
use App\Http\Controllers\admin\SubAdminController;
use App\Http\Controllers\admin\UserController;
use App\Http\Controllers\admin\UserLoginLogController;
use App\Http\Controllers\admin\WalletHistoryController;
use App\Http\Controllers\user\ComplaintController;
use App\Http\Controllers\user\auth\LoginController;
use App\Http\Controllers\user\auth\RegisterController;
use App\Http\Controllers\user\DashboardController as UserDashboardController;
use App\Http\Controllers\user\auth\OtpController;
use App\Http\Controllers\user\PayoutTransactionController as UserPayoutTransactionController;
use App\Http\Controllers\user\PayinTransactionController as UserPayinTransactionController;
use App\Http\Controllers\user\ApiLogController as UserApiLogController;
use App\Http\Controllers\user\BbpsTransactionController as UserBbpsTransactionController;
use App\Http\Controllers\pages\UserProfile;
use App\Http\Controllers\user\FundRequestController;
use App\Http\Controllers\user\ProfileController;
use App\Http\Controllers\user\UserWalletController;
use App\Http\Controllers\user\RazorpayVirtualController;
use App\Http\Controllers\user\UserBankAccountController;
use App\Http\Controllers\user\BbpsController;
use App\Http\Controllers\user\PayinServiceController;
use App\Http\Controllers\user\PayoutServiceController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

// pages
Route::get('/pages/profile-user', [UserProfile::class, 'index'])->name('pages-profile-user');

// my routes
// admin Routes
Route::middleware('redirect.auth.multi')->group(function () {
  Route::get('/crmlogin', [AdminLoginController::class, 'adminlogin'])->name('admin.auth.login');
  Route::get('/admin/login', [AdminLoginController::class, 'adminlogin'])->name('admin.auth.login');
  Route::get('/admin/otp-verify', [AdminOtpController::class, 'adminotpview'])->name('admin.auth.otp-view');
  Route::get('/admin/forgot-password', [ForgotPasswordController::class, 'index'])->name('admin.forgot-password');
  Route::get('/admin/reset-password', [ResetPasswordController::class, 'index'])->name('admin.reset-password');
});
Route::post('/admin/login', [AdminLoginController::class, 'logincheckadmin'])->name('admin.auth.login-submit');
Route::post('/admin/otp-verify', [AdminOtpController::class, 'adminotpsubmit'])->name('admin.auth.otp-submit');
Route::post('/admin/forgot-password', [ForgotPasswordController::class, 'sendOtp'])->name('admin.forgot-password.send');
Route::post('/admin/reset-password', [ResetPasswordController::class, 'reset'])->name('admin.reset-password.submit');

Route::middleware(['auth:admin'])->prefix('admin')->name('admin.')->group(function () {
  Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
  Route::get('/dashboard/data', [DashboardController::class, 'filterData'])
    ->name('dashboard.data');
  Route::get('/user', [UserController::class, 'index'])->name('user.list');
  Route::get('/user/create', [UserController::class, 'create'])->name('user.create');
  Route::post('/user', [UserController::class, 'store'])->name('user.store');
  Route::prefix('/user/{user}')->name('user.')->group(function () {
    Route::get('view', [UserController::class, 'show'])->name('view');
    Route::get('edit', [UserController::class, 'edit'])->name('edit');
    Route::post('update-basic', [UserController::class, 'updateBasic'])->name('update.basic');
    Route::post('update-company', [UserController::class, 'updateCompany'])->name('update.company');
    Route::post('update-director', [UserController::class, 'updateDirector'])->name('update.director');
    Route::post('update-services', [UserController::class, 'updateServices'])->name('update.services');
    Route::post('update-wallet', [UserController::class, 'updateWallet'])->name('update.wallet');
    Route::post('update-api', [UserController::class, 'updateApi'])->name('update.api');
    Route::post('api-keys/generate', [UserController::class, 'generateApiKeys'])->name('api.generate');
  });
  Route::post('/user/status/{id}', [UserController::class, 'toggleStatus'])
    ->name('user.toggle-status');
  Route::delete('/user/{id}', [UserController::class, 'destroy'])->name('user.destroy');
  Route::get('/user/data', [UserController::class, 'getUsersData'])->name('user.data');

  Route::get('/user-login-logs', [UserLoginLogController::class, 'index'])->name('userLoginLog.list');
  Route::get('/user-login-logs/data', [UserLoginLogController::class, 'data'])->name('userLoginLog.data');

  Route::get('sub-admins', [SubAdminController::class, 'index'])->name('subadmins.index');
  Route::get('sub-admins/data', [SubAdminController::class, 'data'])->name('subadmins.data');
  Route::post('sub-admins', [SubAdminController::class, 'store'])->name('subadmins.store');
  Route::get('sub-admins/{id}', [SubAdminController::class, 'edit'])->name('subadmins.edit');
  Route::put('sub-admins/{id}', [SubAdminController::class, 'update'])->name('subadmins.update');
  Route::post('/sub-admins/status/{id}', [SubAdminController::class, 'toggleStatus'])
    ->name('subadmins.toggle-status');
  Route::delete('sub-admins/{id}', [SubAdminController::class, 'destroy'])->name('subadmins.destroy');

  Route::get('/fund-requests', [AdminFundRequestController::class, 'fundRequestsList'])->name('fund-requests');
  Route::get('/fund-requests/data', [AdminFundRequestController::class, 'getFundRequests'])
    ->name('fund-requests.data');
  Route::post('/fund-request/store', [AdminFundRequestController::class, 'store'])
    ->name('fund-request.store');
  Route::get('/fund-request/details/{id}', [AdminFundRequestController::class, 'details'])->name('fund-request.details');
  Route::post('/fund-request/update-status', [AdminFundRequestController::class, 'updateStatus'])
    ->name('fund-request.updateStatus');

  Route::get('/wallet-history', [WalletHistoryController::class, 'WalletHistory'])->name('wallet-history');
  Route::get('/wallet-history/datatable', [WalletHistoryController::class, 'WalletHistoryDatatable'])
    ->name('wallet-history.datatable');

  Route::get('company-accounts', [CompanyAccountController::class, 'index'])->name('company-accounts.index');
  Route::get('company-accounts/data', [CompanyAccountController::class, 'data'])->name('company-accounts.data');
  Route::post('company-accounts', [CompanyAccountController::class, 'store'])->name('company-accounts.store');
  Route::get('company-accounts/{id}', [CompanyAccountController::class, 'edit'])->name('company-accounts.edit');
  Route::put('company-accounts/{id}', [CompanyAccountController::class, 'update'])->name('company-accounts.update');
  Route::post('/company-accounts/status/{id}', [CompanyAccountController::class, 'changeStatus'])
    ->name('company-accounts.toggle-status');
  Route::delete('company-accounts/{id}', [CompanyAccountController::class, 'destroy'])->name('company-accounts.destroy');


  Route::get('/report/payout-transactions', [PayoutTransactionController::class, 'payoutTxnLog'])->name('report.payout-transactions');
  Route::get('/report/payout-transactions/data', [PayoutTransactionController::class, 'getTransactionLogs'])
    ->name('payout-transactions.data');
  Route::get('/report/payout-transactions/show/{id}', [PayoutTransactionController::class, 'show'])
    ->name('payout-transactions.show');
  Route::get('/payout/transaction/{id}', [PayoutTransactionController::class, 'showAjax']);
  Route::get('/report/bank-statement', [PayoutTransactionController::class, 'getBankStatement'])
    ->name('report.bank_statement');

  Route::get('/report/bank-statement/excel', [PayoutTransactionController::class, 'exportBankStatementExcel'])
    ->name('report.bank_statement_excel');

  Route::get('/report/bank-statement/pdf', [PayoutTransactionController::class, 'exportBankStatementPdf'])
    ->name('report.bank_statement_pdf');

  Route::post('/payout/check-status', [PayoutTransactionController::class, 'ajaxCheckStatus'])
    ->name('payout.check_status');

  Route::get('/report/payin-transactions', [PayinTransactionController::class, 'payinTxnLog'])->name('report.payin-transactions');
  Route::get('/report/payin-transactions/data', [PayinTransactionController::class, 'getTransactionLogs'])
    ->name('payin-transactions.data');
  Route::get('/report/payin-transactions/show/{id}', [PayinTransactionController::class, 'show'])
    ->name('payin-transactions.show');
  Route::get('/payin/transaction/{id}', [PayinTransactionController::class, 'showAjax']);

  Route::get('/report/api-logs', [ApiLogController::class, 'apiLog'])->name('report.api-logs');
  Route::get('/report/api-logs/data', [ApiLogController::class, 'getApiLogs'])
    ->name('api-logs.data');
  Route::get('/report/api-logs/show/{id}', [ApiLogController::class, 'show'])
    ->name('report.api-logs.show');
  Route::get('/api-logs/{id}', [ApiLogController::class, 'showAjax']);

  Route::get('/report/complaints', [ComplaintReportController::class, 'complaintLog'])->name('report.complaints');
  Route::get('/report/complaints/data', [ComplaintReportController::class, 'getComplaintLogs'])
    ->name('complaints.data');

  Route::get('/report/bbps-transactions', [BbpsTransactionController::class, 'bbpsTxnLog'])->name('report.bbps-transactions');
  Route::get('/report/bbps-transactions/data', [BbpsTransactionController::class, 'getBbpsTransactions'])
    ->name('bbps-transactions.data');
  Route::get('/report/bbps-transactions/show/{id}', [BbpsTransactionController::class, 'show'])
    ->name('report.bbps-transactions.show');
  Route::get('/bbps/transaction/{id}', [BbpsTransactionController::class, 'showAjax']);
  Route::get('/transactions/print/{id}', [BbpsTransactionController::class, 'printTxn'])
    ->name('transactions.print');

  Route::get('/complaint/{id}', [ComplaintReportController::class, 'showAjax'])
    ->name('complaint.view');
  Route::get('/complaint-track/{id}', [ComplaintReportController::class, 'track'])->name('complaint.track');
  Route::get('/bbps/txn-status/{id}', [BbpsTransactionController::class, 'checkStatus']);
  // Route::get('/report/payin-transactions', [ReportController::class, 'index'])->name('user-add');
  // Route::get('/report/virtual-transactions', [ReportController::class, 'index'])->name('user-add');
  Route::post('/logout', [AdminLoginController::class, 'logout'])->name('auth.logout');
});
// user decrypt doc route
Route::get('/secure-doc/{path}', [UserController::class, 'viewDocument'])
  ->name('secure.doc');

// User Routes
Route::middleware('redirect.auth.multi')->group(function () {
  Route::get('/', [LoginController::class, 'index'])->name('userlogin');
  Route::get('/login', [LoginController::class, 'index'])->name('login');
  Route::get('/userotp-verify', [OtpController::class, 'userotpview'])->name('auth.userotp.view');
  Route::get('/register', [RegisterController::class, 'index'])->name('user-register');
  Route::get('/forgot-password', [UserForgotPasswordController::class, 'index'])->name('forgot-password');
  Route::get('/reset-password', [UserResetPasswordController::class, 'index'])->name('reset-password');
});
Route::post('/logincheckuser', [LoginController::class, 'logincheckuser'])->name('logincheckuser');
Route::post('/register/store', [RegisterController::class, 'store'])->name('user-register.store');
Route::post('/userotp-verify', [OtpController::class, 'userotpsubmit'])->name('auth.userotp.submit');
Route::post('/resend-login-otp', [OtpController::class, 'resendLoginOtp'])->name('auth.resend.login.otp');
Route::post('/forgot-password', [UserForgotPasswordController::class, 'sendOtp'])->name('forgot-password.send');
Route::post('/reset-password', [UserResetPasswordController::class, 'reset'])->name('reset-password.submit');

Route::middleware(['auth:user'])->group(function () {
  Route::get('/dashboard', [UserDashboardController::class, 'index'])->name('dashboard');
  Route::get('/dashboard/data', [UserDashboardController::class, 'filterData'])
    ->name('dashboard.data');
  Route::get('/profile', [ProfileController::class, 'index'])
    ->name('profile');
  Route::get('/user-wallet', [UserWalletController::class, 'index'])
    ->name('user-wallet');
  Route::post('/create-virtual-wallet', [RazorpayVirtualController::class, 'createVirtualAccount'])
    ->name('create.user.vwallet');
  Route::post('/refresh-virtual-balance', [UserWalletController::class, 'refreshVirtualBalance'])
    ->name('virtual.wallet.refresh');

  Route::get('/report/payout-transactions', [UserPayoutTransactionController::class, 'payoutTxnLog'])->name('report.payout-transactions');
  Route::get('/report/payout-transactions/data', [UserPayoutTransactionController::class, 'getTransactionLogs'])
    ->name('payout-transactions.data');
  Route::get('/report/payout-transactions/show/{id}', [UserPayoutTransactionController::class, 'show'])
    ->name('report.payout-transactions.show');
  Route::get('/payout/transaction/{id}', [UserPayoutTransactionController::class, 'showAjax']);

  Route::get('/report/payin-transactions', [UserPayinTransactionController::class, 'payinTxnLog'])->name('report.payin-transactions');
  Route::get('/report/payin-transactions/data', [UserPayinTransactionController::class, 'getTransactionLogs'])
    ->name('payin-transactions.data');
  Route::get('/report/payin-transactions/show/{id}', [UserPayinTransactionController::class, 'show'])
    ->name('report.payin-transactions.show');
  Route::get('/payin/transaction/{id}', [UserPayinTransactionController::class, 'showAjax']);

  Route::get('/report/api-logs', [UserApiLogController::class, 'apiLog'])->name('report.api-logs');
  Route::get('/report/api-logs/data', [UserApiLogController::class, 'getApiLogs'])
    ->name('api-logs.data');
  Route::get('/report/api-logs/show/{id}', [UserApiLogController::class, 'show'])
    ->name('report.api-logs.show');
  Route::get('/api-logs/{id}', [UserApiLogController::class, 'showAjax']);


  // User Bank Account
  Route::get('user-bank-accounts', [UserBankAccountController::class, 'index'])->name('user-bank-accounts.index');
  Route::get('user-bank-accounts/data', [UserBankAccountController::class, 'data'])->name('user-bank-accounts.data');
  Route::post('user-bank-accounts', [UserBankAccountController::class, 'store'])->name('user-bank-accounts.store');
  Route::get('user-bank-accounts/{id}', [UserBankAccountController::class, 'edit'])->name('user-bank-accounts.edit');
  Route::put('user-bank-accounts/{id}', [UserBankAccountController::class, 'update'])->name('user-bank-accounts.update');
  Route::post('/user-bank-accounts/status/{id}', [UserBankAccountController::class, 'changeStatus'])
    ->name('user-bank-accounts.toggle-status');
  Route::delete('user-bank-accounts/{id}', [UserBankAccountController::class, 'destroy'])->name('user-bank-accounts.destroy');

  //Virtual Credit History
  Route::post('/user-wallet/datatables', [UserWalletController::class, 'datatables'])
    ->name('user.wallet.datatables');

  Route::get('/user-wallet/export/csv', [UserWalletController::class, 'exportCsv'])
    ->name('user.wallet.export.csv');

  Route::get('/user-wallet/export/xlsx', [UserWalletController::class, 'exportXlsx'])
    ->name('user.wallet.export.xlsx');

  //Virtual Wallet History
  Route::get('/wallet-history', [UserWalletController::class, 'WalletHistory'])->name('wallet-history');
  Route::get('/wallet-history/datatable', [UserWalletController::class, 'WalletHistoryDatatable'])
    ->name('wallet-history.datatable');

  // Fund Request
  Route::get('/fund-requests', [FundRequestController::class, 'fundRequestsList'])->name('fund-requests');
  Route::get('/fund-requests/data', [FundRequestController::class, 'getFundRequests'])
    ->name('fund-requests.data');
  Route::post('/fund-request/store', [FundRequestController::class, 'store'])
    ->name('fund-request.store');
  Route::get('/fund-request/details/{id}', [FundRequestController::class, 'details'])->name('fund-request.details');

  Route::get('/wallet-history/export', [UserWalletController::class, 'WalletHistoryExport'])
    ->name('wallet.history.export');

  Route::get('/document/{path}', [ProfileController::class, 'viewDocument'])
    ->name('profile.doc');
  Route::post('update-api', [ProfileController::class, 'updateApi'])->name('update.api');
  Route::post('update-password', [ProfileController::class, 'updatePassword'])->name('update.password');
  Route::post('api-keys/generate', [ProfileController::class, 'generateApiKeys'])->name('api.generate');
  Route::post('/logout', [LoginController::class, 'logout'])->name('user.logout');

  // services
  Route::get('/service/bill-payment', [BbpsController::class, 'billPayment'])
    ->name('service.bill-payment');
  Route::get('/service/bill-payment/biller-info/{id}', [BbpsController::class, 'billerInfo'])->name('service.bill-payment.biller-info');
  Route::post('/service/bill-payment/bill-fetch', [BbpsController::class, 'billFetch'])
    ->name('service.bill-payment.bill-fetch');
  Route::get('/service/bill-payment/validate-bill/{id}', [BbpsController::class, 'validateBill'])
    ->name('service.bill-payment.validate-bill');
  Route::get('/service/bill-payment/view-plans/{id}', [BbpsController::class, 'viewPlans'])
    ->name('service.bill-payment.view-plans');
  Route::post('/service/bill-quickpayment', [BbpsController::class, 'QuickbillPay'])
    ->name('service.bill.quickpay');
  Route::post('/service/bill-payment', [BbpsController::class, 'billPay'])
    ->name('service.bill.pay');
  Route::post('/service/bill-payment/validate/{id}', [BbpsController::class, 'validateBill'])->name('service.bill.validate');

  Route::get('/service/bill-payment/{category}', [BbpsController::class, 'fetchBillers'])
    ->name('service.bill-payment.category');

  Route::get('/complaint/create', [ComplaintController::class, 'create'])
    ->name('complaint.create');
  Route::post('/complaint/register', [ComplaintController::class, 'register'])->name('complaint.register');
  Route::get('/complaint/track', function () {
    return view('user.services.complaint_track');
  })->name('complaint.track.page');
  Route::post('/complaint/track', [ComplaintController::class, 'track'])
    ->name('complaint.track');

  Route::get('/report/complaints', [UserComplaintReportController::class, 'complaintLog'])->name('report.complaints');
  Route::get('/report/complaints/data', [UserComplaintReportController::class, 'getComplaintLogs'])
    ->name('complaints.data');
  Route::get('/complaint/{id}', [UserComplaintReportController::class, 'showAjax'])
    ->name('complaint.view');

  Route::get('/service/load-money', [PayinServiceController::class, 'payinservice'])
    ->name('service.load-money');
  Route::post('/service/load-money/submit', [PayinServiceController::class, 'payinsubmit'])
    ->name('service.payin.submit');
  Route::post('/service/payin/check-status', [PayinServiceController::class, 'checkPaymentStatus'])
    ->name('service.payin.check-status');

  Route::get('/service/payout', [PayoutServiceController::class, 'payoutservice'])
    ->name('service.payout');
  Route::post('/service/payout/submit', [PayoutServiceController::class, 'payoutsubmit'])
    ->name('service.payout.submit');
  Route::post('/service/payout/check-status', [PayoutServiceController::class, 'checkPaymentStatus'])
    ->name('service.payout.check-status');

  Route::get('/report/bbps-transactions', [UserBbpsTransactionController::class, 'bbpsTxnLog'])->name('report.bbps-transactions');
  Route::get('/report/bbps-transactions/data', [UserBbpsTransactionController::class, 'getBbpsTransactions'])
    ->name('bbps-transactions.data');
  Route::get('/report/bbps-transactions/show/{id}', [UserBbpsTransactionController::class, 'show'])
    ->name('report.bbps-transactions.show');
  Route::get('/bbps/transaction/{id}', [UserBbpsTransactionController::class, 'showAjax']);
  Route::get('/transactions/print/{id}', [UserBbpsTransactionController::class, 'printTxn'])
    ->name('transactions.print');
  Route::get('/transaction/check-status', function () {
    return view('user.services.bbps_transaction_check_status');
  })->name('transactions.check-status-page');
  Route::get('/transaction/search-ref', [UserBbpsTransactionController::class, 'searchTxnRef'])
    ->name('transactions.search-ref');
  Route::post('/transaction/check-status', [UserBbpsTransactionController::class, 'checkTransactionStatus'])
    ->name('transactions.check-status');
});
Route::get('/clear', function () {
  Artisan::call('config:clear');
  Artisan::call('cache:clear');
  Artisan::call('optimize:clear');
  Artisan::call('view:clear');
  Artisan::call('route:clear');
  return 'All Cleared';
});
Route::get('/log-test', function () {
  Log::info('BBPS LOG WORKING');
  return 'OK';
});
Route::get('/force-log', function () {
  file_put_contents(storage_path('logs/test.txt'), 'TEST WRITE');
  return 'done';
});
