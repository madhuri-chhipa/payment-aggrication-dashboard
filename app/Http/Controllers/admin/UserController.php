<?php

namespace App\Http\Controllers\admin;

use App\Helpers\Helpers;
use App\Http\Controllers\Controller;
use App\Models\CompanyDetail;
use App\Models\UserWalletTransaction;
use App\Models\User;
use App\Models\UserApiKey;
use App\Models\UserService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\TextUI\Help;

class UserController extends Controller
{
  /**
   * Display a listing of the resource.
   */
  public function index()
  {
    return view('admin.users.list');
  }
  public function getUsersData(Request $request)
  {
    $admin = auth('admin')->user();
    $query = User::withTrashed()
      ->when($admin->admin_type === 'employee', function ($q) use ($admin) {
        $q->where('created_by', $admin->id);
      })
      ->select(['id', 'uid', 'company_name', 'email', 'mobile_number', 'payout_balance', 'payin_balance', 'virtual_balance', 'freeze_balance', 'reserve_balance', 'created_at', 'active', 'deleted_at'])
      ->when($request->user, function ($q, $v) {
        $q->where(function ($query) use ($v) {
          $query->where('company_name', 'like', "%{$v}%")
            ->orWhere('email', 'like', "%{$v}%")
            ->orWhere('mobile_number', 'like', "%{$v}%");
        });
      })
      ->when(
        $request->uid,
        fn($q, $v) =>
        $q->where('uid', 'like', "%{$v}%")
      )
      ->when($request->from_date || $request->to_date, function ($q) use ($request) {
        if ($request->from_date && $request->to_date) {
          $q->whereBetween('created_at', [
            Carbon::parse($request->from_date)->startOfDay(),
            Carbon::parse($request->to_date)->endOfDay()
          ]);
        } elseif ($request->from_date) {
          $q->whereDate('created_at', '>=', $request->from_date);
        } elseif ($request->to_date) {
          $q->whereDate('created_at', '<=', $request->to_date);
        }
      })
      ->when(
        $request->status !== null,
        fn($q) =>
        $q->where('active', $request->status)
      )
      ->when(
        $request->delete !== null && $request->delete !== '',
        function ($q) use ($request) {
          if ($request->delete == 1) {
            $q->whereNotNull('deleted_at');
          } else {
            $q->whereNull('deleted_at');
          }
        }
      );
    return DataTables::of($query)
      ->addIndexColumn()
      ->editColumn('created_at', fn($u) => $u->created_at->format('d/m/Y H:i:s'))
      ->editColumn('status', function ($u) {

        $textStatus = $u->active == 'A' ? 'Active' : 'Blocked';

        return '
        <div class="form-check form-switch d-flex justify-content-center">
            <input class="form-check-input status-toggle"
                style="width:30px;"
                type="checkbox"
                role="switch"
                data-id="' . $u->id . '"
                ' . ($u->active == 'A' ? 'checked' : '') . '>
        </div>
        <span class="d-none export-status">' . $textStatus . '</span>';
      })
      ->addColumn('actions', function ($u) {
        if ($u->deleted_at) {
          return '<span class="badge bg-danger">Deleted</span>';
        }
        return '
          <div class="dropdown">
              <button class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                  Actions
              </button>
              <ul class="dropdown-menu">
                  <li>
                      <a class="dropdown-item" href="' . route('admin.user.view', $u->id) . '">
                          <i class="fa fa-eye me-2"></i>View
                      </a>
                  </li>
                  <li>
                      <a class="dropdown-item" href="' . route('admin.user.edit', $u->id) . '">
                          <i class="fa fa-edit me-2"></i>Edit
                      </a>
                  </li>
                  <li>
                      <a class="dropdown-item text-danger delete-user" href="javascript:void(0)" data-id="' . $u->id . '">
                          <i class="fa fa-trash-can me-2"></i>Delete
                      </a>
                  </li>
              </ul>
          </div>';
      })

      ->rawColumns(['status', 'actions'])
      ->toJson();
  }

