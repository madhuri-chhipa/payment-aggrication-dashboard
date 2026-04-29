@extends('layouts/layoutMaster')

@section('title', 'BBPS Bill Transactions')

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

    #txn-table th:first-child,
    #txn-table td:first-child {
      white-space: nowrap;
      width: 80px !important;
    }

    /*
      #txn-table th:first-child,
      #txn-table td:first-child {
        white-space: nowrap;
        width: 50px !important;
        padding: 5px !important;
      } */

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
  </style>
  @vite(['resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss', 'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss', 'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js'])
@endsection

@section('page-script')
  <script>
    window.routes = {
      txnData: "{{ route('bbps-transactions.data') }}",
      viewTxn: "{{ url('/bbps/transaction') }}"
    };
  </script>
  @vite('resources/assets/js/user-bbps-transactions.js')
@endsection


@section('content')

  <div class="card">
    <div class="card-header px-1 pt-1 d-flex justify-content-between align-items-center">
      <h5 class="mb-0 pt-2 ps-4">Bharat Connect Bill Transactions</h5>
      <img src="{{ asset('assets/img/logo/bbpsPrimaryLogo.jpg') }}" alt="Bharat-Connect" style="height: 60px;">
    </div>

    <div class="card-body">
      <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3 align-items-end">
        <div class="col">
          <label>Request ID</label>
          <input type="text" id="filter-request-id" placeholder="Request ID" class="form-control">
        </div>

        <div class="col">
          <label>Biller</label>
          <input type="text" id="filter-biller" placeholder="Biller ID / Name" class="form-control">
        </div>

        <div class="col">
          <label>BBPS Txn Ref</label>
          <input type="text" id="filter-bbps-txn" placeholder="BBPS Txn Ref" class="form-control">
        </div>

        <div class="col">
          <label>Status</label>
          <select id="filter-status" class="form-select">
            <option value="">All</option>
            <option value="SUCCESS">Success</option>
            <option value="FAILED">Failed</option>
            <option value="PENDING">Pending</option>
          </select>
        </div>

        <div class="col">
          <label>From Date</label>
          <input type="date" id="filter-from-date" class="form-control">
        </div>

        <div class="col">
          <label>To Date</label>
          <input type="date" id="filter-to-date" class="form-control">
        </div>

        <div class="col d-flex gap-2">
          <button class="btn btn-primary w-100" id="applyFilter">
            Apply
          </button>

          <button class="btn btn-outline-secondary w-100" id="resetFilter">
            Reset
          </button>
        </div>
      </div>

      <div class="d-flex justify-content-end align-items-center p-3 border-bottom bg-white">
        <div id="export-buttons"></div>
      </div>

      <div class="table-responsive">
        <table id="txn-table" class="table table-hover align-middle">
          <thead>
            <tr>
              <th>#</th>
              <th>Request ID</th>
              <th>Biller</th>
              <th>Customer</th>
              <th>Amount</th>
              <th>Status</th>
              <th>BBPS Txn</th>
              <th>Approval Ref</th>
              <th>Created</th>
              <th>Action</th>
            </tr>
          </thead>

          <tbody></tbody>

        </table>

      </div>

    </div>

  </div>


  <!-- SIDEBAR -->
  <div id="txnSidebar" class="txn-sidebar shadow">

    <div class="sidebar-header d-flex justify-content-between">
      <h5>Transaction Details</h5>
      <button class="btn btn-light btn-sm" id="closeSidebar">×</button>
    </div>

    <div id="txnSidebarBody" class="sidebar-body"></div>

  </div>

  <div id="sidebarOverlay"></div>

@endsection
