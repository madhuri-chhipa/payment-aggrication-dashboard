<?php

namespace App\Http\Controllers\admin;

use App\Helpers\Helpers;
use App\Http\Controllers\Controller;
use App\Models\CompanyDetail;
use App\Models\User;
use App\Models\UserApiKey;
use App\Models\UserLoginLog;
use App\Models\UserService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class UserLoginLogController extends Controller
{
  public function index()
  {
    return view('admin.login_logs.list');
  }
  public function data(Request $request)
  {
    $admin = auth()->guard('admin')->user();
    $query = UserLoginLog::query()
      ->with('user:id,company_name,email,mobile_number')
      ->when($admin->admin_type === 'employee', function ($q) use ($admin) {
        $q->whereHas('user', function ($userQuery) use ($admin) {
          $userQuery->where('created_by', $admin->id);
        });
      })
      ->select([
        'id',
        'user_id',
        'otp',
        'ip_address',
        'latitude',
        'longitude',
        'logged_at',
      ])

      /* ---------- Filters ---------- */
      ->when($request->user, function ($q, $v) {
        $q->whereHas('user', function ($uq) use ($v) {
          $uq->where(function ($sub) use ($v) {
            $sub->where('company_name', 'like', "%{$v}%")
              ->orWhere('email', 'like', "%{$v}%")
              ->orWhere('mobile_number', 'like', "%{$v}%");
          });
        });
      })

      ->when(
        $request->ip_address,
        fn($q, $v) =>
        $q->where('ip_address', 'like', "%{$v}%")
      )

      ->when($request->from_date || $request->to_date, function ($q) use ($request) {
        if ($request->from_date && $request->to_date) {
          $q->whereBetween('logged_at', [
            Carbon::parse($request->from_date)->startOfDay(),
            Carbon::parse($request->to_date)->endOfDay()
          ]);
        } elseif ($request->from_date) {
          $q->whereDate('logged_at', '>=', $request->from_date);
        } elseif ($request->to_date) {
          $q->whereDate('logged_at', '<=', $request->to_date);
        }
      });

    return DataTables::of($query)
      ->addIndexColumn()
      ->addColumn('user_info', function ($log) {
        return '
          <strong>' . ($log->user->company_name ?? '-') . '</strong><br>
          <small>' . ($log->user->email ?? '-') . '</small><br>
          <small>' . ($log->user->mobile_number ?? '-') . '</small>
        ';
      })

      ->editColumn(
        'logged_at',
        fn($log) =>
        Carbon::parse($log->logged_at)->format('d/m/Y H:i:s')
      )

      ->addColumn('location', function ($log) {
        return '
                Lat: ' . ($log->latitude ? Crypt::decryptString($log->latitude) : '-') . '<br>
                Long: ' . ($log->longitude ? Crypt::decryptString($log->longitude) : '-') . '
            ';
      })
      ->rawColumns(['user_info', 'location', 'logged_at'])
      ->toJson();
  }
}
