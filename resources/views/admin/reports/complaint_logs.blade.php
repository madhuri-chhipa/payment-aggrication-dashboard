@extends('layouts/layoutMaster')

@section('title', 'Complaint Logs')

@section('vendor-style')
  <style>
    #txn-table {
      table-layout: fixed;
      width: 100% !important;
    }

    #txn-table_wrapper .dt-info,
    #txn-table_wrapper .dt-paging {
      display: inline-block !important;
      padding: 10px !important;
    }

    #txn-table th:first-child,
    #txn-table td:first-child {
      white-space: nowrap;
      width: 80px !important;
    }

    #txn-table_wrapper .dt-info {
      margin-top: 10px !important;
    }

    #txn-table_wrapper .dt-paging {
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

    .txn-sidebar {
      position: fixed;
      top: 0;
      right: -420px;
      width: 420px;
      height: 100%;
      background: #fff;
      z-index: 1055;
      transition: right 0.3s ease;
      display: flex;
      flex-direction: column;
    }

    .txn-sidebar.open {
      right: 0;
    }

    .sidebar-header {
      padding: 15px;
      border-bottom: 1px solid #eee;
      background: #f8f9fa;
    }

    .sidebar-body {
      padding: 15px;
      overflow-y: auto;
      flex: 1;
    }

    #sidebarOverlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.35);
      display: none;
      z-index: 1050;
    }

    #sidebarOverlay.show {
      display: block;
    }

    td,
    th {
      padding-right: 5px !important;
    }
  </style>
  @vite(['resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss', 'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss', 'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss', 'resources/assets/vendor/libs/select2/select2.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/moment/moment.js', 'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js', 'resources/assets/vendor/libs/select2/select2.js'])
@endsection

@section('page-script')
  <script>
    window.routes = {
      complaintData: "{{ route('admin.complaints.data') }}",
      viewComplaint: "{{ url('admin/complaint') }}"
    };
    window.csrfToken = "{{ csrf_token() }}";
  </script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script>
    $(document).on('click', '.check-status', function() {
      let btn = $(this);
      let id = btn.data('id');
      // disable button
      btn.addClass('disabled');
      btn.html('<i class="fa fa-spinner fa-spin me-1"></i> Checking...');
      $.ajax({
        url: `/admin/complaint-track/${id}`,
        type: 'GET',
        success: function(res) {
          if (res.status) {
            toastr.success(
              "Complaint Status: " + res.complaint_status +
              "<br>Remarks: " + (res.remarks ?? 'N/A')
            );
            $('#txn-table').DataTable().ajax.reload();
          } else {
            toastr.error(res.message);
          }
        },
        error: function() {
          toastr.error('Something went wrong.');
        },
        complete: function() {
          // restore button
          btn.removeClass('disabled');
          btn.html('<i class="fa fa-rotate text-info me-1"></i> Track Status');
        }
      });
    });
  </script>
  @vite('resources/assets/js/complaint-logs.js')
@endsection

@section('content')
  <div class="row">
    <div class="col-12">
      <div class="card border shadow-xs mb-4">
        <div class="card-header px-1 pt-1 d-flex justify-content-between align-items-center">
          <h5 class="mb-0 pt-2 ps-4">Bharat Connect Complaint Logs</h5>
          <img src="{{ asset('assets/img/logo/bbpsPrimaryLogo.jpg') }}" alt="Bharat-Connect" style="height: 60px;">
        </div>
        <div class="card-body">
          <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 mt-3 g-3 align-items-end">
            <!-- User -->
            <div class="col mt-1">
              <label class="form-label small text-muted">User</label>
              <input type="text" id="filter-user" class="form-control" placeholder="Name / Email / Phone">
            </div>

            <!-- Txn Ref ID -->
            <div class="col mt-1">
              <label class="form-label small text-muted">Txn Ref ID</label>
              <input type="text" id="filter-txn-ref" class="form-control" placeholder="Txn Ref ID">
            </div>

            <!-- BBPS Complaint ID -->
            <div class="col mt-1">
              <label class="form-label small text-muted">BBPS Complaint ID</label>
              <input type="text" id="filter-bbps-id" class="form-control" placeholder="Complaint ID">
            </div>

            <!-- Complaint Type -->
            <div class="col mt-1">
              <label class="form-label small text-muted">Complaint Type</label>
              <select id="filter-type" class="form-select">
                <option value="">All</option>
                <option value="Transaction">Transaction</option>
                <option value="Account">Account</option>
                <option value="Service">Service</option>
                <option value="Other">Other</option>
              </select>
            </div>

            <!-- Complaint Status -->
            <div class="col mt-1">
              <label class="form-label small text-muted">Status</label>
              <select id="filter-status" class="form-select">
                <option value="">All</option>
                <option value="success">Success</option>
                <option value="pending">Pending</option>
                <option value="failed">Failed</option>
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
          {{-- 📊 TABLE --}}
          <div class="d-flex justify-content-end align-items-center p-3 border-bottom-0 bg-white">
            <div id="export-buttons"></div>
          </div>

          <!-- 🔹 Table Scroll Area ONLY -->
          <div class="table-responsive">
            <table id="txn-table" class="table table-hover align-middle w-100">
              <thead class="bg-light">
                <tr>
                  <th>S.No</th>
                  <th>User Info</th>
                  <th>Txn Ref ID</th>
                  <th>Mobile Number</th>
                  <th>Complaint Type</th>
                  <th>Biller ID</th>
                  <th>Agent ID</th>
                  <th>Description</th>
                  <th>BBPS Complaint ID</th>
                  <th>Status</th>
                  <th>Created At</th>
                  <th class="text-center">Action</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- 🔹 Transaction Details Sidebar -->
  <div id="txnSidebar" class="txn-sidebar shadow">
    <div class="sidebar-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Transaction Details</h5>
      <button class="btn btn-sm btn-light" id="closeSidebar">&times;</button>
    </div>
    <div id="txnSidebarBody" class="sidebar-body">
      <div class="text-center p-4 text-muted">Select a transaction to view details</div>
    </div>
  </div>

  <div id="sidebarOverlay"></div>

@endsection
