@extends('layouts/layoutMaster')

@section('title', 'User Login Logs')

@section('vendor-style')
  <style>
    #login-logs-table {
      table-layout: fixed;
      width: 100% !important;
    }

    #login-logs-table_wrapper .dt-info,
    #login-logs-table_wrapper .dt-paging {
      display: inline-block !important;
      padding: 10px !important;
    }

    #login-logs-table th:first-child,
    #login-logs-table td:first-child {
      white-space: nowrap;
      width: 80px !important;
    }

    #login-logs-table_wrapper .dt-info {
      margin-top: 10px !important;
    }

    #login-logs-table_wrapper .dt-paging {
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
      loginLogData: "{{ route('admin.userLoginLog.data') }}"
    };
  </script>
  @vite('resources/assets/js/login-logs.js')
@endsection

@section('content')
<div class="card">
    <div class="card-header border-bottom-0 pb-0">
      <h5 class="mb-0">User Login Logs</h5>
    </div>

    <div class="card-body">
        <!-- Filters -->
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 mt-3 g-3 align-items-end">
            <div class="col">
                <input type="text" id="filter-user" class="form-control" placeholder="User name / email / phone">
            </div>

            <div class="col">
                <input type="text" id="filter-ip" class="form-control" placeholder="IP Address">
            </div>

            <div class="col">
                <input type="date" id="filter-from-date" class="form-control">
            </div>

            <div class="col">
                <input type="date" id="filter-to-date" class="form-control">
            </div>

            <!-- Buttons -->
            <div class="col d-flex gap-3">
                <button class="btn btn-primary w-100" id="applyFilter">
                    <i class="fa fa-filter me-1"></i> Apply
                </button>
                <button class="btn btn-outline-secondary w-100" id="resetFilter">
                    <i class="fa fa-refresh me-1"></i> Reset
                </button>
            </div>
        </div>
        <!-- Table -->
        <div class="d-flex justify-content-end align-items-center p-3 border-bottom-0 bg-white">
            <div id="export-buttons"></div>
        </div>
        <!-- 🔹 Table Scroll Area ONLY -->
        <div class="table-responsive">
            <table id="login-logs-table" class="table table-hover align-middle w-100">
                <thead class="bg-light">
                    <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>OTP</th>
                    <th>IP Address</th>
                    <th>Location</th>
                    <th>Logged At</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection
