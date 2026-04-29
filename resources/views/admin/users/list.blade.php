@extends('layouts/layoutMaster')

@section('title', 'User List')

@section('vendor-style')
  <style>
    #users-table {
      table-layout: fixed;
      width: 100% !important;
    }

    #users-table_wrapper .dt-info,
    #users-table_wrapper .dt-paging {
      display: inline-block !important;
      padding: 10px !important;
    }

    #users-table th:first-child,
    #users-table td:first-child {
      white-space: nowrap;
      width: 80px !important;
    }

    #users-table_wrapper .dt-info {
      margin-top: 10px !important;
    }

    #users-table_wrapper .dt-paging {
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
  @vite(['resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss', 'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss', 'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss', 'resources/assets/vendor/libs/select2/select2.scss', 'resources/assets/vendor/libs/@form-validation/form-validation.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/moment/moment.js', 'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js', 'resources/assets/vendor/libs/select2/select2.js', 'resources/assets/vendor/libs/@form-validation/popular.js', 'resources/assets/vendor/libs/@form-validation/bootstrap5.js', 'resources/assets/vendor/libs/@form-validation/auto-focus.js', 'resources/assets/vendor/libs/cleave-zen/cleave-zen.js'])
@endsection

@section('page-script')
  <script>
    window.routes = {
      userData: "{{ route('admin.user.data') }}",
      deleteUser: "{{ url('admin/user') }}",
      toggleStatus: "{{ url('admin/user/status') }}"
    };
    window.csrfToken = "{{ csrf_token() }}";
  </script>
  @vite('resources/assets/js/user-list.js')
@endsection

@section('content')
  <!-- Users List Table -->
  <div class="row">
    <div class="col-12">
      <div class="card border shadow-xs mb-4">
        <div class="card-header border-bottom-0 pb-0">
          <div class="d-sm-flex align-items-center">
            <div>
              <h5 class="font-weight-semibold text-lg mb-0">Users list</h5>
            </div>
            <div class="ms-auto d-flex">
              <button type="button" class="btn btn-primary">
                <a href="{{ route('admin.user.create') }}">
                  <span class="btn-inner--text text-white"><i class="fa fa-add"></i> Add member</span>
                </a>
              </button>
            </div>
          </div>
        </div>
        <div class="card-body py-0">
          <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 mt-3 g-3 align-items-end">
            <!-- Txn ID -->
            <div class="col mt-1">
              <label class="form-label small text-muted">Uid</label>
              <input type="text" id="filter-uid" class="form-control" placeholder="uid">
            </div>

            <!-- User -->
            <div class="col mt-1">
              <label class="form-label small text-muted">User</label>
              <input type="text" id="filter-user" class="form-control"
                placeholder="Name / Email / Phone">
            </div>

            <!-- Txn Status -->
            <div class="col mt-1">
              <label class="form-label small text-muted">Status</label>
              <select id="filter-status" class="form-select">
                <option value="">All</option>
                <option value="A">Active</option>
                <option value="B">Blocked</option>
              </select>
            </div>

            <div class="col mt-1">
              <label class="form-label small text-muted">Deleted</label>
              <select id="filter-delete" class="form-select">
                <option value="">All</option>
                <option value="0">Not Deleted</option>
                <option value="1">Deleted</option>
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

          <div class="d-flex justify-content-end align-items-center p-3 border-bottom bg-white">
            <div id="export-buttons"></div>
          </div>
          <div class="table-responsive px-0">
            <table id="users-table" class="table table-hover align-middle w-100">
              <thead class="bg-light">
                <tr>
                  <th class="text-start px-2">S.No</th>
                  <th class="text-start px-2">UID</th>
                  <th class="text-start px-2">Company Name</th>
                  <th class="text-start px-2">Phone Number</th>
                  <th class="text-start px-2">Email</th>
                  <th class="text-start px-2">Status</th>
                  <th class="text-start px-2">Created</th>
                  <th class="text-start px-2" class="text-center">Action</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
