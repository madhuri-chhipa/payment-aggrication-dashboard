<?php

namespace App\Http\Controllers\admin\auth;

use App\Http\Controllers\Controller;
use App\Jobs\SendLoginOtpJob;
use App\Jobs\SendSmsOtpJob;
use App\Models\Admin;
use App\Models\AdminLoginLog;
use Illuminate\Http\Request;
use App\Models\AdminDetail;
use App\Models\User;
use App\Models\UserDetail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OtpController extends Controller
{
  /**
   * Display the OTP verification page.
   */
  public function adminotpview()
  {
    $pageConfigs = ['myLayout' => 'blank'];
    return view('admin.auth.otp-view', ['pageConfigs' => $pageConfigs]);
  }

  /**
   * Handle OTP submission.
   */
  public function adminotpsubmit(Request $request)
  {
    $request->validate([
      'otp' => 'required',
      'latitude' => 'nullable',
      'longitude' => 'nullable',
    ]);
    $latitude = $request->latitude;
    $longitude = $request->longitude;
    $otp = $request->input('otp');
    $adminMobile = session('otp_admin_mobile');
    if (!$adminMobile) {
      return redirect()->route('admin.login')
        ->withErrors(['otp' => 'Session expired. Please login again.']);
    }

    $admin = Admin::where('mobile_number', $adminMobile)->first();
    if (!$admin || !$admin->login_otp || !$admin->login_otp_expires_at || $admin->login_otp_expires_at < now() || !Hash::check($request->otp, $admin->login_otp)) {
      return redirect()->back()
        ->withErrors(['otp' => 'Invalid or expired OTP, Please try again.']);
    }
    // ✅ Save login log
    AdminLoginLog::create([
      'admin_id' => $admin->id,
      'otp' => $otp,
      'ip_address' => $request->ip(),
      'latitude' => $latitude
        ? Crypt::encryptString($latitude)
        : null,
      'longitude' => $longitude
        ? Crypt::encryptString($longitude)
        : null,
      'logged_at' => now(),
    ]);
    $admin->update(['login_otp' => null, 'login_otp_expires_at' => null]);
    Auth::guard('admin')->login($admin);
    $request->session()->forget('otp_admin_mobile');
    $request->session()->regenerate();
    return redirect()->route('admin.dashboard')->with('success', 'Login successful');
  }

  public function resendLoginOtp(Request $request)
  {
    $adminMobile = session('otp_admin_mobile');
    if (!$adminMobile) {
      return redirect()->route('login')
        ->withErrors(['error' => 'Session expired. Please login again.']);
    }

    $admin = Admin::where('mobile_number', $adminMobile)->first();
    if (!$admin) {
      return response()->json([
        'status' => false,
        'message' => 'Admin not found'
      ]);
    }

    // ✅ Prevent spam (allow resend only after 60 sec)
    if ($admin->login_otp_expires_at && now()->diffInSeconds($admin->login_otp_expires_at, false) > 540) {
      return response()->json([
        'status' => false,
        'message' => 'Please wait before requesting OTP again'
      ]);
    }

    // ✅ Generate OTP
    $otp = rand(1000, 9999);
    $email = $admin->email;
    $mobile = $admin->mobile_number;
    $name = $admin->name;
    $admin->update([
      'login_otp' => Hash::make($otp),
      'login_otp_expires_at' => now()->addMinutes(10),
    ]);

    // ✅ Prepare SMS message
    $message = "Dear Partner, Your Web login OTP is {$otp} . Do not share with anyone for security reasons.- Team Finsova";

    // ✅ Dispatch Email Job
    SendLoginOtpJob::dispatch(
      $name,
      $email,
      $otp
    );
    $this->sendMsgOtp($mobile, config('services.sms.entity_id'), config('services.sms.template_id'), $message);
    return response()->json([
      'status' => true,
      'message' => 'OTP sent successfully on Email & Mobile'
    ]);
  }
  private function sendMsgOtp($to, $entity_id, $template_id, $message)
  {
    $url = config('services.sms.url');
    $payload = [
      'username'   => config('services.sms.username'),
      'dest'       => $to,
      'apikey'     => config('services.sms.api_key'),
      'signature'  => config('services.sms.sender'),
      'msgtype'    => 'PM',
      'msgtxt'     => $message,
      'entityid'   => $entity_id,
      'templateid' => $template_id,
    ];

    try {
      $response = Http::timeout(30)->get($url, $payload);

      // Structured logging
      $this->writeSmsLogs(
        'SMS',
        'SendMsg',
        $url,
        $payload,
        $response->headers(),
        $response->status(),
        $response->body()
      );

      if (!$response->successful()) {
        Log::warning('SMS sending failed', [
          'to'       => $to,
          'status'   => $response->status(),
          'response' => $response->body(),
        ]);
      }

      return $response->json();
    } catch (\Throwable $e) {
      Log::error('SMS sending exception', [
        'to'      => $to,
        'message' => $message,
        'error'   => $e->getMessage(),
      ]);
      return ['status' => 'error', 'message' => $e->getMessage()];
    }
  }
  private function writeSmsLogs(
    string $service,
    string $api,
    string $url,
    $requestPayload = null,
    $headers = null,
    $responseCode = null,
    $responseData = null
  ) {
    $log = [
      'service'       => $service,
      'api'           => $api,
      'url'           => $url,
      'ip'            => request()->ip(),
      'datetime'      => now()->toDateTimeString(),
      'headers'       => $headers,
      'payload'       => $requestPayload,
      'http_code'     => $responseCode,
      'response_data' => $responseData,
    ];

    Log::channel('sms')->info('SMS Log', $log);
  }
}
