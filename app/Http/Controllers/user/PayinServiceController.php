<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\PayinTransaction;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\RunpaisaPayinService;
use App\Services\FinkedaPayinService;

class PayinServiceController extends Controller
{
  protected $runpaisaService;
  protected $finkedaService;

  public function __construct(RunpaisaPayinService $runpaisaService, FinkedaPayinService $finkedaService)
  {
    $this->runpaisaService = $runpaisaService;
    $this->finkedaService = $finkedaService;
  }
  public function payinservice()
  {
    return view('user.services.load_money');
  }
  public function payinsubmit(Request $request)
  {
    $request->validate([
      'amount' => 'required|integer|min:1',
      'payer_name' => 'required|string|max:100',
      'transfer_mode' => 'required|in:PG',
      'transfer_email' => 'nullable|email',
      'transfer_phone_number' => 'nullable|digits:10',
      'reqLong' => 'required|string',
      'reqLat' => 'required|string',
      'cardType' => 'required|string',
      'cardNetwork' => 'required|string',
    ]);

    $user = User::find($request->user_id ?? Auth::id());
    if (!$user) {
      return response()->json([
        'status' => false,
        'message' => 'User not found.',
      ]);
    }
    $id = $user->id;
    $ip = request()->ip();
    $userServices = UserService::where('user_id', $id)->first();
    if (!$userServices) {
      return response()->json(['status' => false, 'message' => 'Service settings not found for user.']);
    }
    $refid = $this->generateFinkedaUrn();
    if (is_a($refid, \Illuminate\Http\JsonResponse::class)) {
      return $refid;
    }
    $amount            = $request->input('amount');
    $name  = $request->input('payer_name');
    $mobile            = $request->input('transfer_phone_number');
    $email             = $request->input('transfer_email');
    $name             = $request->input('payer_name');
    $payment_mode      = 'PG';
    $active_api        = $userServices->active_payin_api;
    PayinTransaction::create([
      'user_id'          => $user->id,
      'txn_id'           => $refid,
      'payer_name'    => $name,
      'transfer_mode'    => $payment_mode,
      'amount'           => $amount,
      'charge_amount'    => 0.00,
      'gst_amount'       => 0.00,
      'total_charge'     => 0.00,
      'total_amount'     => $amount,
      'status'           => 'P',
      'payment_status'   => 'P',
      'response_message' => 'Transaction initiated',
      'description'      => 'Transaction initiated',
      'created_by'       => 'API',
      'updated_by'       => 'API',
      'api'              => $active_api,
      'ip'               => request()->ip(),
      'created_by'       => $user->id ?? 0,
      'updated_by'       => $user->id ?? 0,
      'created_at'       => now(),
      'updated_at'       => now(),
    ]);
    if ($active_api === 'Runpaisa') {
      $api_response = $this->runpaisaService->initiateTransactionPG($refid, $amount, $mobile, $email, $name);
    } else if ($active_api === 'Finkeda') {
      $api_response = $this->finkedaService->initiateTransactionPG(
        $refid,
        $amount,
        $request->cardType,
        $request->cardNetwork,
        $request->reqLong,
        $request->reqLat,
        'WEB'
      );
    } else {
      return response()->json(['status' => 'failed', 'message' => 'Unsupported API Please contact to Admin'], 400);
    }
    $status          = strtoupper($api_response['status'] ?? 'F');
    $responseMessage = $api_response['message'] ?? 'Transaction processed';
    $paymentUrl      = $api_response['payment_url'] ?? null;
    $api_txn_id      =  $api_response['api_txn_id'] ?? null;
    $updateData = [
      'api_txn_id'       => $api_txn_id,
      'payment_link'      => $paymentUrl,
      'updated_at'       => now(),
      'response_message' => $responseMessage,
    ];
    if ($status === 'F') {
      $updateData['status']       = 'F';
      $updateData['description']  = $responseMessage ?? 'Transaction failed by payment gateway';
    }
    DB::table('payin_transactions')
      ->where('txn_id', $refid)
      ->update($updateData);
    $statusCode = match ($status) {
      'S' => 200,
      'F' => 400,
      default => 400
    };
    return response()->json([
      'success'       => true,
      'status'        => match ($status) {
        'S' => 'success',
        'F' => 'failed',
        default => 'failed',
      },
      'message'       => $responseMessage,
      'status_code'   => $statusCode,
      'data'          => [
        'transaction_id' => $refid,
        'reference_id'   => $api_txn_id,
        'payment_url'    => $paymentUrl,
        'transfer_mode'  => $payment_mode,
        'timestamp'      => now()->toDateTimeString(),
        'gateway'       => $active_api
      ]
    ], $statusCode);
  }
  private function generateFinkedaUrn(): string
  {
    do {
      $urn = now()->format('ymdHis') . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
      $urn = substr($urn, 0, 17);

      $exists = PayinTransaction::where('txn_id', $urn)->exists();
    } while ($exists);

    return $urn;
  }
  public function checkPaymentStatus(Request $request)
  {
    $txnId = $request->txn_id;

    $transaction = PayinTransaction::where('txn_id', $txnId)->first();

    if (!$transaction) {
      return response()->json([
        'success' => false,
        'message' => 'Transaction not found'
      ]);
    }

    return response()->json([
      'success'        => true,
      'payment_status' => $transaction->payment_status,
      'status'         => $transaction->status,
      'utr'            => $transaction->utr,
      'message'        => $transaction->response_message,
    ]);
  }
}
