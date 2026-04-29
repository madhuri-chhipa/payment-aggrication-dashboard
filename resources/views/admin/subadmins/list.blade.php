@extends('layouts/layoutMaster')
@section('title', 'Sub Admins')
@section('vendor-style')
  <style>
    #subadmins-table {
      table-layout: fixed;
      width: 100% !important;
    }

    #subadmins-table_wrapper .dt-info,
    #subadmins-table_wrapper .dt-paging {
      display: inline-block !important;
      padding: 10px !important;
    }

    #subadmins-table th:first-child,
    #subadmins-table td:first-child {
      white-space: nowrap;
      width: 80px !important;
    }

    #subadmins-table_wrapper .dt-info {
      margin-top: 10px !important;
    }

    #subadmins-table_wrapper .dt-paging {
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
@section('content')
  <div class="card">
    <div class="card-header d-flex justify-content-between border-bottom-0 pb-0">
      <h5>Sub Admins</h5>
      <button class="btn btn-primary" id="addNew"><i class="fa fa-add me-2"></i>Add Sub Admin</button>
    </div>

    <div class="card-body">
      <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 mt-3 g-3 align-items-end px-2 px-md-4">
        <!-- User -->
        <div class="col mt-1">
          <label class="form-label small text-muted">Name</label>
          <input type="text" id="filter-name" class="form-control" placeholder="Filter by Name">
        </div>

        <!-- Account -->
        <div class="col mt-1">
          <label class="form-label small text-muted">Email</label>
          <input type="text" id="filter-email" class="form-control" placeholder="Filter by Email">
        </div>

        <!-- API Txn ID -->
        <div class="col mt-1">
          <label class="form-label small text-muted">Phone</label>
          <input type="text" id="filter-phone" class="form-control" placeholder="Filter by Phone">
        </div>

        <!-- Txn Status -->
        <div class="col mt-1">
          <label class="form-label small text-muted">Status</label>
          <select id="filter-status" class="form-select">
            <option value="">All Status</option>
            <option value="A">Active</option>
            <option value="B">Blocked</option>
          </select>
        </div>

        <!-- From Date -->
        <div class="col-2 mt-1">
          <label class="form-label small text-muted">From</label>
          <input type="date" id="filter-from-date" class="form-control">
        </div>

        <!-- To Date -->
        <div class="col-2 mt-1">
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
      <div class="table-responsive">
        <table id="subadmins-table" class="table table-hover align-middle w-100">
          <thead class="bg-light">
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Email</th>
              <th>Mobile</th>
              <th>Admin Type</th>
              <th>Status</th>
              <th>Created</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Modal -->
  <div class="modal fade" id="subAdminModal">
    <div class="modal-dialog">
      <form id="subAdminForm">
        @csrf
        <input type="hidden" id="admin_id">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Sub Admin</h5>
          </div>
          <div class="modal-body">
            <div class="row g-4">
              <div class="col-md-6">
                <label class="form-label">Name</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="Enter Name"
                  class="form-control">
                @error('name')
                  <span class="text-danger">{{ $message }}</span>
                @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}"
                  placeholder="Enter Email Address" class="form-control">
                @error('email')
                  <span class="text-danger">{{ $message }}</span>
                @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">Mobile Number</label>
                <input type="text" id="mobile_number" name="mobile_number" value="{{ old('mobile_number') }}"
                  placeholder="Enter Mobile Number" class="form-control" maxlength="10">
                @error('mobile_number')
                  <span class="text-danger">{{ $message }}</span>
                @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">Admin Type</label>
                <select name="admin_type" id="admin_type" class="form-select">
                  <option value="">Select Admin Type</option>
                  <option value="accountant" {{ old('admin_type') == 'accountant' ? 'selected' : '' }}>Accountant
                  </option>
                  <option value="employee" {{ old('admin_type') == 'employee' ? 'selected' : '' }}>Employee</option>
                </select>
                @error('admin_type')
                  <span class="text-danger">{{ $message }}</span>
                @enderror
              </div>

              <div class="col-md-6">
                <div class="form-password-toggle">
                  <label class="form-label" for="basic-default-password12">Password</label>
                  <div class="input-group">
                    <input type="password" id="password" class="form-control" value="{{ old('password') }}"
                      name="password"
                      placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                      aria-describedby="basic-default-password2" />
                    <span id="basic-default-password2" class="input-group-text cursor-pointer"><i
                        class="icon-base ti tabler-eye-off"></i></span>
                  </div>
                  @error('password')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>
              </div>

              <div class="col-md-6">
                <div class="form-password-toggle">
                  <label class="form-label" for="basic-default-password12">Confirm Password</label>
                  <div class="input-group">
                    <input type="password" class="form-control" id="password_confirmation"
                      value="{{ old('password_confirmation') }}" name="password_confirmation"
                      placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                      aria-describedby="basic-default-password2" />
                    <span id="basic-default-password2" class="input-group-text cursor-pointer"><i
                        class="icon-base ti tabler-eye-off"></i></span>
                  </div>
                  @error('password_confirmation')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>
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
      data: "{{ route('admin.subadmins.data') }}",
      store: "{{ route('admin.subadmins.store') }}",
      update: "{{ url('admin/sub-admins') }}",
      edit: "{{ url('admin/sub-admins') }}",
      delete: "{{ url('admin/sub-admins') }}",
      status: "{{ url('admin/sub-admins/status') }}"
    };
  </script>
  @vite('resources/assets/js/subadmins.js')
@endsection
