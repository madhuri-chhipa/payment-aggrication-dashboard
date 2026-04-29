<?php

namespace App\Http\Controllers\admin\auth;

use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordOtpMail;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class ForgotPasswordController extends Controller
{
  public function index()
  {
    $pageConfigs = ['myLayout' => 'blank'];
    return view('admin.auth.forgot-password', ['pageConfigs' => $pageConfigs]);
  }

  public function sendOtp(Request $request)
  {
    $request->validate([
      'email' => 'required|email|exists:admins,email',
    ]);

    $admin = Admin::where('email', $request->email)->firstOrFail();

    $otp = random_int(100000, 999999);

    $admin->update([
      'reset_otp' => Hash::make($otp),
      'reset_otp_expires_at' => now()->addMinutes(
        config('otp.expiry_minutes')
      ),
    ]);

    // 🔹 Dynamic values from config
    $systemName = config('app.name');
    $imageUrl   = asset(config('otp.logo_path'));

    $subject = str_replace(
      ':app',
      $systemName,
      config('otp.subjects.forgot')
    );

    // 🔹 Send OTP mail
    Mail::to($admin->email)->send(
      new ResetPasswordOtpMail(
        name: $admin->name,
        otp: $otp,
        systemName: $systemName,
        imageUrl: $imageUrl,
        subjectLine: $subject
      )
    );

    session([
      'password_reset_email' => $admin->email
    ]);

    return redirect()
      ->route('admin.reset-password')
      ->with('success', 'OTP sent to your email.');
  }
}
