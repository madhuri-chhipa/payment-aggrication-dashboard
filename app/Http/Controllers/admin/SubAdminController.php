<?php

namespace App\Http\Controllers\admin;

use App\Helpers\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class SubAdminController extends Controller
{
  public function index()
  {
    return view('admin.subadmins.list');
  }

  public function data(Request $request)
  {
    $query = Admin::where('admin_type', '!=', 'admin')
      ->select(['id', 'name', 'email', 'mobile_number', 'admin_type', 'status', 'created_at'])
      ->when(
        $request->name,
        fn($q, $v) =>
        $q->where('name', 'like', "%{$v}%")
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
        $request->mobile_number,
        fn($q, $v) =>
        $q->where('mobile_number', 'like', "%{$v}%")
      )
      ->when(
        $request->email,
        fn($q, $v) =>
        $q->where('email', 'like', "%{$v}%")
      )
      ->when(
        $request->status !== null,
        fn($q) =>
        $q->where('status', $request->status)
      );

    return DataTables::of($query)
      ->addIndexColumn()
      ->editColumn('created_at', fn($u) => $u->created_at->format('d/m/Y H:i:s'))
      ->editColumn('status', function ($u) {
        $textStatus = $u->status == 'A' ? 'Active' : 'Blocked';
        return '
        <div class="form-check form-switch d-flex justify-content-center">
            <input class="form-check-input status-toggle"
                style="width:30px;"
                type="checkbox"
                role="switch"
                data-id="' . $u->id . '"
                ' . ($u->status == 'A' ? 'checked' : '') . '>
        </div>
        <span class="d-none export-status">' . $textStatus . '</span>';
      })
      ->addColumn('action', function ($a) {
        return '
          <button class="btn btn-sm btn-primary edit-subadmin"
              data-id="' . $a->id . '"><i class="fa fa-edit me-1"></i>Edit</button>
          <button class="btn btn-sm btn-danger delete-subadmin"
              data-id="' . $a->id . '"><i class="fa fa-trash-can me-1"></i>Delete</button>
        ';
      })

      ->rawColumns(['status', 'action'])
      ->toJson();
  }

  public function store(Request $request)
  {
    $request->validate([
      'name' => 'required|string',
      'admin_type' => 'required|string',
      'email' => 'required|email|unique:admins',
      'mobile_number' => 'required|unique:admins',
      'password' => 'required|min:8|confirmed',
    ]);

    Admin::create([
      'name' => $request->name,
      'email' => $request->email,
      'mobile_number' => $request->mobile_number,
      'password' => Hash::make($request->password),
      'admin_type' => $request->admin_type,
      'status' => 'A',
    ]);

    return response()->json(data: ['message' => 'Sub Admin created']);
  }

  public function edit($id)
  {
    return Admin::findOrFail($id);
  }

  public function update(Request $request, $id)
  {
    $admin = Admin::findOrFail($id);

    $request->validate([
      'name' => 'required|string',
      'admin_type' => 'required|string',
      'email' => 'required|email|unique:admins,email,' . $id,
      'mobile_number' => 'required|unique:admins,mobile_number,' . $id,
      'password' => 'nullable|min:8|confirmed',
    ]);

    $data = $request->only('name', 'email', 'mobile_number', 'admin_type');

    if ($request->password) {
      $data['password'] = Hash::make($request->password);
    }

    $admin->update($data);

    return response()->json(['message' => 'Sub Admin updated']);
  }

  public function toggleStatus(Request $request, $id)
  { 
    $admin = Admin::findOrFail($id);
    $admin->status = $request->status;
    $admin->save();

    return response()->json([
      'success' => true,
      'status' => $admin->status,
    ]);
  }

  public function destroy($id)
  {
    Admin::findOrFail($id)->delete();
    return response()->json(['message' => 'Sub Admin deleted']);
  }
}
