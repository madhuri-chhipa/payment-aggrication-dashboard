<?php

namespace App\Http\Controllers\admin\auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ResetPasswordController extends Controller
{
  public function index()
  {
    $pageConfigs = ['myLayout' => 'blank'];
    return view('admin.auth.reset-password', ['pageConfigs' => $pageConfigs]);
  }

  public function reset(Request $request)
  {
    $request->validate([
      'email' => 'required|email|exists:admins,email',
      'otp' => 'required|digits:6',
      'password' => 'required|min:8|confirmed',
    ]);

    $admin = Admin::where('email', $request->email)->first();

    if (!$admin->reset_otp || now()->gt($admin->reset_otp_expires_at)) {
      return back()->withErrors(['otp' => 'OTP expired']);
    }

    if (!Hash::check($request->otp, $admin->reset_otp)) {
      return back()->withErrors(['otp' => 'Invalid OTP']);
    }

    $admin->update([
      'password' => Hash::make($request->password),
      'reset_otp' => null,
      'reset_otp_expires_at' => null,
    ]);

    session()->forget('password_reset_email');
    return redirect('/admin/login')->with('success', 'Password reset successfully');
  }
}
