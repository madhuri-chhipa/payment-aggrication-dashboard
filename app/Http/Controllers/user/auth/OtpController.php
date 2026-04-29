<?php

namespace App\Http\Controllers\user\auth;

use App\Http\Controllers\Controller;
use App\Jobs\SendLoginOtpJob;
use App\Jobs\SendSmsOtpJob;
use App\Models\UserLoginLog;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;

class OtpController extends Controller
{
  /**
   * Display the OTP verification page.
   */
  public function userotpview()
  {
    $pageConfigs = ['myLayout' => 'blank'];
    return view('user.auth.otp-view', ['pageConfigs' => $pageConfigs]);
  }

  /**
   * Handle OTP submission.
   */
  public function userotpsubmit(Request $request)
  {
    $request->validate([
      'otp' => 'required',
      'latitude' => 'nullable',
      'longitude' => 'nullable',
    ]);

    $latitude = $request->latitude;
    $longitude = $request->longitude;
    $userMobile = session('otp_user_mobile');
    if (!$userMobile) {
      return redirect()->route('login')
        ->withErrors(['error' => 'Session expired. Please login again.']);
    }

    // if (!$latitude || !$longitude) {
    //   try {
    //     $ipData = Http::get("http://ip-api.com/json/" . $request->ip())->json();
    //     $latitude = $ipData['lat'] ?? null;
    //     $longitude = $ipData['lon'] ?? null;
    //   } catch (\Exception $e) {
    //   }
    // }
    $otp = $request->input('otp');
    $user = User::where('mobile_number', $userMobile)->first();
    if (!$user) {
      return redirect()->route('login')
        ->withErrors(['otp' => 'Session expired. Please login again.']);
    }
    if (!$user->login_otp || !$user->login_otp_expires_at || $user->login_otp_expires_at < now() || !Hash::check($otp, $user->login_otp)) {
      return redirect()->back()
        ->withErrors(['otp' => 'Invalid or expired OTP, Please try again.']);
    }

    // ✅ Save login log
    UserLoginLog::create([
      'user_id' => $user->id,
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

    // ✅ Clear OTP
    $user->update([
      'login_otp' => null,
      'login_otp_expires_at' => null,
    ]);

    // ✅ LOGIN USING CORRECT GUARD
    Auth::guard('user')->login($user);
    $request->session()->forget('otp_user_mobile');
    // optional but safe
    $request->session()->regenerate();

    return redirect()->route('dashboard')
      ->with('success', 'Login successful');
  }

  public function resendLoginOtp(Request $request)
  {
    $request->validate([
      'user_mobile' => 'required'
    ]);

    $userMobile = $request->user_mobile;

    $user = User::where('mobile_number', $userMobile)->first();

    if (!$user) {
      return response()->json([
        'status' => false,
        'message' => 'User not found'
      ]);
    }

    // ✅ Prevent spam (allow resend only after 60 sec)
    if ($user->login_otp_expires_at && now()->diffInSeconds($user->login_otp_expires_at, false) > 540) {
      return response()->json([
        'status' => false,
        'message' => 'Please wait before requesting OTP again'
      ]);
    }

    // ✅ Generate OTP
    $otp = rand(1000, 9999);

    $user->update([
      'login_otp' => Hash::make($otp),
      'login_otp_expires_at' => now()->addMinutes(10),
    ]);

    // ✅ Prepare SMS message
    $message = "Dear Partner, Your Web login OTP is {$otp} . Do not share with anyone for security reasons.- Team Finsova";

    // ✅ Dispatch Email Job
    SendLoginOtpJob::dispatch(
      $user->company_name,
      $user->email,
      $otp
    );

    // ✅ Dispatch SMS Job
    SendSmsOtpJob::dispatch(
      $user->mobile_number,
      $message,
      config('services.sms.entity_id'),
      config('services.sms.template_id')
    );

    return response()->json([
      'status' => true,
      'message' => 'OTP sent successfully on Email & Mobile'
    ]);
  }
}