  /**
   * Show the form for creating a new resource.
   */
  public function create()
  {
    return view('admin.users.create');
  }

  /**
   * Store a newly created resource in storage.
   */
  public function store(Request $request)
  {
    /* ---------------------------------
         | 1. VALIDATION
         |----------------------------------*/
    $validator = Validator::make($request->all(), [

      /* ---------- USERS ---------- */
      'company_name' => 'required|string|max:255',
      'email' => 'required|email|unique:users,email',
      'mobile_number' => 'required|digits:10|unique:users,mobile_number',
      'password' => 'required|min:8|confirmed',

      /* ---------- COMPANY ---------- */
      'company_type' => 'required|string',
      'gst_no' => 'nullable|string|max:15',
      'cin' => 'nullable|string|max:21',
      'pan' => 'nullable|string|max:10',
      'udhyam_number' => 'nullable|string|max:20',
      'address' => 'nullable|string',

      'gst_image' => 'nullable|file|max:5120',
      'cin_image' => 'nullable|file|max:5120',
      'pan_image' => 'nullable|file|max:5120',
      'udhyam_image' => 'nullable|file|max:5120',
      'moa_image' => 'nullable|file|max:5120',
      'br_image' => 'nullable|file|max:5120',

      /* ---------- DIRECTOR ---------- */
      'director_name' => 'required|string|max:255',
      'director_email' => 'required|email',
      'director_mobile' => 'required|digits:10',
      'director_aadhar_no' => 'required|digits:12',
      'director_pan_no' => 'required|string|max:10',

      'director_aadhar_image' => 'required|file|max:5120',
      'director_pan_image' => 'required|file|max:5120',

      /* ---------- SERVICES ---------- */
      'payout_status' => 'nullable|in:A,B',
      'payin_status' => 'nullable|in:A,B',
      'minimum_transaction' => 'nullable|numeric|min:1',
      'maximum_transaction' => 'nullable|numeric|gt:minimum_transaction',
      'payin_minimum_transaction' => 'nullable|numeric|min:1',
      'payin_maximum_transaction' => 'nullable|numeric|gt:payin_minimum_transaction',

      'ftransaction' => 'nullable|in:A,B,C',
      'ptransaction' => 'nullable|in:A,B,C',
      'virtal_charges' => 'nullable|numeric|min:0',
      'virtual_type' => 'nullable|in:percentage,flat_rate',

      'pslab_1000' => 'nullable|numeric|min:0',
      'pslab_25000' => 'nullable|numeric|min:0',
      'pslab_200000' => 'nullable|numeric|min:0',
      'pslab_percentage' => 'nullable|numeric|min:0',
      'payin_charges' => 'nullable|numeric|min:0',

      /* ---------- API KEYS ---------- */
      'client_key' => 'nullable|string',
      'client_secret' => 'nullable|string',
      'payin_webhooks' => 'nullable|url',
      'payout_webhooks' => 'nullable|url',
      'ip' => 'nullable|string',
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
        'uid' => Helpers::generateUniqueUid(),
        'company_name' => $request->company_name,
        'email' => $request->email,
        'mobile_number' => $request->mobile_number,
        'password' => Hash::make($request->password),
        'payout_balance' => $request->payout_balance ?? '0.00',
        'payin_balance' => $request->payin_balance ?? '0.00',
        'reserve_balance' => $request->reserve_balance ?? '0.00',
        'freeze_balance' => $request->freeze_balance ?? '0.00',
        'virtual_balance' => $request->virtual_balance ?? '0.00',
        'active' => 'A',
        'created_by' => auth('admin')->id(),
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
        $moaImage = Helpers::storeEncryptedDoc(
          $request->file('moa_image'),
          $user->id,
          '',
        );
      }

      if ($request->file('br_image') != null) {
        $brImage = Helpers::storeEncryptedDoc(
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
        'user_id' => $user->id,
        'name' => $request->company_name,
        'company_type' => $request->company_type,
        'gst_no' => $request->gst_no ? Crypt::encryptString($request->gst_no) : null,
        'gst_image' => $gstImage ?? null,
        'cin' => $request->cin ? Crypt::encryptString($request->cin) : null,
        'cin_image' => $cinImage ?? null,
        'pan' => $request->pan ? Crypt::encryptString($request->pan) : null,
        'pan_image' => $panImage ?? null,
        'udhyam_number' => $request->udhyam_number ? Crypt::encryptString($request->udhyam_number) : null,
        'udhyam_image' => $udhyamImage ?? null,
        'moa_image' => $moaImage ?? null,
        'br_image' => $brImage ?? null,
        'address' => $request->address ?? null,
        'director_name' => $request->director_name,
        'director_email' => $request->director_email,
        'director_mobile' => $request->director_mobile,
        'director_aadhar_no'  => $encryptedAadhar,
        'director_aadhar_image' => $directorAadharImage,
        'director_pan_no'     => $encryptedPan,
        'director_pan_image' => $directorPanImage,
      ]);
      /* ---------------------------------
            | 5. USER SERVICES
            |----------------------------------*/
      UserService::create([
        'user_id' => $user->id,
        'payout_status' => $request->payout_status ?? 'B',
        'payin_status' => $request->payin_status ?? 'B',
        'minimum_transaction' => $request->minimum_transaction ?? '100.00',
        'maximum_transaction' => $request->maximum_transaction ?? '49999.00',
        'payin_minimum_transaction' => $request->payin_minimum_transaction ?? '100.00',
        'payin_maximum_transaction' => $request->payin_maximum_transaction ?? '49999.00',
        'ftransaction' => $request->ftransaction ?? 'B',
        'ptransaction' => $request->ptransaction ?? 'B',
        'virtual_charges' => $request->virtal_charges ?? '1.00',
        'virtual_type' => $request->virtual_type ?? 'percentage',
        'pslab_1000' => $request->pslab_1000 ?? '5.00',
        'pslab_25000' => $request->pslab_25000 ?? '7.00',
        'pslab_200000' => $request->pslab_200000 ?? '15.00',
        'pslab_percentage' => $request->pslab_percentage ?? '7.00',
        'payin_charges' => $request->payin_charges ?? '2.00',
        'active_payout_api' => $request->active_payout_api ?? 'None',
        'active_payin_api' => $request->active_payin_api ?? 'None',
      ]);
      /* ---------------------------------
            | 6. API KEYS
            |----------------------------------*/
      UserApiKey::create([
        'user_id' => $user->id,
        'client_key' => Null,
        'client_secret' => Null,
        'payin_webhooks' => $request->payin_webhooks,
        'payout_webhooks' => $request->payout_webhooks,
        'ip' => $request->ip,
      ]);

      DB::commit();
      return redirect()->route('admin.user.list')->with('success', 'Registration completed successfully');
    } catch (\Exception $e) {
      DB::rollBack();
      return back()->with('error', $e->getMessage());
    }
  }

