@extends('layouts/layoutMaster')

@section('title', 'Fund Requests')

@section('vendor-style')
  <style>
    #fund-request-table {
      table-layout: fixed;
      width: 100% !important;
    }

    #fund-request-table_wrapper .dt-info,
    #fund-request-table_wrapper .dt-paging {
      display: inline-block !important;
      padding: 10px !important;
    }

    #fund-request-table th:first-child,
    #fund-request-table td:first-child {
      white-space: nowrap;
      width: 80px !important;
    }

    #fund-request-table_wrapper .dt-info {
      margin-top: 10px !important;
    }

    #fund-request-table_wrapper .dt-paging {
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
  @vite(['resources/assets/vendor/libs/moment/moment.js', 'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js', 'resources/assets/vendor/libs/select2/select2.js'])
@endsection

@section('page-script')
  <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>;
  <script>
    window.routes = {
      fundRequestData: "{{ route('admin.fund-requests.data') }}",
      updateFundStatusUrl: "{{ route('admin.fund-request.updateStatus') }}"
    };
    window.csrfToken = "{{ csrf_token() }}";
  </script>
  @vite('resources/assets/js/fund-requests.js')
@endsection

@section('content')
  <div class="row">
    <div class="col-12">
      <div class="card border shadow-xs mb-4">

        <div class="card-header pb-0">
          <div class="d-sm-flex align-items-center">
            <div>
              <h5 class="font-weight-semibold text-lg mb-0">Fund Requests</h5>
            </div>
          </div>
        </div>

        <div class="card-body">
          <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 mt-3 g-3 align-items-end">
            <!-- User -->
            <div class="col mt-1">
              <label class="form-label small text-muted">User</label>
              <input type="text" id="filter-user" class="form-control" placeholder="Name / Email / Phone">
            </div>

            <!-- Txn ID -->
            <div class="col mt-1">
              <label class="form-label small text-muted">Request ID</label>
              <input type="text" id="filter-reqid" class="form-control" placeholder="Request ID">
            </div>

            <!-- Account -->
            <div class="col mt-1">
              <label class="form-label small text-muted">Wallet Txn ID</label>
              <input type="text" id="filter-wallet-txn-id" class="form-control" placeholder="Wallet Txn ID">
            </div>

            <!-- Contact -->
            <div class="col mt-1">
              <label class="form-label small text-muted">Sender Account Number</label>
              <input type="text" id="filter-sender-account-number" class="form-control"
                placeholder="Sender Account Number">
            </div>

            <!-- API Txn ID -->
            <div class="col mt-1">
              <label class="form-label small text-muted">Company Account Number</label>
              <input type="text" id="filter-company-account-number" class="form-control"
                placeholder="Company Account Number">
            </div>

            <div class="col mt-1">
              <label class="form-label small text-muted">Transaction UTR</label>
              <input type="text" id="filter-transaction-utr" class="form-control" placeholder="Transaction UTR">
            </div>

            <!-- Txn Status -->
            <div class="col mt-1">
              <label class="form-label small text-muted">Status</label>
              <select id="filter-status" class="form-select">
                <option value="">All</option>
                <option value="A">Accepted</option>
                <option value="R">Rejected</option>
                <option value="P">Pending</option>
              </select>
            </div>

            <div class="col mt-1">
              <label class="form-label small text-muted">Wallet Type</label>
              <select id="filter-source" class="form-select">
                <option value="">All</option>
                <option value="Payin">Payin</option>
                <option value="Payout">Payout</option>
                <option value="Virtual">Virtual</option>
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
          {{-- 📊 TABLE --}}
          <div class="d-flex justify-content-end mt-4 align-items-center p-3 border-bottom bg-white">
            <div id="export-buttons"></div>
          </div>

          <!-- 🔹 Table Scroll Area ONLY -->
          <div class="table-responsive">
            <table id="fund-request-table" class="table table-hover align-middle w-100">
              <thead class="bg-light">
                <tr>
                  <th>S.No</th>
                  <th>User Info</th>
                  <th>Request Id</th>
                  <th>Wallet txn Id</th>
                  <th>Amount</th>
                  <th>Sender Account Number</th>
                  <th>Company Account Number</th>
                  <th>Transaction Utr</th>
                  <th>Mode</th>
                  <th>Wallet Type</th>
                  <th>Status</th>
                  <th>Created At</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="offcanvas offcanvas-end" tabindex="-1" id="fundRequestSidebar" style="width: 420px;">
    <div class="offcanvas-header border-bottom">
      <h5 class="offcanvas-title">Fund Request Details</h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>

    <div class="offcanvas-body" id="fundRequestDetails">
      <div class="text-center py-5">
        <div class="spinner-border text-primary"></div>
      </div>
    </div>
  </div>
@endsection
