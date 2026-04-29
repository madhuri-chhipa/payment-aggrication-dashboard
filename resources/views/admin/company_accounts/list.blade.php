@extends('layouts/layoutMaster')
@section('title', 'Company Bank Accounts')

@section('vendor-style')
  <style>
    #accounts-table {
      table-layout: fixed;
      width: 100% !important;
    }

    #accounts-table_wrapper .dt-info,
    #accounts-table_wrapper .dt-paging {
      display: inline-block !important;
      padding: 10px !important;
    }

    #accounts-table th:first-child,
    #accounts-table td:first-child {
      white-space: nowrap;
      width: 80px !important;
    }

    #accounts-table_wrapper .dt-info {
      margin-top: 10px !important;
    }

    #accounts-table_wrapper .dt-paging {
      float: right !important;
    }

    .dt-buttons.btn-group {
      display: flex !important;
      gap: 8px;
    }

    /* Restore full rounded corners for ALL DataTables buttons */
    .dt-buttons.btn-group>.btn {
      border-radius: 6px !important;
    }

    /* Override Bootstrap rule removing RIGHT radius */
    .dt-buttons.btn-group:not(.btn-group-vertical)>.btn:not(:last-child):not(.dropdown-toggle),
    .dt-buttons.btn-group:not(.btn-group-vertical)>.btn.dropdown-toggle-split:first-child,
    .dt-buttons.btn-group:not(.btn-group-vertical)>.btn-group:not(:last-child)>.btn {
      border-radius: 6px !important;
    }

    /* Override Bootstrap rule removing LEFT radius */
    .dt-buttons.btn-group:not(.btn-group-vertical)>.btn:nth-child(n+2),
    .dt-buttons.btn-group:not(.btn-group-vertical)> :not(.btn-check)+.btn,
    .dt-buttons.btn-group:not(.btn-group-vertical)>.btn-group:not(:first-child)>.btn {
      border-radius: 6px !important;
    }
  </style>
  @vite(['resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss', 'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss', 'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss', 'resources/assets/vendor/libs/select2/select2.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js', 'resources/assets/vendor/libs/select2/select2.js'])
@endsection

@section('content')
  <div class="card">
    <div class="card-header d-flex justify-content-between">
      <h5>Company Bank Accounts</h5>
      <button class="btn btn-primary" id="addNew"><i class="fa fa-add me-2"></i>Add Account</button>
    </div>

    <div class="card-body">
      {{-- Filters --}}
      <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 mt-3 g-3 align-items-end px-2 px-md-4 ">
        <!-- User -->
        <div class="col mt-1">
          <label class="form-label small text-muted">Bank Name</label>
          <input type="text" id="filter-bank" class="form-control" placeholder="Bank Name">
        </div>

        <!-- Txn ID -->
        <div class="col mt-1">
          <label class="form-label small text-muted">Account Holder</label>
          <input type="text" id="filter-holder" class="form-control" placeholder="Account Holder">
        </div>

        <!-- Account -->
        <div class="col mt-1">
          <label class="form-label small text-muted">Account Number</label>
          <input type="text" id="filter-account" class="form-control" placeholder="Account Number">
        </div>

        <!-- Status -->
        <div class="col mt-1">
          <label class="form-label small text-muted">Status</label>
          <select id="filter-status" class="form-select">
            <option value="">All</option>
            <option value="A">Active</option>
            <option value="B">Blocked</option>
          </select>
        </div>

        <!-- From Date -->
        <div class="col mt-1">
          <label class="form-label small text-muted">From</label>
          <input type="date" id="filter-from-date" class="form-control">
        </div>

        <!-- To Date -->
        <div class="col mt-1">
          <label class="form-label small text-muted">To</label>
          <input type="date" id="filter-to-date" class="form-control">
        </div>

        <!-- Buttons -->
        <div class="col mt-1 d-flex gap-2">
          <button class="btn btn-primary w-100" id="applyFilter">
            <i class="fa fa-filter me-1"></i> Apply
          </button>
          <button class="btn btn-outline-secondary w-100" id="resetFilter">
            <i class="fa fa-refresh me-1"></i> Reset
          </button>
        </div>
      </div>

      <div class="d-flex justify-content-end align-items-center p-3 border-bottom-0 bg-white">
        <div id="export-buttons"></div>
      </div>

      <!-- 🔹 Table Scroll Area ONLY -->
      <div class="table-responsive">
        <table id="accounts-table" class="table table-hover align-middle w-100">
          <thead class="bg-light">
            {{-- Table --}}
            <tr>
              <th>S.No.</th>
              <th>Bank Name</th>
              <th>Branch</th>
              <th>Account Holder</th>
              <th>Account Number</th>
              <th>IFSC</th>
              <th>Status</th>
              <th>Created</th>
              <th>Action</th>
            </tr>
          </thead>
        </table>
      </div>
    </div>
  </div>

  {{-- Modal --}}
  <div class="modal fade" id="accountModal">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <form id="accountForm">
        @csrf
        <input type="hidden" id="account_id">

        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Company Account</h5>
          </div>

          <div class="modal-body">
            <div class="row g-4">

              <div class="col-md-6">
                <label class="form-label">Bank Name</label>
                <input type="text" id="bank_name" name="bank_name" placeholder="Enter bank name" class="form-control">
              </div>

              <div class="col-md-6">
                <label class="form-label">Branch Name</label>
                <input type="text" id="branch_name" name="branch_name" placeholder="Enter branch name"
                  class="form-control">
              </div>

              <div class="col-md-6">
                <label class="form-label">Account Holder Name</label>
                <input type="text" id="account_holder_name" name="account_holder_name"
                  placeholder="Enter account holder name" class="form-control">
              </div>

              <div class="col-md-6">
                <label class="form-label">Account Number</label>
                <input type="text" id="account_number" name="account_number" placeholder="Enter account number"
                  class="form-control">
              </div>

              <div class="col-md-6">
                <label class="form-label">IFSC Code</label>
                <input type="text" id="ifsc" name="ifsc" placeholder="Enter IFSC code" class="form-control">
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn btn-primary" id="save">Save</button>
          </div>
        </div>
      </form>
    </div>
  </div>
@endsection

@section('page-script')
  <script>
    window.routes = {
      data: "{{ route('admin.company-accounts.data') }}",
      store: "{{ route('admin.company-accounts.store') }}",
      update: "{{ url('admin/company-accounts') }}",
      edit: "{{ url('admin/company-accounts') }}",
      delete: "{{ url('admin/company-accounts') }}",
      status: "{{ url('admin/company-accounts/status') }}"
    };
  </script>

  @vite('resources/assets/js/company_accounts.js')
@endsection