  /**
   * Display the specified resource.
   */
  public function show(string $id)
  {
    $user = User::with(['companyDetail', 'services', 'apiKey'])
      ->findOrFail($id);
    return view('admin.users.view', compact('user'));
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

    return response($binary)
      ->header('Content-Type', 'image/png'); // or image/jpeg
  }
  /**
   * Show the form for editing the specified resource.
   */
  public function edit(string $id)
  {
    $user = User::with(['companyDetail', 'services', 'apiKey'])
      ->findOrFail($id);
    if (!$user) {
      return back()->with('error', 'User not found');
    }
    if (!$user->companyDetail) {
      return back()->with('error', 'Company details not found');
    }
    if (!$user->services) {
      return back()->with('error', 'Services not found');
    }
    if (!$user->apiKey) {
      return back()->with('error', 'API keys not found');
    } else {
      $userApi = $user->apiKey;
      $secureFields = [
        'razorpay_api_key',
        'razorpay_secret_key',
        'paywize_api_key',
        'paywize_secret_key',
        'buckbox_api_key',
        'buckbox_secret_key',
        'buckbox_eny_key',
      ];
      if ($userApi) {
        foreach ($secureFields as $field) {
          $userApi->$field = Helpers::decryptValue($userApi->$field);
        }
      }
    }

    return view('admin.users.edit', compact('user'));
  }

