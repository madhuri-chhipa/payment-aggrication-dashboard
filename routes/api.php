<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\AuthController as ApiAuthController;
use App\Http\Controllers\api\TspPayoutApiController;
use App\Http\Controllers\api\PayoutApiController;
use App\Http\Controllers\api\PayinApiController;
use App\Http\Controllers\user\RazorpayVirtualController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/flipzik/payout-callback', [TspPayoutApiController::class, 'flipzikcallback']);
Route::post('/paywize/payout-callback', [TspPayoutApiController::class, 'paywizecallback']);
Route::post('/bulkpe/payout-callback', [TspPayoutApiController::class, 'bulkpecallback']);
Route::post('/buckbox/payout-callback', [TspPayoutApiController::class, 'buckboxcallback']);
Route::post('/runpaisa/payout-callback', [TspPayoutApiController::class, 'runpaisacallback']);
Route::post('/runpaisa/pg-callback', [PayinApiController::class, 'runpaisapgcallback']);
Route::any('/finkeda/payin-callback', [PayinApiController::class, 'finkedapgcallback']);
Route::any('/finkeda/pg/webhook', [PayinApiController::class, 'finkedapgwebhook']);
Route::post('/finkeda/payout-callback', [PayinApiController::class, 'finkedapayoutcallback']);
Route::post('/bluswap/payout-callback', [PayinApiController::class, 'bluswapcallback']);
Route::post('/razorpay/virtual/callback', [RazorpayVirtualController::class, 'virtualTxnCallback']);

Route::post('v1/createtoken', [ApiAuthController::class, 'generateAuthToken'])->name('api.generate.token');

//Api payin

Route::post('v1/create/orders', [PayinApiController::class, 'createPgOrder'])->name('payin-pgorder-create');

Route::post('v1/orders/status', [PayinApiController::class, 'PgOrderCheckStatus'])->name('payin-check-status');

//Api payout

Route::post('/v1/payouts', [PayoutApiController::class, 'payoutMoneyTransfer'])->name('payout-money-transfer');

Route::post('/v1/payouts/status', [PayoutApiController::class, 'payoutCheckstatus'])->name('payout-check-status');

Route::post('/v1/fetchbalance', [PayoutApiController::class, 'payoutcheckbalance'])->name('payout-check-balance');

//Tsp payout

Route::post('tsp/v1/payouts', [TspPayoutApiController::class, 'tsppayoutMoneyTransfer'])->name('tsppayout-money-transfer');

Route::post('tsp/v1/payouts/status', [TspPayoutApiController::class, 'tsppayoutCheckstatus'])->name('tsppayout-check-status');

Route::post('tsp/v1/fetchbalance', [TspPayoutApiController::class, 'tsppayoutcheckbalance'])->name('tsppayout-check-balance');