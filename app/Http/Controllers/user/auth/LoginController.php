<?php

namespace App\Http\Controllers\user\auth;

use App\Http\Controllers\Controller;
use App\Jobs\SendLoginOtpJob;
use App\Jobs\SendSmsOtpJob;
use Illuminate\Http\Request;
use App\Mail\LoginOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class LoginController extends Controller
{
  public function index()
  {
    $pageConfigs = ['myLayout' => 'blank'];
    if (auth()->guard('user')->check()) {
      return redirect()->route('dashboard');
    }
    return view('user.auth.login', ['pageConfigs' => $pageConfigs]);
  }
  public function logincheckuser(Request $request)
  {
    // dd($request->all());
    $request->validate([
      'login' => 'required|string',
      'password' => 'required|string',
    ]);
    $login = $request->input('login');
    $password = $request->input('password');

    $user = User::where('email', $login)->orWhere('mobile_number', $login)->first();
    if (!$user) {
      return redirect()->back()->withErrors(['error' => 'No user found.']);
    }

    if ($user->active == 'B') {
      return redirect()->back()->withErrors(['error' => 'Your account has been blocked.']);
    }

    if (!Hash::check($password, $user->password)) {
      return redirect()->back()->withErrors(['error' => 'Incorrect password.']);
    }

    // $request->session()->put('usertype', 'user');
    // Auth::login($user);
    // return redirect()->route('dashboards-analytics')->with('success', 'Login successful');
    if ($user->email == 'uatlogin@gmail.com') {
      $otp = '1234';
    } else if ($user->mobile_number == '8955645722') {
      $otp = '5722';
    } else {
      $otp = rand(1000, 9999);
    }
    // $mobile = $user->phone_number;
    $email = $user->email;
    $mobile = $user->mobile_number;
    $name = $user->company_name;
    $message = "Dear Partner, Your Web login OTP is {$otp} . Do not share with anyone for security reasons.- Team Finsova";
    $user->update([
      'login_otp' => Hash::make($otp),
      'login_otp_expires_at' => now()->addMinutes(10),
    ]);
    session([
      'otp_user_mobile' => $mobile
    ]);
    SendLoginOtpJob::dispatch($name, $email, $otp);
    $this->sendMsgOtp($mobile, config('services.sms.entity_id'), config('services.sms.template_id'), $message);

    return redirect()->route('auth.userotp.view')
      ->with('success', 'OTP sent successfully');
    // $responsemobile = '';
    // $responseemail = $this->sendLoginOtpEmail($name, $email, $otp);

    // $responseEmail = json_decode($responseemail->getContent(), true);
    // $responseCode = isset($responsemobile[0]) ? $responsemobile[0]['code'] : null;

    // if (!empty($responseEmail) && $responseEmail['status'] == true) {
    //   $user->update([
    //     'login_otp'   => Hash::make($otp),
    //     'login_otp_expires_at' => now()->addMinutes(10),
    //   ]);
    //   return redirect()->route('auth.userotp.view')
    //     ->with('success', 'OTP sent successfully to Registered Mobile/Email');
    // } else {
    //   $errorMessage = isset($responsemobile[0]['desc']) && $responseEmail['status'] == true ? $responsemobile[0]['desc'] : 'Failed to send OTP.';
    //   return redirect()->back()->withErrors(['error' => $errorMessage]);
    // }

  }
  /**
   * Function to send SMS via API
   */
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
      /** @var Response $response */
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

  /**
   * Structured SMS log writer
   */
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

  // public function sendLoginOtpEmail($name, $email, $otp)
  // {
  //   $systemName = config('app.name');
  //   $imageUrl   = asset(config('otp.logo_path'));

  //   $subject = str_replace(
  //     ':app',
  //     $systemName,
  //     config('otp.subjects.login')
  //   );
  //   try {
  //     Mail::to($email)->send(
  //       new LoginOtpMail(
  //         name: $name,
  //         otp: $otp,
  //         systemName: $systemName,
  //         imageUrl: $imageUrl,
  //         subjectLine: $subject
  //       )
  //     );
  //     return response()->json([
  //       'status'  => true,
  //       'message' => 'OTP sent successfully.',
  //     ]);
  //   } catch (\Throwable $e) {
  //     Log::error('Login OTP Mail Failed', [
  //       'email' => $email,
  //       'error' => $e->getMessage(),
  //     ]);
  //     return response()->json([
  //       'status'  => false,
  //       'message' => 'Failed to send OTP. Please try again.',
  //     ]);
  //   }
  // }

  public function logout(Request $request)
  {
    Auth::guard('user')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect()->route('userlogin');
  } // end of logout function
}