  /**
   * Update the specified resource in storage.
   */
  public function updateBasic(Request $request, User $user)
  {
    $validated = $request->validate([
      'company_name'   => 'required|string|max:255',
      'email'          => 'required|email|unique:users,email,' . $user->id,
      'mobile_number'  => 'required|digits:10|unique:users,mobile_number,' . $user->id,
      'password'       => 'nullable|min:8|confirmed',
    ]);

    $user->company_name  = $validated['company_name'];
    $user->email         = $validated['email'];
    $user->mobile_number = $validated['mobile_number'];

    if (!empty($validated['password'])) {
      $user->password = Hash::make($validated['password']);
    }

    $user->save();

    return back()->with('success', 'Basic details updated successfully');
  }

  public function updateCompany(Request $request, User $user)
  {
    $validated = $request->validate([
      'company_type'   => 'required|string',
      'gst_no'         => 'nullable|string|max:15',
      'cin'            => 'nullable|string|max:21',
      'pan'            => 'nullable|string|max:10',
      'udhyam_number'  => 'nullable|string|max:20',
      'address'        => 'nullable|string',
      'gst_image'      => 'nullable|file|max:5120',
      'cin_image'      => 'nullable|file|max:5120',
      'pan_image'      => 'nullable|file|max:5120',
      'udhyam_image'   => 'nullable|file|max:5120',
      'moa_image'      => 'nullable|file|max:5120',
      'br_image'       => 'nullable|file|max:5120',
    ]);
    $data = [
      'name'          => $user->company_name,
      'company_type'  => $validated['company_type'],
      'gst_no'        => $validated['gst_no'] ? Crypt::encryptString($validated['gst_no']) : null,
      'cin'           => $validated['cin'] ? Crypt::encryptString($validated['cin']) : null,
      'pan'           => $validated['pan'] ? Crypt::encryptString($validated['pan']) : null,
      'udhyam_number' => $validated['udhyam_number'] ? Crypt::encryptString($validated['udhyam_number']) : null,
      'address'       => $validated['address'] ?? null,
    ];
    /* FILES */
    foreach (
      [
        'gst_image'    => 'gst_no',
        'cin_image'    => 'cin',
        'pan_image'    => 'pan',
        'udhyam_image' => 'udhyam_number',
        'moa_image'    => null,
        'br_image'     => null,
      ] as $file => $ref
    ) {
      if ($request->hasFile($file)) {
        $data[$file] = Helpers::storeEncryptedDoc(
          $request->file($file),
          $user->id,
          $ref ? $request->input($ref) : null
        );
      }
    }
    CompanyDetail::updateOrCreate(
      ['user_id' => $user->id],
      $data
    );
    return back()->with('success', 'Company details updated');
  }

