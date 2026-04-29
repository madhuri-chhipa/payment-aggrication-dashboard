<?php

namespace App\Http\Controllers\user\auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ResetPasswordController extends Controller
{
  public function index()
  {
    $pageConfigs = ['myLayout' => 'blank'];
    return view('user.auth.reset-password', ['pageConfigs' => $pageConfigs]);
  }

  public function reset(Request $request)
  {
    $request->validate([
      'email' => 'required|email|exists:users,email',
      'otp' => 'required|digits:6',
      'password' => 'required|min:8|confirmed',
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user->reset_otp || now()->gt($user->reset_otp_expires_at)) {
      return back()->withErrors(['otp' => 'OTP expired']);
    }

    if (!Hash::check($request->otp, $user->reset_otp)) {
      return back()->withErrors(['otp' => 'Invalid OTP']);
    }

    $user->update([
      'password' => Hash::make($request->password),
      'reset_otp' => null,
      'reset_otp_expires_at' => null,
    ]);

    session()->forget('password_reset_email');
    return redirect('/login')->with('success', 'Password reset successfully');
  }
}
