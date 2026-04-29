<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\UserApiKey;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Testing\Fluent\Concerns\Has;

class ProfileController extends Controller
{
  public function index()
  {
    $user = Auth::user()->load([
      'companyDetail',
      'services',
      'apiKey'
    ]);

    return view('user.profile', compact('user'));
  }

  public function viewDocument($path)
  {
    $path = base64_decode($path);

    if (!Storage::disk('public')->exists($path)) {
      abort(404);
    }

    $encrypted = Storage::disk('public')->get($path);
    $base64 = Crypt::decryptString($encrypted);
    $binary = base64_decode($base64);

    return response($binary)->header('Content-Type', 'image/png');
  }

  public function updateApi(Request $request)
  {
    $user = Auth::user();
    $validated = $request->validate([
      'payin_webhooks'   => 'nullable|url',
      'payout_webhooks'  => 'nullable|url',
      'ip'               => 'nullable|string',
    ]);
    UserApiKey::updateOrCreate(
      ['user_id' => $user->id],
      [
        'payin_webhooks' => $validated['payin_webhooks'] ?? null,
        'payout_webhooks' => $validated['payout_webhooks'] ?? null,
        'ip'            => $validated['ip'] ?? null,
      ]
    );
    return back()->with('success', 'Developer options updated');
  }

  public function generateApiKeys(Request $request)
  {
    $user = Auth::user();

    try {
      $clientKey = bin2hex(random_bytes(12));      // 24 chars
      $clientSecret = bin2hex(random_bytes(16));  // 32 chars
      UserApiKey::updateOrCreate(
        ['user_id' => $user->id],
        [
          'client_key'     => Crypt::encryptString($clientKey),
          'client_secret'  => Crypt::encryptString($clientSecret),
        ]
      );
      return response()->json([
        'success'       => true,
        'client_key'    => $clientKey,     // decrypted
        'client_secret' => $clientSecret,  // decrypted
        'message'       => 'API keys generated successfully',
      ]);
    } catch (\Throwable $e) {
      return response()->json([
        'success' => false,
        'message' => 'Failed to generate API keys',
      ], 500);
    }
  }

  public function updatePassword(Request $request)
  {
    $user = Auth::user();
    $validated = $request->validate([
      'current_password' => 'required|string',
      'password' => 'required|string|min:8',
      'password_confirmation' => 'required|string|min:8|same:password',
    ]);

    if (!Hash::check($validated['current_password'], $user->password)) {
      return back()->with('error', 'Current password is incorrect');
    }
    if (Hash::check($validated['password'], $user->password)) {
      return back()->with('error', 'New password cannot be same as current password');
    }
    $user->update([
      'password' => Hash::make($validated['password']),
    ]);
    return back()->with('success', 'Password updated successfully');
  }
}