  public function updateDirector(Request $request, User $user)
  {
    $validated = $request->validate([
      'director_name'       => 'required|string|max:255',
      'director_email'      => 'required|email',
      'director_mobile'     => 'required|digits:10',
      'director_aadhar_no'  => 'required|digits:12',
      'director_pan_no'     => 'required|string|max:10',
      'director_aadhar_image' => 'nullable|file|max:5120',
      'director_pan_image'    => 'nullable|file|max:5120',
    ]);
    $encryptedAadhar = Crypt::encryptString($validated['director_aadhar_no']);
    $encryptedPan    = Crypt::encryptString($validated['director_pan_no']);

    $data = [
      'director_name'       => $validated['director_name'],
      'director_email'      => $validated['director_email'],
      'director_mobile'     => $validated['director_mobile'],
      'director_aadhar_no'  => $encryptedAadhar,
      'director_pan_no'     => $encryptedPan,
    ];

    if ($request->file('director_aadhar_image')) {
      $data['director_aadhar_image'] = Helpers::storeEncryptedDoc(
        $request->file('director_aadhar_image'),
        $user->id,
        $validated['director_aadhar_no']
      );
    }
    if ($request->file('director_pan_image')) {
      $data['director_pan_image'] = Helpers::storeEncryptedDoc(
        $request->file('director_pan_image'),
        $user->id,
        $validated['director_pan_no']
      );
    }
    CompanyDetail::updateOrCreate(
      ['user_id' => $user->id],
      $data
    );
    return back()->with('success', 'Director details updated');
  }


  public function updateServices(Request $request, User $user)
  {
    $validated = $request->validate([
      'payout_status' => 'nullable|in:A,B',
      'payin_status'  => 'nullable|in:A,B',
      'minimum_transaction'        => 'nullable|numeric|min:1',
      'maximum_transaction'        => 'nullable|numeric|gt:minimum_transaction',
      'payin_minimum_transaction'  => 'nullable|numeric|min:1',
      'payin_maximum_transaction'  => 'nullable|numeric|gt:payin_minimum_transaction',
      'virtual_type'    => 'nullable|in:percentage,flat_rate',
      'pslab_1000'      => 'nullable|numeric|min:0',
      'pslab_25000'     => 'nullable|numeric|min:0',
      'pslab_200000'    => 'nullable|numeric|min:0',
      'pflat_charges'   => 'nullable|numeric|min:0',
      'pflat_charges_2' => 'nullable|numeric|min:0',
      'virtual_charges' => 'nullable|numeric|min:0',
      'payout_charges'  => 'nullable|numeric|min:0',
      'payin_charges'   => 'nullable|numeric|min:0',
      'platform_fee'    => 'nullable|numeric|min:0',
      'wallet_type'     => 'nullable|in:virtual_wallet,payout_wallet',
      'active_payout_api' => 'nullable|in:' . implode(',', array_keys(config('constant.ACTIVE_PIPE_TYPE'))),
      'active_payin_api'  => 'nullable|in:' . implode(',', array_keys(config('constant.ACTIVE_PIPE_TYPE'))),
      'payout_service_enable' => 'nullable|in:A,B',
      'load_money_service_enable' => 'nullable|in:A,B',
      'bill_payment_service_enable' => 'nullable|in:A,B',
      'bbps_charges' => 'nullable|numeric|min:0',
    ]);
    UserService::updateOrCreate(
      ['user_id' => $user->id],
      [
        'payout_status' => $validated['payout_status'] ?? 'B',
        'payin_status'  => $validated['payin_status'] ?? 'B',
        'minimum_transaction'       => $validated['minimum_transaction'] ?? 100,
        'maximum_transaction'       => $validated['maximum_transaction'] ?? 49999,
        'payin_minimum_transaction' => $validated['payin_minimum_transaction'] ?? 100,
        'payin_maximum_transaction' => $validated['payin_maximum_transaction'] ?? 49999,
        'virtual_type'      => $validated['virtual_type'] ?? 'percentage',
        'pslab_1000'        => $validated['pslab_1000'] ?? '5.00',
        'pslab_25000'       => $validated['pslab_25000'] ?? '7.00',
        'pslab_200000'      => $validated['pslab_200000'] ?? '15.00',
        'pflat_charges'     => $validated['pflat_charges'] ?? '7.00',
        'pflat_charges_2'   => $validated['pflat_charges_2'] ?? '7.00',
        'wallet_type'       => $validated['wallet_type'] ?? 'virtual_wallet',
        'virtual_charges'   => $validated['virtual_charges'] ?? '1.00',
        'payout_charges'    => $validated['payout_charges'] ?? '1.00',
        'platform_fee'      => $validated['platform_fee'] ?? '5.00',
        'payin_charges'     => $validated['payin_charges'] ?? '2.00',
        'active_payout_api' => $validated['active_payout_api'] ?? 'None',
        'active_payin_api'  => $validated['active_payin_api'] ?? 'None',
        'payout_service_enable' => $validated['payout_service_enable'] ?? 'B',
        'load_money_service_enable' => $validated['load_money_service_enable'] ?? 'B',
        'bill_payment_service_enable' => $validated['bill_payment_service_enable'] ?? 'B',
        'bbps_charges' => $validated['bbps_charges'] ?? '1.00',
      ]
    );
    return back()->with('success', 'Services updated successfully');
  }

