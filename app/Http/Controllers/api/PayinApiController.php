<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\PayinTransaction;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserService;
use App\Models\UserApiKey;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use App\Services\RunpaisaPayinService;
use App\Services\FinkedaPayinService;
use Illuminate\Support\Facades\Log;

class PayinApiController extends Controller
{
  protected $runpaisaService;
  protected $finkedaService;

  public function __construct(RunpaisaPayinService $runpaisaService, FinkedaPayinService $finkedaService)
  {
    $this->runpaisaService = $runpaisaService;
    $this->finkedaService = $finkedaService;
  }
  public function runpaisapgcallback(Request $request)
  {
    return $this->runpaisaService->handlePayinCallback();
  }
  public function finkedapgcallback(Request $request)
  {
    return $this->finkedaService->handlePayinRedirection();
  }
  public function finkedapgwebhook(Request $request)
  {
    return $this->finkedaService->handlePayinCallback();
  }
  public function createPgOrder(Request $request)
  {
    $user = $this->authenticateRequest($request);
    if ($user instanceof \Illuminate\Http\JsonResponse) return $user;
    if (!$user) return response()->json(['status' => 'FAILED', 'message' => 'Please contact admin.'], 400);

    $user_id = $user->id;

    $userApis = UserService::where('user_id', $user_id)->first();
    if (!$userApis || $userApis->payin_status === 'B' || $user->active != 'A') {
      return response()->json(['status' => 'FAILED', 'message' => 'API is disabled. Please contact admin.'], 400);
    }

    $refid = $request->input('txn_id');
    if (empty($refid) || !preg_match('/^.{17,20}$/', $refid)) {
      return response()->json([
        'status' => 'FAILED',
        'message' => 'Reference ID must be 17–20 characters.'
      ], 400);
    }

    $fieldLabels = [
      'reqLat'            => 'Latitude',
      'reqLong'            => 'Longitude',
      'customer_mobile'         => 'Customer Mobile number',
      'customer_email'          => 'Customer Email address',
      'customer_name'           => 'Customer Full name',
      'amount'              => 'Amount',
    ];
    $requiredFields = array_keys($fieldLabels);

    foreach ($requiredFields as $field) {
      if (empty($request->input($field))) {
        return response()->json([
          'status'  => 'FAILED',
          'message' => $fieldLabels[$field] .
            ' is required. Please provide a valid value in "' .
            $field . '".'
        ], 400);
      }
    }

    if (PayinTransaction::where('txn_id', $refid)->exists()) {
      return response()->json(['status' => 'FAILED', 'message' => 'Reference ID already exists. Please provide unique reference ID.'], 400);
    }

    $amount            = $request->input('amount');
    if (!is_numeric($amount) || $amount < 100 || $amount > 49999) {
      return response()->json(['status' => 'FAILED', 'message' => 'Amount must be between ₹100 and ₹49,999.'], 400);
    }
    $name  = $request->input('payer_name');
    $reqLat  = $request->input('reqLat') ?: '26.273987709800267';
    $reqLong  = $request->input('reqLong') ?: '73.00436536810876';
    $mobile            = $request->input('mobile');
    $email             = $request->input('email');
    $cardType = $request->input('cardType') ?: '';
    $cardNetwork = $request->input('cardNetwork') ?: '';
    $payment_mode = $cardType ? 'CARD' : 'UPI';
    $active_api        = $userApis->active_payin_api;
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
        $cardType,
        $cardNetwork,
        $reqLong,
        $reqLat,
        'WEB'
      );
    } else {
      return response()->json(['status' => 'FAILED', 'message' => 'Unsupported API Please contact to Admin'], 400);
    }
    $status          = strtoupper($api_response['status'] ?? 'F');
    $responseMessage = $api_response['message'] ?? 'Transaction processed';
    $paymentUrl      = $api_response['payment_url'] ?? null;
    $api_txn_id      =  null;
    $updateData = [
      'api_txn_id'       => $api_txn_id,
      'payment_link'      => $paymentUrl,
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
    $pgstatus      = match ($status) {
      'S' => 'SUCCESS',
      'F' => 'FAILED',
      default => 'FAILED',
    };
    return response()->json([
      'status'  => 'SUCCESS',
      'message'       => $responseMessage,
      'status_code'   => $statusCode,
      'data'          => [
        'pgstatus'      => $pgstatus,
        'transaction_id' => $refid,
        'reference_id'   => $api_txn_id,
        'payment_url'    => $paymentUrl,
        'transfer_mode'  => $payment_mode,
        'timestamp'      => now()->toDateTimeString(),
      ]
    ], $statusCode);
  }
  public function PgOrderCheckStatus(Request $request)
  {
    $user = $this->authenticateRequest($request);
    if ($user instanceof \Illuminate\Http\JsonResponse) return $user;

    if (!$user) {
      return response()->json([
        'status'       => 'FAILED',
        'message' => 'User not found. Please contact admin.',
      ], 400);
    }

    $user_id = $user->id;
    $userApis = UserService::where('user_id', $user_id)->first();
    if (!$userApis || $userApis->payin_status === 'B' || $user->active != 'A') {
      return response()->json([
        'status'       => 'FAILED',
        'message' => 'Payin API is disabled. Please contact admin.',
      ], 400);
    }

    $refid = $request->input('refid');
    $payin = PayinTransaction::where('txn_id', $refid)->first();
    if (!$payin) {
      return response()->json([
        'status'       => 'FAILED',
        'message' => 'Transaction not found.',
      ], 404);
    }

    $status = $payin->status; // S, F, P, R
    $statusText = match ($status) {
      'S' => 'SUCCESS',
      'F', 'R' => 'FAILED',
      'P' => 'PENDING',
      default => 'PENDING',
    };

    $statusCode = match ($status) {
      'S' => 200,
      'P' => 201,
      'F', 'R' => 400,
      default => 400,
    };

    return response()->json([
      'status'       => 'SUCCESS',
      'message'       => $payin->response_message ?? 'Transaction status fetched successfully.',
      'status_code'   => $statusCode,
      'data'          => [
        'pgstatus'        => $statusText,
        'transaction_id' => $payin->txn_id,
        'reference_id'   => $payin->api_txn_id,
        'utr'            => $payin->utr,
        'amount'         => $payin->amount,
        'transfer_mode'  => 'PG',
        'timestamp'      => now()->toDateTimeString(),
      ]
    ], $statusCode);
  }


  private function authenticateRequest(Request $request)
  {
    $clientSecret = trim((string) $request->header('client-secret'));
    $authHeader   = trim((string) $request->header('Authorization'));
    $ip           = (string) $request->ip();

    if ($clientSecret === '') {
      return response()->json([
        'status'  => 'FAILED',
        'message' => 'Missing client-secret header.',
      ], 401);
    }

    if ($authHeader === '') {
      return response()->json([
        'status'  => 'FAILED',
        'message' => 'Missing Authorization header.',
      ], 401);
    }

    if (!str_starts_with($authHeader, 'Bearer ')) {
      return response()->json([
        'status'  => 'FAILED',
        'message' => 'Invalid Authorization format. Expected: Bearer {token}.',
      ], 401);
    }

    $token = trim(substr($authHeader, 7));
    if ($token === '') {
      return response()->json([
        'status'  => 'FAILED',
        'message' => 'Authorization token is empty.',
      ], 401);
    }
    $apiKeyRow = UserApiKey::query()
      ->select(['id', 'user_id', 'client_secret', 'ip'])
      ->get()
      ->first(function ($row) use ($clientSecret) {
        try {
          return Crypt::decryptString((string) $row->client_secret) === $clientSecret;
        } catch (\Throwable $e) {
          return false;
        }
      });

    if (!$apiKeyRow) {
      return response()->json([
        'status'  => 'FAILED',
        'message' => 'Invalid Client Secret.',
      ], 401);
    }
    $user = User::find($apiKeyRow->user_id);
    if (!$user) {
      return response()->json([
        'status'  => 'FAILED',
        'message' => 'User not found.',
      ], 401);
    }
    if ($user->auth_token !== $token) {
      return response()->json([
        'status'  => 'FAILED',
        'message' => 'Invalid or mismatched authorization token.',
      ], 401);
    }

    if ($user->auth_token_expiry && now()->greaterThan($user->auth_token_expiry)) {
      return response()->json([
        'status'  => 'FAILED',
        'message' => 'Authorization token has expired. Please re-authenticate.',
      ], 401);
    }

    $allowedIps = array_values(array_filter(array_map('trim', explode(',', (string) $apiKeyRow->ip))));
    if (!in_array($ip, $allowedIps, true)) {
      return response()->json([
        'status'  => 'FAILED',
        'message' => 'Access denied. Your IP address is not authorized.',
      ], 403);
    }
    return $user;
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

      $wallet_balance = $user->wallet_balance;
      $total_charge = $charge_amount + $gst_amount;
      $total_amount = $amount + $total_charge;

      $opening_balance = $wallet_balance;
      $closing_balance = $opening_balance + $total_amount;

      DB::table('user_wallet_transactions')->insert([
        'user_id'         => $user->id,
        'service_name'    => 'PAYINAPI',
        'opening_balance' => $opening_balance,
        'total_charge'    => $total_charge,
        'amount'          => $amount,
        'total_amount'    => $total_amount,
        'closing_balance' => $closing_balance,
        'credit'          => $total_amount,
        'debit'           => 0.00,
        'description'     => $user->uid . '/PG/' . $refid,
        'created_at'      => now(),
        'updated_at'      => now(),
      ]);

      DB::table('users')
        ->where('id', $user->id)
        ->update([
          'wallet_balance' => $closing_balance,
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
}
