<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserApiKey;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
  public function generateAuthToken(Request $request)
  {
    $clientKey = trim((string) $request->header('client-key'));
    $clientSecret = trim((string) $request->header('client-secret'));
    $ip           = (string) $request->ip();

    // ✅ Validate headers
    if ($clientKey == '') {
      $this->writeAuthTokenLog($ip, $clientKey, false, null, null, 'Missing client Key header.');
      return response()->json(['status' => 'FAILED', 'message' => 'Missing client key header.'], 422);
    }

    if ($clientSecret == '') {
      $this->writeAuthTokenLog($ip, $clientSecret, false, null, null, 'Missing client secret header.');
      return response()->json(['status' => 'FAILED', 'message' => 'Missing client secret header.'], 422);
    }

    // ✅ Match keys with DB (encrypted in DB)
    $matched = UserApiKey::query()
      ->select(['id', 'user_id', 'client_key', 'client_secret', 'ip'])
      ->get()
      ->first(function ($row) use ($clientKey, $clientSecret) {
        try {
          return Crypt::decryptString((string) $row->client_key) === $clientKey
            && Crypt::decryptString((string) $row->client_secret) === $clientSecret;
        } catch (\Throwable $e) {
          return false;
        }
      });
    if (!$matched) {
      $this->writeAuthTokenLog($ip, $clientKey, false, null, null, 'Invalid client_key / client_secret.');
      return response()->json([
        'status'  => 'FAILED',
        'message' => 'Authentication failed. Invalid client_key / client_secret.',
      ], 401);
    }

    // ✅ Get user
    $user = User::find($matched->user_id);
    if (!$user) {
      $this->writeAuthTokenLog($ip, $clientKey, false, $matched->user_id, null, 'User not found.');
      return response()->json([
        'status'  => 'FAILED',
        'message' => 'Authentication failed. User not found.',
      ], 401);
    }

    // ✅ IP whitelist check
    $allowedIps = array_values(array_filter(array_map('trim', explode(',', (string) $matched->ip))));
    if (!in_array($ip, $allowedIps, true)) {
      $this->writeAuthTokenLog($ip, $clientKey, false, $user->id, null, 'IP not authorized.');
      return response()->json([
        'status'  => 'FAILED',
        'message' => 'Access denied. Your IP address is not authorized.' . $ip,
      ], 403);
    }

    // ✅ Generate JWT + set expiry to 60 mins
    $token = JWTAuth::fromUser($user);
    if (!$token) {
      $this->writeAuthTokenLog($ip, $clientKey, false, $user->id, null, 'Token generation failed.');
      return response()->json(['status' => 'FAILED', 'message' => 'Token generation failed.'], 500);
    }

    $expiry = Carbon::now('Asia/Kolkata')->addMinutes(60);

    // ✅ Save token & expiry
    $user->auth_token = $token;
    $user->auth_token_expiry = $expiry;
    $user->save();

    // ✅ Write log in: storage/logs/auth_token_YYYY-MM-DD.log
    $this->writeAuthTokenLog($ip, $clientKey, true, $user->id, $expiry, 'Token generated successfully.', $token);

    return response()->json([
      'status'     => 'SUCCESS',
      'token'      => $token,
      'token_expires_in' => $expiry->toDateTimeString(),
      'message'    => 'Token generated successfully.',
    ]);
  }

  /**
   * Logs to storage/logs/auth_token_YYYY-MM-DD.log
   */
  private function writeAuthTokenLog(
    string $ip,
    string $clientKey,
    bool $success,
    ?int $userId,
    ?Carbon $expiry,
    string $message,
    ?string $token = null
  ): void {
    $logDate = now()->format('Y-m-d');

    $payload = [
      'ip'         => $ip,
      'user_id'     => $userId,
      'client_key'  => $clientKey,
      'status'      => $success ? 'Success' : 'Failed',
      'message'     => $message,
      'expires_at'  => $expiry?->toDateTimeString(),
      // token masked for security
      'token_last6' => $token ? substr($token, -6) : null,
      'timestamp'   => now()->toDateTimeString(),
    ];

    Log::build([
      'driver' => 'single',
      'path'   => storage_path("logs/auth_token_{$logDate}.log"),
    ])->info('AUTH_TOKEN', $payload);
  }
}