  public function updateApi(Request $request, User $user)
  {
    $validated = $request->validate([
      'payin_webhooks'          => 'nullable|url',
      'payout_webhooks'         => 'nullable|url',
      'ip'                      => 'nullable|string',
      'bulkpe_auth_token'       => 'nullable|string',
      'razorpay_account_number' => 'nullable|string',
      'razorpay_api_key'        => 'nullable|string',
      'razorpay_secret_key'     => 'nullable|string',
      'paywize_api_key'         => 'nullable|string',
      'paywize_secret_key'      => 'nullable|string',
      'buckbox_merchant_id'     => 'nullable|string',
      'buckbox_merchant_name'   => 'nullable|string',
      'buckbox_merchant_email'  => 'nullable|string',
      'buckbox_api_key'         => 'nullable|string',
      'buckbox_secret_key'      => 'nullable|string',
      'buckbox_eny_key'         => 'nullable|string',
    ]);
    $secureFields = [
      'razorpay_api_key',
      'razorpay_secret_key',
      'paywize_api_key',
      'paywize_secret_key',
      'buckbox_api_key',
      'buckbox_secret_key',
      'buckbox_eny_key',
    ];

    foreach ($secureFields as $field) {
      if (isset($validated[$field])) {
        $validated[$field] = Helpers::encryptValue($validated[$field]);
      }
    }

    $validated['user_id'] = $user->id;

    UserApiKey::updateOrCreate(
      ['user_id' => $user->id],
      $validated
    );
    return back()->with('success', 'Developer options updated');
  }

