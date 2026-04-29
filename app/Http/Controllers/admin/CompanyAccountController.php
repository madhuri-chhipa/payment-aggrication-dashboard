<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\CompanyAccount;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class CompanyAccountController extends Controller
{
  public function index()
  {
    return view('admin.company_accounts.list');
  }

  /**
   * DataTable Listing
   */
  public function data(Request $request)
  {
    $query = CompanyAccount::query()
      ->select([
        'id',
        'bank_name',
        'branch_name',
        'account_holder_name',
        'account_number',
        'ifsc',
        'status',
        'created_at'
      ])
      ->when(
        $request->bank_name,
        fn($q, $v) =>
        $q->where('bank_name', 'like', "%{$v}%")
      )
      ->when(
        $request->account_holder_name,
        fn($q, $v) =>
        $q->where('account_holder_name', 'like', "%{$v}%")
      )
      ->when(
        $request->account_number,
        fn($q, $v) =>
        $q->where('account_number', 'like', "%{$v}%")
      )
      ->when(
        $request->status !== null && $request->status !== '',
        fn($q) =>
        $q->where('status', $request->status)
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
      });


    return DataTables::of($query)
      ->addIndexColumn()

      ->editColumn('status', function ($row) {

        $textStatus = $row->status == 'A' ? 'Active' : 'Blocked';

        return '
        <div class="form-check form-switch d-flex justify-content-center">
            <input class="form-check-input status-toggle"
                style="width:30px;"
                type="checkbox"
                role="switch"
                data-id="' . $row->id . '"
                ' . ($row->status == 'A' ? 'checked' : '') . '>
        </div>
        <span class="d-none export-status">' . $textStatus . '</span>';
      })

      ->editColumn('created_at', function ($t) {
        return $t->created_at
          ? \Carbon\Carbon::parse($t->created_at)->format('d/m/Y H:i:s')
          : '-';
      })
      ->addColumn('action', function ($row) {
        return '
          <div class="dropdown">
              <button class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                  Actions
              </button>
              <ul class="dropdown-menu">
                  <li>
                    <button class="dropdown-item edit-account"
                      data-id="' . $row->id . '">
                      <i class="fa fa-edit me-1"></i>Edit
                    </button>
                  </li>
                  <li>
                    <button class="dropdown-item text-danger delete-account"
                      data-id="' . $row->id . '">
                      <i class="fa fa-trash-can me-1"></i>Delete
                    </button>
                  </li>
              </ul>
          </div>';
      })

      ->rawColumns(['status', 'action'])
      ->toJson();
  }

  /**
   * Store New Account
   */
  public function store(Request $request)
  {
    $request->validate([
      'bank_name' => 'required|string|max:255',
      'branch_name' => 'required|string|max:255',
      'account_holder_name' => 'required|string|max:255',
      'account_number' => 'required|string|max:50',
      'ifsc' => 'required|string|max:20'
    ]);

    CompanyAccount::create($request->all());

    return response()->json(['message' => 'Company account created successfully']);
  }

  /**
   * Edit
   */
  public function edit($id)
  {
    return response()->json(CompanyAccount::findOrFail($id));
  }

  /**
   * Update
   */
  public function update(Request $request, $id)
  {
    $account = CompanyAccount::findOrFail($id);

    $request->validate([
      'bank_name' => 'required|string|max:255',
      'branch_name' => 'required|string|max:255',
      'account_holder_name' => 'required|string|max:255',
      'account_number' => 'required|string|max:50',
      'ifsc' => 'required|string|max:20'
    ]);

    $account->update($request->all());

    return response()->json(['message' => 'Company account updated successfully']);
  }

  /**
   * Toggle Status
   */
  public function changeStatus(Request $request, $id)
  {
    $account = CompanyAccount::findOrFail($id);
    $account->status = $request->status;
    $account->save();

    return response()->json([
      'success' => true,
      'status' => $account->status
    ]);
  }

  /**
   * Delete
   */
  public function destroy($id)
  {
    CompanyAccount::findOrFail($id)->delete();
    return response()->json(['message' => 'Company account deleted successfully']);
  }
}
