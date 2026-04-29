<?php

namespace App\Http\Controllers\user\auth;

use App\Helpers\Helpers;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\CompanyDetail;
use App\Models\User;
use App\Models\UserApiKey;
use App\Models\UserService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    public function index()
    {
        $pageConfigs = ['myLayout' => 'blank'];
        return view('user.auth.register', ['pageConfigs' => $pageConfigs]);
    }
    public function sentOtp(Request $request)
    {
        $pageConfigs = ['myLayout' => 'blank'];
        return view('user.auth.sent-otp', ['pageConfigs' => $pageConfigs]);
    }
    public function store(Request $request)
    {
        /* ---------------------------------
        | 1. VALIDATION
        |----------------------------------*/
        $validator = Validator::make($request->all(), [

            /* ---------- USERS ---------- */
            'company_name'      => 'required|string|max:255',
            'email'             => 'required|email|unique:users,email',
            'mobile_number'     => 'required|digits:10|unique:users,mobile_number',
            'password'          => 'required|min:8|confirmed',

            /* ---------- COMPANY ---------- */
            'company_type'      => 'required|string',
            'gst_no'            => 'nullable|string|max:15',
            'cin'               => 'nullable|string|max:21',
            'pan'               => 'nullable|string|max:10',
            'udhyam_number'     => 'nullable|string|max:20',
            'address'           => 'nullable|string',

            'gst_image'         => 'nullable|file|max:5120',
            'cin_image'         => 'nullable|file|max:5120',
            'pan_image'         => 'nullable|file|max:5120',
            'udhyam_image'      => 'nullable|file|max:5120',

            /* ---------- DIRECTOR ---------- */
            'director_name'         => 'required|string|max:255',
            'director_email'        => 'required|email',
            'director_mobile'       => 'required|digits:10',
            'director_aadhar_no'    => 'required|digits:12',
            'director_pan_no'       => 'required|string|max:10',

            'director_aadhar_image' => 'required|file|max:5120',
            'director_pan_image'    => 'required|file|max:5120',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        DB::beginTransaction();

        try {
            /* ---------------------------------
            | 2. USERS TABLE
            |----------------------------------*/
            $user = User::create([
                'uid'              => Helpers::generateUniqueUid(),
                'company_name'     => $request->company_name,
                'email'            => $request->email,
                'mobile_number'    => $request->mobile_number,
                'password'         => Hash::make($request->password),
                'payout_balance'   => $request->payout_balance ?? '0.00',
                'payin_balance'    => $request->payin_balance ?? '0.00',
                'reserve_balance'  => $request->reserve_balance ?? '0.00',
                'freeze_balance'   => $request->freeze_balance ?? '0.00',
                'virtual_balance'  => $request->virtual_balance ?? '0.00',
                'active'           => 'A',
            ]);

            /* ---------------------------------
            | 3. FILE UPLOADS
            |----------------------------------*/
            if ($request->file('gst_image') != null) {
                $gstImage = Helpers::storeEncryptedDoc(
                    $request->file('gst_image'),
                    $user->id,
                    $request->gst_no
                );
            }
            if ($request->file('cin_image') != null) {
                $cinImage = Helpers::storeEncryptedDoc(
                    $request->file('cin_image'),
                    $user->id,
                    $request->cin
                );
            }

            if ($request->file('pan_image') != null) {
                $panImage = Helpers::storeEncryptedDoc(
                    $request->file('pan_image'),
                    $user->id,
                    $request->pan
                );
            }

            if ($request->file('udhyam_image') != null) {
                $udhyamImage = Helpers::storeEncryptedDoc(
                    $request->file('udhyam_image'),
                    $user->id,
                    $request->udhyam_number
                );
            }

            if ($request->file('moa_image') != null) {
                $udhyamImage = Helpers::storeEncryptedDoc(
                    $request->file('moa_image'),
                    $user->id,
                    '',
                );
            }

            if ($request->file('br_image') != null) {
                $udhyamImage = Helpers::storeEncryptedDoc(
                    $request->file('br_image'),
                    $user->id,
                    '',
                );
            }

            if ($request->file('director_aadhar_image') != null) {
                $directorAadharImage = Helpers::storeEncryptedDoc(
                    $request->file('director_aadhar_image'),
                    $user->id,
                    $request->director_aadhar_no
                );
            }

            if ($request->file('director_pan_image') != null) {
                $directorPanImage = Helpers::storeEncryptedDoc(
                    $request->file('director_pan_image'),
                    $user->id,
                    $request->director_pan_no
                );
            }
            /* ---------------------------------
            | 4. COMPANY + DIRECTOR DETAILS
            |----------------------------------*/
            $encryptedAadhar = Crypt::encryptString($request->director_aadhar_no);
            $encryptedPan    = Crypt::encryptString($request->director_pan_no);
            CompanyDetail::create([
                'user_id'               => $user->id,
                'name'                  => $request->company_name,
                'company_type'          => $request->company_type,
                'gst_no'                => $request->gst_no ? Crypt::encryptString($request->gst_no) : null,
                'gst_image'             => $gstImage ?? null,
                'cin'                   => $request->cin ? Crypt::encryptString($request->cin) : null,
                'cin_image'             => $cinImage ?? null,
                'pan'                   => $request->pan ? Crypt::encryptString($request->pan) : null,
                'pan_image'             => $panImage ?? null,
                'udhyam_number'         => $request->udhyam_number ? Crypt::encryptString($request->udhyam_number) : null,
                'udhyam_image'          => $udhyamImage ?? null,
                'moa_image'             => $moaImage ?? null,
                'br_image'              => $brImage ?? null,
                'address'               => $request->address ?? null,
                'director_name'         => $request->director_name,
                'director_email'        => $request->director_email,
                'director_mobile'       => $request->director_mobile,
                'director_aadhar_no'    => $encryptedAadhar,
                'director_aadhar_image' => $directorAadharImage,
                'director_pan_no'       => $request->director_pan_no ?? Crypt::encryptString($request->director_pan_no),
                'director_pan_image'    => $encryptedPan,
            ]);
            /* ---------------------------------
            | 5. USER SERVICES
            |----------------------------------*/

            UserService::create([
                'user_id'               => $user->id,
                'payout_status'         => $request->payout_status ?? 'B',
                'payin_status'          => $request->payin_status ?? 'B',
                'minimum_transaction'   => $request->minimum_transaction ?? '100.00',
                'maximum_transaction'   => $request->maximum_transaction ?? '49999.00',
                'payin_minimum_transaction' => $request->payin_minimum_transaction ?? '100.00',
                'payin_maximum_transaction' => $request->payin_maximum_transaction ?? '49999.00',
                'ftransaction'          => $request->ftransaction ?? 'B',
                'ptransaction'          => $request->ptransaction ?? 'B',
                'virtual_charges'       => $request->virtal_charges ?? '1.00',
                'virtual_type'          => $request->virtual_type ?? 'percentage',
                'pslab_1000'            => $request->pslab_1000 ?? '5.00',
                'pslab_25000'           => $request->pslab_25000 ?? '7.00',
                'pslab_200000'          => $request->pslab_200000 ?? '15.00',
                'pslab_percentage'      => $request->pslab_percentage ?? '7.00',
                'payin_charges'         => $request->payin_charges ?? '2.00',
                'active_payout_api'     => $request->active_payout_api ?? 'None',
                'active_payin_api'      => $request->active_payin_api ?? 'None',
            ]);
            /* ---------------------------------
            | 6. API KEYS
            |----------------------------------*/
            UserApiKey::create([
                'user_id'           => $user->id,
                'client_key'        => null,
                'client_secret'     => null,
                'payin_webhooks'    => null,
                'payout_webhooks'   => null,
                'ip'                => null,
            ]);

            DB::commit();

            return redirect()->route('userlogin')->with('success', 'Registration completed successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }
}
