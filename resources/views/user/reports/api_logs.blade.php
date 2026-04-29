@extends('layouts/layoutMaster')

@section('title', 'API Logs')

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

    #txn-table th:first-child,
    #txn-table td:first-child {
      white-space: nowrap;
      width: 80px !important;
    }
  </style>
  @vite(['resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss', 'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss', 'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss', 'resources/assets/vendor/libs/select2/select2.scss', 'resources/assets/vendor/libs/@form-validation/form-validation.scss'])
@endsection

@section('vendor-script')
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script>
    $(document).on('click', '.view-log', function() {
      let id = $(this).data('id');
      let type = $(this).data('type');

      $.ajax({
        url: window.routes.viewTxn + '/' + id,
        type: 'GET',
        success: function(res) {

          let data = res[type] ?? 'No Data';

          try {
            data = JSON.stringify(JSON.parse(data), null, 2);
          } catch (e) {}

          // ✅ Set dynamic title
          $('#logModalTitle').text(type.charAt(0).toUpperCase() + type.slice(1));

          // ✅ Set content
          $('#logContent').text(data);

          $('#logModal').modal('show');
        }
      });
    });
  </script>
  @vite(['resources/assets/vendor/libs/moment/moment.js', 'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js', 'resources/assets/vendor/libs/select2/select2.js', 'resources/assets/vendor/libs/@form-validation/popular.js', 'resources/assets/vendor/libs/@form-validation/bootstrap5.js', 'resources/assets/vendor/libs/@form-validation/auto-focus.js', 'resources/assets/vendor/libs/cleave-zen/cleave-zen.js'])
@endsection
@section('page-script')
  <script>
    window.routes = {
      txnData: "{{ route('api-logs.data') }}",
      viewTxn: "{{ url('/api-logs') }}"
    };
    window.csrfToken = "{{ csrf_token() }}";
  </script>
  @vite('resources/assets/js/user-api-logs.js')
@endsection

@section('content')
  <div class="row">
    <div class="col-12">
      <div class="card border shadow-xs mb-4">

        <div class="card-header pb-0">
          <div class="d-sm-flex align-items-center">
            <div>
              <h5 class="font-weight-semibold text-lg mb-0">API's Logs</h5>
            </div>
          </div>
        </div>

        <div class="card-body">
          <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 mt-3 g-3 align-items-end">

            <!-- API Txn ID -->
            <div class="col mt-1">
              <label class="form-label small text-muted">Event</label>
              <input type="text" id="filter-event" class="form-control" placeholder="Event">
            </div>

            <div class="col mt-1">
              <label class="form-label small text-muted">API Url</label>
              <input type="text" id="filter-api-url" class="form-control" placeholder="API Url">
            </div>

            <div class="col mt-1">
              <label class="form-label small text-muted">Http Code</label>
              <input type="text" id="filter-http-code" class="form-control" placeholder="Http Code">
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
          <div class="d-flex justify-content-end align-items-center p-3 border-bottom bg-white">
            <div id="export-buttons"></div>
          </div>

          <!-- 🔹 Table Scroll Area ONLY -->
          <div class="table-responsive">
            <table id="txn-table" class="table table-hover align-middle w-100">
              <thead class="bg-light">
                <tr>
                  <th>S.No</th>
                  <th>Event</th>
                  <th>Api Url</th>
                  <th>header</th>
                  <th>Request</th>
                  <th>Http Code</th>
                  <th>Response</th>
                  <th>Created At</th>
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
      <h5 class="mb-0">Log Details</h5>
      <button class="btn btn-sm btn-light" id="closeSidebar">&times;</button>
    </div>
    <div id="txnSidebarBody" class="sidebar-body">
      <div class="text-center p-4 text-muted">Select a log to view details</div>
    </div>
  </div>

  <div id="sidebarOverlay"></div>
  <!-- Modal -->
  <div class="modal fade" id="logModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">

        <div class="modal-header">
          <h5 class="modal-title" id="logModalTitle">Log Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <pre id="logContent" style="white-space: pre-wrap;"></pre>
        </div>

      </div>
    </div>
  </div>
@endsection