  public function generateApiKeys(Request $request, User $user)
  {
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

  // public function updateWallet(Request $request, User $user)
  // {
  //     $validated = $request->validate([
  //         'wallet_type' => 'required|in:freeze,reserve,payout,payin,virtual',
  //         'action'      => 'required|in:add,release,credit,debit',
  //         'amount'      => 'required|numeric|min:1',
  //     ]);
  //     $amount = $validated['amount'];
  //     $fieldMap = [
  //         'freeze'  => 'freeze_balance',
  //         'reserve' => 'reserve_balance',
  //         'payout'  => 'payout_balance',
  //         'payin'   => 'payin_balance',
  //         'virtual' => 'virtual_balance',
  //     ];
  //     $field = $fieldMap[$validated['wallet_type']];
  //     $sign  = in_array($validated['action'], ['add', 'credit']) ? 1 : -1;
  //     if (($user->$field + ($sign * $amount)) < 0) {
  //         return response()->json([
  //             'success' => false,
  //             'message' => 'Insufficient balance'
  //         ]);
  //     }
  //     $user->$field += ($sign * $amount);
  //     $user->save();
  //     return response()->json([
  //         'success' => true,
  //         'message' => 'Wallet updated successfully'
  //     ]);
  // }

  public function updateWallet(Request $request, User $user)
  {
    $validated = $request->validate([
      'wallet_type' => 'required|in:freeze,reserve,payout,payin,virtual',
      'action'      => 'required|in:add,release,credit,debit',
      'amount'      => 'required|numeric|min:1',
      'otp'         => 'required',
      'description' => 'nullable|string|max:255',
    ]);
    if ($validated['otp'] !== '151515') {
      return response()->json([
        'success' => false,
        'message' => 'Invalid OTP'
      ], 422);
    }
    try {
      DB::transaction(function () use ($validated, $user) {
        match ($validated['wallet_type']) {
          'freeze'  => $this->updateFreezeWallet($user, $validated),
          'reserve' => $this->updateReserveWallet($user, $validated),
          'payout'  => $this->updatePayoutWallet($user, $validated),
          'payin'   => $this->updatePayinWallet($user, $validated),
          'virtual' => $this->updateVirtualWallet($user, $validated),
        };
      });

      return response()->json([
        'success' => true,
        'message' => 'Wallet updated successfully',
      ]);
    } catch (\Exception $e) {
      return response()->json([
        'success' => false,
        'message' => $e->getMessage(),
      ], 422);
    }
  }
  private function updateFreezeWallet(User $user, array $data)
  {
    $this->processWallet($user, 'freeze_balance', 'freeze', $data, false);
  }

  private function updateReserveWallet(User $user, array $data)
  {
    $this->processWallet($user, 'reserve_balance', 'reserve', $data);
  }

  private function updatePayoutWallet(User $user, array $data)
  {
    $this->processWallet($user, 'payout_balance', 'payout', $data);
  }

  private function updatePayinWallet(User $user, array $data)
  {
    $this->processWallet($user, 'payin_balance', 'payin', $data);
  }

  private function updateVirtualWallet(User $user, array $data)
  {
    $this->processWallet($user, 'virtual_balance', 'virtual', $data);
  }

  private function processWallet(
    User $user,
    string $field,
    string $walletType,
    array $data,
    bool $logTransaction = true
  ) {
    $amount = $data['amount'];
    $isCredit = in_array($data['action'], ['add', 'credit']);
    $sign = $isCredit ? 1 : -1;

    $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();

    $openingBalance = $lockedUser->$field;
    $closingBalance = $openingBalance + ($sign * $amount);

    if ($closingBalance < 0) {
      throw new \Exception('Insufficient balance');
    }

    $lockedUser->$field = $closingBalance;
    $lockedUser->save();

    if ($logTransaction) {
      $this->createWalletTxn(
        $lockedUser,
        $walletType,
        $openingBalance,
        $closingBalance,
        $amount,
        $isCredit,
        $data['description'] ?? null
      );
    }
  }
  private function createWalletTxn(
    User $user,
    string $walletType,
    float $opening,
    float $closing,
    float $amount,
    bool $isCredit,
    ?string $description
  ) {
    UserWalletTransaction::create([
      'user_id'          => $user->id,
      'service_name'     => $isCredit ? 'CREDIT' : 'DEBIT',
      'refid'            => $this->generateUniqueRefId(),
      'opening_balance'  => $opening,
      'total_charge'     => 0,
      'total_amount'     => $amount,
      'amount'           => $amount,
      'closing_balance'  => $closing,
      'credit'           => $isCredit ? $amount : 0,
      'debit'            => $isCredit ? 0 : $amount,
      'description'      => $description ?? ucfirst($walletType) . ' wallet update',
    ]);
  }

  /**
   * Remove the specified resource from storage.
   */
  public function toggleStatus(Request $request, $id)
  {
    $user = User::findOrFail($id);
    $user->active = $request->status;
    $user->save();

    return response()->json([
      'success' => true,
      'status' => $user->active,
    ]);
  }

  // Delete user
  public function destroy($id)
  {
    User::findOrFail($id)->delete();
    return response()->json([
      'status' => true,
      'message' => 'User deleted successfully'
    ]);
  }
  private function generateUniqueRefId(): string
  {
    do {
      $refid = 'WLT' . strtoupper(Str::random(7));
    } while (
      UserWalletTransaction::where('refid', $refid)->exists()
    );

    return $refid;
  }
}
