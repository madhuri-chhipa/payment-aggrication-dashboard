@extends('layouts/layoutMaster')

@section('title', 'Admin Dashboard')

@section('vendor-style')
  <style>
    .col-xxl-20 {
      flex: 0 0 20% !important;
      max-width: 20% !important;
    }

    .admin-profile-card {
      border-radius: 15px;
      overflow: hidden;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
      border: none;
    }

    .admin-profile-header {
      background: linear-gradient(135deg, #4e73df, #1cc88a);
      padding: 25px;
      color: #fff;
      text-align: center;
    }

    .admin-avatar {
      width: 90px;
      height: 90px;
      border-radius: 50%;
      background: #ffffff;
      color: #4e73df;
      font-size: 32px;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 15px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    .admin-status {
      display: inline-block;
      padding: 5px 12px;
      border-radius: 20px;
      font-size: 12px;
      margin-top: 8px;
    }

    .status-active {
      background: rgba(255, 255, 255, 0.2);
      color: #fff;
    }

    .status-inactive {
      background: rgba(255, 255, 255, 0.3);
      color: #fff;
    }

    .admin-profile-body {
      padding: 25px;
    }

    .profile-info-row {
      display: flex;
      align-items: center;
      margin-bottom: 15px;
      font-size: 14px;
    }

    .profile-info-row i {
      width: 30px;
      font-size: 16px;
      color: #4e73df;
    }

    .profile-label {
      font-weight: 600;
      width: 110px;
      color: #555;
    }

    .profile-value {
      color: #333;
    }

    .edit-profile-btn {
      margin-top: 15px;
      width: 100%;
      border-radius: 30px;
      padding: 8px;
      font-size: 14px;
    }
  </style>
  @vite(['resources/assets/vendor/libs/apex-charts/apex-charts.scss', 'resources/assets/vendor/fonts/flag-icons.scss'])
@endsection
@section('vendor-script')
  <script>
    document.addEventListener("DOMContentLoaded", function() {
      const filterType = document.getElementById("filter_type");
      const fromInput = document.querySelector("input[name='from_date']");
      const toInput = document.querySelector("input[name='to_date']");

      function toggleDateInputs() {
        if (filterType.value === "custom") {
          fromInput.style.display = "block";
          toInput.style.display = "block";
        } else {
          fromInput.style.display = "none";
          toInput.style.display = "none";
        }
      }

      toggleDateInputs();
      filterType.addEventListener("change", toggleDateInputs);

      // ajax filter
      loadDashboardData(); // load on page load

      $('#dashboardFilterForm').on('submit', function(e) {
        e.preventDefault();
        loadDashboardData();
      });

      function loadDashboardData() {

        $.ajax({
          url: "{{ route('admin.dashboard.data') }}",
          type: "GET",
          data: {
            filter_type: $('select[name=filter_type]').val(),
            from_date: $('input[name=from_date]').val(),
            to_date: $('input[name=to_date]').val(),
          },
          beforeSend: function() {
            $('.card h4 span').text('...');
          },
          success: function(res) {
            // Update stats
            $('#successFundAmount').text(res.successFundAmount);
            $('#successFundTxn').text(res.successFundTxn);
            $('#pendingFundAmount').text(res.pendingFundAmount);
            $('#pendingFundTxn').text(res.pendingFundTxn);
            $('#totalCreditAmount').text(res.totalCreditAmount);
            $('#totalCreditTxn').text(res.totalCreditTxn);
            $('#successPayoutAmount').text(res.successPayoutAmount);
            $('#successPayoutTxn').text(res.successPayoutTxn);
            $('#pendingPayoutAmount').text(res.pendingPayoutAmount);
            $('#pendingPayoutTxn').text(res.pendingPayoutTxn);
            $('#failedPayoutAmount').text(res.failedPayoutAmount);
            $('#failedPayoutTxn').text(res.failedPayoutTxn);
            $('#successPayinAmount').text(res.successPayinAmount);
            $('#successPayinTxn').text(res.successPayinTxn);
            $('#pendingPayinAmount').text(res.pendingPayinAmount);
            $('#pendingPayinTxn').text(res.pendingPayinTxn);
          }
        });
      }
    });
  </script>
  @vite('resources/assets/vendor/libs/apex-charts/apexcharts.js')
@endsection
@section('page-script')
  @vite('resources/assets/js/dashboards-crm.js')
@endsection

@section('content')

  <div class="row g-6">
    <div class="col-xl-4">
      <div class="card admin-profile-header rounded-4 h-100 p-0">
        <div class="d-flex align-items-end row">
          <div class="col-7">
            <div class="card-body text-nowrap text-start">
              {{-- <h5 class="card-title mb-0">Congratulations {{ $user->company_name }}! 🎉</h5> --}}
              <h4 class="text-white mb-1 text-start">{{ $loggedAdmin->name }}</h4>
              <p class="mb-2 text-start">{{ $loggedAdmin->email }}</p>
              <p class="mb-2 text-start"> {{ $loggedAdmin->mobile_number ?? 'Not Provided' }}</p>
              @if ($loggedAdmin->status == 'A')
                <div class="admin-status status-active">Active</div>
              @else
                <div class="admin-status status-inactive">Inactive</div>
              @endif
              {{-- <a href="javascript:;" class="btn btn-primary">View Sales</a> --}}
            </div>
          </div>
          <div class="col-5 text-center text-sm-left">
            <div class="card-body pb-0 px-0 px-md-4">
              <img src="{{ asset('assets/img/illustrations/card-advance-sale.png') }}" height="140" alt="view sales" />
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-8">
      <div class="card h-100">
        <div class="card-body">
          <ul class="mb-0">
            <li>
              All payouts must comply with RBI KYC/AML norms, including proper customer verification and transaction
              monitoring.
            </li>
            <li>
              Funds can be processed only through authorized banking channels under applicable RBI regulations (PSS Act,
              2007).
            </li>
            <li>
              Entities must ensure proper record-keeping, grievance redressal, and adherence to transaction limits
              prescribed by RBI.
            </li>
          </ul>
        </div>
      </div>
    </div>
    <!-- Total Users -->

    <!-- Active Users -->
    <div class="col-xxl-20 col-lg-3 col-md-4 col-6">
      <div class="card h-100">
        <div class="card-body">
          <div class="badge p-2 bg-label-success mb-3 rounded">
            <i class="ti tabler-user-check icon-28px"></i>
          </div>
          <h5 class="card-title mb-1">Active Users</h5>
          <p class="text-heading mb-3 mt-1">{{ number_format($activeUsers) }}</p>
        </div>
      </div>
    </div>
    <!-- Bank Bal -->
    <div class="col-xxl-20 col-lg-3 col-md-4 col-6">
      <div class="card h-100">
        <div class="card-body">
          <div class="badge p-2 bg-label-primary mb-3 rounded">
            <i class="ti tabler-wallet icon-28px"></i>
          </div>
          <h5 class="card-title mb-1">Bank Balance</h5>
          <p class="text-heading mb-3 mt-1">₹ {{ number_format($bankBalance, 2) }}</p>
        </div>
      </div>
    </div>
    <!-- Total Virtual Balance -->
    <div class="col-xxl-20 col-lg-3 col-md-4 col-6">
      <div class="card h-100">
        <div class="card-body">
          <div class="badge p-2 bg-label-warning mb-3 rounded">
            <i class="ti tabler-wallet icon-28px"></i>
          </div>
          <h5 class="card-title mb-1">Virtual Balance</h5>
          <p class="text-heading mb-3 mt-1">₹ {{ number_format($totalVirtualBalance, 2) }}</p>
        </div>
      </div>
    </div>

    <!-- Total Payin Balance -->
    <div class="col-xxl-20 col-lg-3 col-md-4 col-6">
      <div class="card h-100">
        <div class="card-body">
          <div class="badge p-2 bg-label-info mb-3 rounded">
            <i class="ti tabler-arrow-down-circle icon-28px"></i>
          </div>
          <h5 class="card-title mb-1">Payin Balance</h5>
          <p class="text-heading mb-3 mt-1">₹ {{ number_format($totalPayinBalance, 2) }}</p>
        </div>
      </div>
    </div>

    <!-- Total Payout Balance -->
    <div class="col-xxl-20 col-lg-3 col-md-4 col-6">
      <div class="card h-100">
        <div class="card-body">
          <div class="badge p-2 bg-label-danger mb-3 rounded">
            <i class="ti tabler-arrow-up-circle icon-28px"></i>
          </div>
          <h5 class="card-title mb-1">Payout Balance</h5>
          <p class="text-heading mb-3 mt-1">₹ {{ number_format($totalPayoutBalance, 2) }}</p>
        </div>
      </div>
    </div>

    <form method="GET" id="dashboardFilterForm">
      <div class="card mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">

          <div>
            <strong>Showing:</strong>
            {{ request('from_date') ?? now()->format('d M Y') }}
            -
            {{ request('to_date') ?? now()->format('d M Y') }}
          </div>

          <div class="d-flex gap-2 align-items-center">

            <select name="filter_type" class="form-select" id="filter_type">
              <option value="today">Today</option>
              <option value="yesterday">Yesterday</option>
              <option value="7days">Last 7 Days</option>
              <option value="30days">Last 30 Days</option>
              <option value="lastmonth">Last Month</option>
              <option value="3months">Last 3 Months</option>
              <option value="custom">Custom Range</option>
            </select>

            <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
            <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">

            <button type="submit" class="btn btn-success">
              <i class="fa fa-filter"></i> APPLY
            </button>

          </div>

        </div>
      </div>
    </form>
    <!-- Successful Fund Requests -->
    <div class="col-xl-3 col-md-4 col-sm-6">
      <div class="card h-100">
        <div class="card-body d-flex justify-content-between">
          <div>
            <h6 class="text-muted">Successful Fund Requests</h6>
            <h4 class="mb-1">₹ <span id="successFundAmount">0.00</span></h4>
            <small><span id="successFundTxn">0</span> Txn</small>
          </div>
          <div class="avatar avatar-md d-flex justify-content-center align-items-center bg-label-success rounded">
            <i class="fa fa-refresh fs-4"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Pending Fund Requests -->
    <div class="col-xl-3 col-md-4 col-sm-6">
      <div class="card h-100">
        <div class="card-body d-flex justify-content-between">
          <div>
            <h6 class="text-muted">Pending Fund Requests</h6>
            <h4 class="mb-1">₹ <span id="pendingFundAmount">0.00</span></h4>
            <small><span id="pendingFundTxn">0</span> Txn</small>
          </div>
          <div class="avatar avatar-md d-flex justify-content-center align-items-center bg-label-warning rounded">
            <i class="fa fa-hourglass fs-4"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Total Credit Amount -->
    <div class="col-xl-3 col-md-4 col-sm-6">
      <div class="card h-100">
        <div class="card-body d-flex justify-content-between">
          <div>
            <h6 class="text-muted">Total Credit Amount</h6>
            <h4 class="mb-1">₹ <span id="totalCreditAmount">0.00</span></h4>
            <small><span id="totalCreditTxn">0</span> Txn</small>
          </div>
          <div class="avatar avatar-md d-flex justify-content-center align-items-center bg-label-info rounded">
            <i class="fa fa-wallet fs-4"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Success Payouts -->
    <div class="col-xl-3 col-md-4 col-sm-6">
      <div class="card h-100">
        <div class="card-body d-flex justify-content-between">
          <div>
            <h6 class="text-muted">Success Payouts</h6>
            <h4 class="mb-1">₹ <span id="successPayoutAmount">0.00</span></h4>
            <small><span id="successPayoutTxn">0</span> Txn</small>
          </div>
          <div class="avatar avatar-md d-flex justify-content-center align-items-center bg-label-success rounded">
            <i class="fa fa-arrow-up fs-4"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Pending Payouts -->
    <div class="col-xl-3 col-md-4 col-sm-6">
      <div class="card h-100">
        <div class="card-body d-flex justify-content-between">
          <div>
            <h6 class="text-muted">Pending Payouts</h6>
            <h4 class="mb-1">₹ <span id="pendingPayoutAmount">0.00</span></h4>
            <small><span id="pendingPayoutTxn">0</span> Txn</small>
          </div>
          <div class="avatar avatar-md d-flex justify-content-center align-items-center bg-label-warning rounded">
            <i class="fa fa-clock fs-4"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Failed Payouts -->
    <div class="col-xl-3 col-md-4 col-sm-6">
      <div class="card h-100">
        <div class="card-body d-flex justify-content-between">
          <div>
            <h6 class="text-muted">Failed Payouts</h6>
            <h4 class="mb-1">₹ <span id="failedPayoutAmount">0.00</span></h4>
            <small><span id="failedPayoutTxn">0</span> Txn</small>
          </div>
          <div class="avatar avatar-md d-flex justify-content-center align-items-center bg-label-danger rounded">
            <i class="fa fa-x fs-4"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Success Payins -->
    <div class="col-xl-3 col-md-4 col-sm-6">
      <div class="card h-100">
        <div class="card-body d-flex justify-content-between">
          <div>
            <h6 class="text-muted">Success Payins</h6>
            <h4 class="mb-1">₹ <span id="successPayinAmount">0.00</span></h4>
            <small><span id="successPayinTxn">0</span> Txn</small>
          </div>
          <div class="avatar avatar-md d-flex justify-content-center align-items-center bg-label-success rounded">
            <i class="fa fa-check fs-4"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Pending Payins -->
    <div class="col-xl-3 col-md-4 col-sm-6">
      <div class="card h-100">
        <div class="card-body d-flex justify-content-between">
          <div>
            <h6 class="text-muted">Pending Payins</h6>
            <h4 class="mb-1">₹ <span id="pendingPayinAmount">0.00</span></h4>
            <small><span id="pendingPayinTxn">0</span> Txn</small>
          </div>
          <div class="avatar avatar-md d-flex justify-content-center align-items-center bg-label-warning rounded">
            <i class="fa fa-clock fs-4"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent User Activity -->
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-body">

          <h5 class="mb-3">
            <i class="fa fa-clock text-primary"></i>
            Recent User Activity
          </h5>

          <div style="max-height:400px; overflow-y:auto;">
            @foreach ($logs as $log)
              <div class="border-bottom py-2">
                User {{ $log->user->company_name ?? 'Unknown' }}
                just logged in at
                {{ \Carbon\Carbon::parse($log->logged_at)->format('d-m-Y H:i:s') }}
              </div>
            @endforeach
          </div>

        </div>
      </div>
    </div>

    <!-- System Logs -->
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-body">

          <h5 class="mb-3 text-danger">
            <i class="fa fa-terminal"></i>
            System Logs
          </h5>

          <div class="p-3 rounded" style="background:#f1f1f1; max-height:400px; overflow-y:auto; font-size:13px;">

            @foreach ($logs as $log)
              <div>
                [{{ \Carbon\Carbon::parse($log->logged_at)->format('Y-m-d H:i:s') }}]
                LOGIN:
                {{ $log->user->company_name ?? 'Unknown' }}
                from {{ $log->ip_address ?? 'N/A' }}
              </div>
            @endforeach

          </div>

        </div>
      </div>
    </div>

    {{-- <!-- Revenue Growth -->
    <div class="col-xxl-4 col-xl-5 col-md-6 col-sm-8 col-12 mb-md-0 order-xxl-0 order-2">
      <div class="card pb-xxl-3">
        <div class="card-body row">
          <div class="d-flex flex-column col-4">
            <div class="card-title mb-auto">
              <h5 class="mb-2 text-nowrap">Revenue Growth</h5>
              <p class="mb-0">Weekly Report</p>
            </div>
            <div class="chart-statistics">
              <h3 class="card-title mb-1">$4,673</h3>
              <span class="badge bg-label-success">+15.2%</span>
            </div>
          </div>
          <div id="revenueGrowth" class="col-8"></div>
        </div>
      </div>
    </div>

    <!-- Earning Reports Tabs-->
    <div class="col-xxl-8 col-12">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between">
          <div class="card-title m-0">
            <h5 class="mb-1">Earning Reports</h5>
            <p class="card-subtitle">Yearly Earnings Overview</p>
          </div>
          <div class="dropdown">
            <button class="btn btn-text-secondary rounded-pill text-body-secondary border-0 p-2 me-n1" type="button"
              id="earningReportsTabsId" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              <i class="icon-base ti tabler-dots-vertical icon-md text-body-secondary"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="earningReportsTabsId">
              <a class="dropdown-item" href="javascript:void(0);">View More</a>
              <a class="dropdown-item" href="javascript:void(0);">Delete</a>
            </div>
          </div>
        </div>
        <div class="card-body">
          <ul class="nav nav-tabs widget-nav-tabs pb-8 gap-4 mx-1 d-flex flex-nowrap" role="tablist">
            <li class="nav-item">
              <a href="javascript:void(0);"
                class="nav-link btn active d-flex flex-column align-items-center justify-content-center" role="tab"
                data-bs-toggle="tab" data-bs-target="#navs-orders-id" aria-controls="navs-orders-id"
                aria-selected="true">
                <div class="badge bg-label-secondary rounded p-2"><i
                    class="icon-base ti tabler-shopping-cart icon-md"></i></div>
                <h6 class="tab-widget-title mb-0 mt-2">Orders</h6>
              </a>
            </li>
            <li class="nav-item">
              <a href="javascript:void(0);"
                class="nav-link btn d-flex flex-column align-items-center justify-content-center" role="tab"
                data-bs-toggle="tab" data-bs-target="#navs-sales-id" aria-controls="navs-sales-id"
                aria-selected="false">
                <div class="badge bg-label-secondary rounded p-2"><i
                    class="icon-base ti tabler-chart-bar-popular icon-md"></i></div>
                <h6 class="tab-widget-title mb-0 mt-2">Sales</h6>
              </a>
            </li>
            <li class="nav-item">
              <a href="javascript:void(0);"
                class="nav-link btn d-flex flex-column align-items-center justify-content-center" role="tab"
                data-bs-toggle="tab" data-bs-target="#navs-profit-id" aria-controls="navs-profit-id"
                aria-selected="false">
                <div class="badge bg-label-secondary rounded p-2"><i
                    class="icon-base ti tabler-currency-dollar icon-md"></i></div>
                <h6 class="tab-widget-title mb-0 mt-2">Profit</h6>
              </a>
            </li>
            <li class="nav-item">
              <a href="javascript:void(0);"
                class="nav-link btn d-flex flex-column align-items-center justify-content-center" role="tab"
                data-bs-toggle="tab" data-bs-target="#navs-income-id" aria-controls="navs-income-id"
                aria-selected="false">
                <div class="badge bg-label-secondary rounded p-2"><i
                    class="icon-base ti tabler-chart-pie-2 icon-md"></i>
                </div>
                <h6 class="tab-widget-title mb-0 mt-2">Income</h6>
              </a>
            </li>
            <li class="nav-item">
              <a href="javascript:void(0);"
                class="nav-link btn d-flex align-items-center justify-content-center disabled" role="tab"
                data-bs-toggle="tab" aria-selected="false">
                <div class="badge bg-label-secondary rounded p-2"><i class="icon-base ti tabler-plus icon-md"></i></div>
              </a>
            </li>
          </ul>
          <div class="tab-content p-0 ms-0 ms-sm-2">
            <div class="tab-pane fade show active" id="navs-orders-id" role="tabpanel">
              <div id="earningReportsTabsOrders"></div>
            </div>
            <div class="tab-pane fade" id="navs-sales-id" role="tabpanel">
              <div id="earningReportsTabsSales"></div>
            </div>
            <div class="tab-pane fade" id="navs-profit-id" role="tabpanel">
              <div id="earningReportsTabsProfit"></div>
            </div>
            <div class="tab-pane fade" id="navs-income-id" role="tabpanel">
              <div id="earningReportsTabsIncome"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Sales last 6 months -->
    <div class="col-xl-4 col-md-6 order-xxl-0 order-1">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between pb-4">
          <div class="card-title mb-0">
            <h5 class="mb-1">Sales</h5>
            <p class="card-subtitle">Last 6 Months</p>
          </div>
          <div class="dropdown">
            <button class="btn btn-text-secondary rounded-pill text-body-secondary border-0 p-2 me-n1" type="button"
              id="salesLastMonthMenu" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              <i class="icon-base ti tabler-dots-vertical icon-md text-body-secondary"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="salesLastMonthMenu">
              <a class="dropdown-item" href="javascript:void(0);">View More</a>
              <a class="dropdown-item" href="javascript:void(0);">Delete</a>
            </div>
          </div>
        </div>
        <div class="card-body">
          <div id="salesLastMonth"></div>
        </div>
      </div>
    </div>

    <!-- Sales By Country -->
    <div class="col-xxl-4 col-md-6">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between">
          <div class="card-title mb-0">
            <h5 class="mb-1">Sales by Countries</h5>
            <p class="card-subtitle">Monthly Sales Overview</p>
          </div>
          <div class="dropdown">
            <button class="btn btn-text-secondary btn-icon rounded-pill text-body-secondary border-0 me-n1"
              type="button" id="salesByCountry" data-bs-toggle="dropdown" aria-haspopup="true"
              aria-expanded="false">
              <i class="icon-base ti tabler-dots-vertical icon-22px text-body-secondary"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="salesByCountry">
              <a class="dropdown-item" href="javascript:void(0);">Download</a>
              <a class="dropdown-item" href="javascript:void(0);">Refresh</a>
              <a class="dropdown-item" href="javascript:void(0);">Share</a>
            </div>
          </div>
        </div>
        <div class="card-body">
          <ul class="p-0 m-0">
            <li class="d-flex align-items-center mb-4">
              <div class="avatar flex-shrink-0 me-4">
                <i class="fis fi fi-us rounded-circle fs-2"></i>
              </div>
              <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                <div class="me-2">
                  <div class="d-flex align-items-center">
                    <h6 class="mb-0 me-1">$8,567k</h6>
                  </div>
                  <small class="text-body">United states</small>
                </div>
                <div class="user-progress">
                  <p class="text-success fw-medium mb-0 d-flex align-items-center gap-1">
                    <i class="icon-base ti tabler-chevron-up"></i>
                    25.8%
                  </p>
                </div>
              </div>
            </li>
            <li class="d-flex align-items-center mb-4">
              <div class="avatar flex-shrink-0 me-4">
                <i class="fis fi fi-br rounded-circle fs-2"></i>
              </div>
              <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                <div class="me-2">
                  <div class="d-flex align-items-center">
                    <h6 class="mb-0 me-1">$2,415k</h6>
                  </div>
                  <small class="text-body">Brazil</small>
                </div>
                <div class="user-progress">
                  <p class="text-danger fw-medium mb-0 d-flex align-items-center gap-1">
                    <i class="icon-base ti tabler-chevron-down"></i>
                    6.2%
                  </p>
                </div>
              </div>
            </li>
            <li class="d-flex align-items-center mb-4">
              <div class="avatar flex-shrink-0 me-4">
                <i class="fis fi fi-in rounded-circle fs-2"></i>
              </div>
              <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                <div class="me-2">
                  <div class="d-flex align-items-center">
                    <h6 class="mb-0 me-1">$865k</h6>
                  </div>
                  <small class="text-body">India</small>
                </div>
                <div class="user-progress">
                  <p class="text-success fw-medium mb-0 d-flex align-items-center gap-1">
                    <i class="icon-base ti tabler-chevron-up"></i>
                    12.4%
                  </p>
                </div>
              </div>
            </li>
            <li class="d-flex align-items-center mb-4">
              <div class="avatar flex-shrink-0 me-4">
                <i class="fis fi fi-au rounded-circle fs-2"></i>
              </div>
              <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                <div class="me-2">
                  <div class="d-flex align-items-center">
                    <h6 class="mb-0 me-1">$745k</h6>
                  </div>
                  <small class="text-body">Australia</small>
                </div>
                <div class="user-progress">
                  <p class="text-danger fw-medium mb-0 d-flex align-items-center gap-1">
                    <i class="icon-base ti tabler-chevron-down"></i>
                    11.9%
                  </p>
                </div>
              </div>
            </li>
            <li class="d-flex align-items-center mb-4">
              <div class="avatar flex-shrink-0 me-4">
                <i class="fis fi fi-fr rounded-circle fs-2"></i>
              </div>
              <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                <div class="me-2">
                  <div class="d-flex align-items-center">
                    <h6 class="mb-0 me-1">$45</h6>
                  </div>
                  <small class="text-body">France</small>
                </div>
                <div class="user-progress">
                  <p class="text-success fw-medium mb-0 d-flex align-items-center gap-1">
                    <i class="icon-base ti tabler-chevron-up"></i>
                    16.2%
                  </p>
                </div>
              </div>
            </li>
            <li class="d-flex align-items-center">
              <div class="avatar flex-shrink-0 me-4">
                <i class="fis fi fi-cn rounded-circle fs-2"></i>
              </div>
              <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                <div class="me-2">
                  <div class="d-flex align-items-center">
                    <h6 class="mb-0 me-1">$12k</h6>
                  </div>
                  <small class="text-body">China</small>
                </div>
                <div class="user-progress">
                  <p class="text-success fw-medium mb-0 d-flex align-items-center gap-1">
                    <i class="icon-base ti tabler-chevron-up"></i>
                    14.8%
                  </p>
                </div>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>
    <!--/ Sales By Country -->

    <!-- Project Status -->
    <div class="col-12 col-md-6 col-xxl-4">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between">
          <h5 class="mb-0 card-title">Project Status</h5>
          <div class="dropdown">
            <button class="btn btn-text-secondary rounded-pill text-body-secondary border-0 p-2 me-n1" type="button"
              id="projectStatusId" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              <i class="icon-base ti tabler-dots-vertical icon-md text-body-secondary"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="projectStatusId">
              <a class="dropdown-item" href="javascript:void(0);">View More</a>
              <a class="dropdown-item" href="javascript:void(0);">Delete</a>
            </div>
          </div>
        </div>
        <div class="card-body">
          <div class="d-flex align-items-start">
            <div class="badge rounded bg-label-warning p-2 me-3 rounded"><i
                class="icon-base ti tabler-currency-dollar icon-lg"></i></div>
            <div class="d-flex justify-content-between w-100 gap-2 align-items-center">
              <div class="me-2">
                <h6 class="mb-0">$4,3742</h6>
                <small class="text-body">Your Earnings</small>
              </div>
              <h6 class="mb-0 text-success">+10.2%</h6>
            </div>
          </div>
          <div id="projectStatusChart"></div>
          <div class="d-flex justify-content-between mb-4">
            <h6 class="mb-0">Donates</h6>
            <div class="d-flex">
              <p class="mb-0 me-4">$756.26</p>
              <p class="mb-0 text-danger">-139.34</p>
            </div>
          </div>
          <div class="d-flex justify-content-between">
            <h6 class="mb-0">Podcasts</h6>
            <div class="d-flex">
              <p class="mb-0 me-4">$2,207.03</p>
              <p class="mb-0 text-success">+576.24</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Active Projects -->
    <div class="col-xxl-4 col-md-6">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between">
          <div class="card-title mb-0">
            <h5 class="mb-1">Active Project</h5>
            <p class="card-subtitle">Average 72% Completed</p>
          </div>
          <div class="dropdown">
            <button class="btn btn-text-secondary rounded-pill text-body-secondary border-0 p-2 me-n1" type="button"
              id="activeProjects" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              <i class="icon-base ti tabler-dots-vertical icon-md text-body-secondary"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="activeProjects">
              <a class="dropdown-item" href="javascript:void(0);">Refresh</a>
              <a class="dropdown-item" href="javascript:void(0);">Download</a>
              <a class="dropdown-item" href="javascript:void(0);">View All</a>
            </div>
          </div>
        </div>
        <div class="card-body">
          <ul class="p-0 m-0">
            <li class="mb-4 d-flex">
              <div class="d-flex w-50 align-items-center me-4">
                <img src="{{ asset('assets/img/icons/brands/laravel-logo.png') }}" alt="laravel-logo" class="me-4"
                  width="35" />
                <div>
                  <h6 class="mb-0">Laravel</h6>
                  <small class="text-body">eCommerce</small>
                </div>
              </div>
              <div class="d-flex flex-grow-1 align-items-center">
                <div class="progress w-100 me-4" style="height:8px;">
                  <div class="progress-bar bg-danger" role="progressbar" style="width: 65%" aria-valuenow="54"
                    aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <span class="text-body-secondary">65%</span>
              </div>
            </li>
            <li class="mb-4 d-flex">
              <div class="d-flex w-50 align-items-center me-4">
                <img src="{{ asset('assets/img/icons/brands/figma-logo.png') }}" alt="figma-logo" class="me-4"
                  width="35" />
                <div>
                  <h6 class="mb-0">Figma</h6>
                  <small class="text-body">App UI Kit</small>
                </div>
              </div>
              <div class="d-flex flex-grow-1 align-items-center">
                <div class="progress w-100 me-4" style="height:8px;">
                  <div class="progress-bar bg-primary" role="progressbar" style="width: 86%" aria-valuenow="86"
                    aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <span class="text-body-secondary">86%</span>
              </div>
            </li>
            <li class="mb-4 d-flex">
              <div class="d-flex w-50 align-items-center me-4">
                <img src="{{ asset('assets/img/icons/brands/vue-logo.png') }}" alt="vue-logo" class="me-4"
                  width="35" />
                <div>
                  <h6 class="mb-0">VueJs</h6>
                  <small class="text-body">Calendar App</small>
                </div>
              </div>
              <div class="d-flex flex-grow-1 align-items-center">
                <div class="progress w-100 me-4" style="height:8px;">
                  <div class="progress-bar bg-success" role="progressbar" style="width: 90%" aria-valuenow="90"
                    aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <span class="text-body-secondary">90%</span>
              </div>
            </li>
            <li class="mb-4 d-flex">
              <div class="d-flex w-50 align-items-center me-4">
                <img src="{{ asset('assets/img/icons/brands/react-logo.png') }}" alt="react-logo" class="me-4"
                  width="35" />
                <div>
                  <h6 class="mb-0">React</h6>
                  <small class="text-body">Dashboard</small>
                </div>
              </div>
              <div class="d-flex flex-grow-1 align-items-center">
                <div class="progress w-100 me-4" style="height:8px;">
                  <div class="progress-bar bg-info" role="progressbar" style="width: 37%" aria-valuenow="37"
                    aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <span class="text-body-secondary">37%</span>
              </div>
            </li>
            <li class="mb-4 d-flex">
              <div class="d-flex w-50 align-items-center me-4">
                <img src="{{ asset('assets/img/icons/brands/bootstrap-logo.png') }}" alt="bootstrap-logo"
                  class="me-4" width="35" />
                <div>
                  <h6 class="mb-0">Bootstrap</h6>
                  <small class="text-body">Website</small>
                </div>
              </div>
              <div class="d-flex flex-grow-1 align-items-center">
                <div class="progress w-100 me-4" style="height:8px;">
                  <div class="progress-bar bg-primary" role="progressbar" style="width: 22%" aria-valuenow="22"
                    aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <span class="text-body-secondary">22%</span>
              </div>
            </li>
            <li class="d-flex">
              <div class="d-flex w-50 align-items-center me-4">
                <img src="{{ asset('assets/img/icons/brands/sketch-logo.png') }}" alt="sketch-logo" class="me-4"
                  width="35" />
                <div>
                  <h6 class="mb-0">Sketch</h6>
                  <small class="text-body">Website Design</small>
                </div>
              </div>
              <div class="d-flex flex-grow-1 align-items-center">
                <div class="progress w-100 me-4" style="height:8px;">
                  <div class="progress-bar bg-warning" role="progressbar" style="width: 29%" aria-valuenow="29"
                    aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <span class="text-body-secondary">29%</span>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>
    <!--/ Active Projects -->

    <!-- Last Transaction -->
    <div class="col-md-6 col-12">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="card-title m-0 me-2">Last Transaction</h5>
          <div class="dropdown">
            <button class="btn btn-text-secondary rounded-pill text-body-secondary border-0 p-2 me-n1" type="button"
              id="teamMemberList" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              <i class="icon-base ti tabler-dots-vertical icon-md text-body-secondary"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="teamMemberList">
              <a class="dropdown-item" href="javascript:void(0);">Download</a>
              <a class="dropdown-item" href="javascript:void(0);">Refresh</a>
              <a class="dropdown-item" href="javascript:void(0);">Share</a>
            </div>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-borderless border-top">
            <thead class="border-bottom">
              <tr>
                <th>CARD</th>
                <th>DATE</th>
                <th>STATUS</th>
                <th>TREND</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td class="pt-5">
                  <div class="d-flex justify-content-start align-items-center">
                    <div class="me-4">
                      <img src="{{ asset('assets/img/icons/payments/visa-img.png') }}" alt="Visa"
                        height="30" />
                    </div>
                    <div class="d-flex flex-column">
                      <p class="mb-0 text-heading">*4230</p>
                      <small class="text-body">Credit</small>
                    </div>
                  </div>
                </td>
                <td class="pt-5">
                  <div class="d-flex flex-column">
                    <p class="mb-0 text-heading">Sent</p>
                    <small class="text-body text-nowrap">17 Mar 2022</small>
                  </div>
                </td>
                <td class="pt-5"><span class="badge bg-label-success">Verified</span></td>
                <td class="pt-5">
                  <p class="mb-0 text-heading">+$1,678</p>
                </td>
              </tr>
              <tr>
                <td>
                  <div class="d-flex justify-content-start align-items-center">
                    <div class="me-4">
                      <img src="{{ asset('assets/img/icons/payments/master-card-img.png') }}" alt="Visa"
                        height="30" />
                    </div>
                    <div class="d-flex flex-column">
                      <p class="mb-0 text-heading">*5578</p>
                      <small class="text-body">Credit</small>
                    </div>
                  </div>
                </td>
                <td>
                  <div class="d-flex flex-column">
                    <p class="mb-0 text-heading">Sent</p>
                    <small class="text-body text-nowrap">12 Feb 2022</small>
                  </div>
                </td>
                <td><span class="badge bg-label-danger">Rejected</span></td>
                <td>
                  <p class="mb-0 text-heading">-$839</p>
                </td>
              </tr>
              <tr>
                <td>
                  <div class="d-flex justify-content-start align-items-center">
                    <div class="me-4">
                      <img src="{{ asset('assets/img/icons/payments/american-express-img.png') }}" alt="Visa"
                        height="30" />
                    </div>
                    <div class="d-flex flex-column">
                      <p class="mb-0 text-heading">*4567</p>
                      <small class="text-body">ATM</small>
                    </div>
                  </div>
                </td>
                <td>
                  <div class="d-flex flex-column">
                    <p class="mb-0 text-heading">Sent</p>
                    <small class="text-body text-nowrap">28 Feb 2022</small>
                  </div>
                </td>
                <td><span class="badge bg-label-success">Verified</span></td>
                <td>
                  <p class="mb-0 text-heading">+$435</p>
                </td>
              </tr>
              <tr>
                <td>
                  <div class="d-flex justify-content-start align-items-center">
                    <div class="me-4">
                      <img src="{{ asset('assets/img/icons/payments/visa-img.png') }}" alt="Visa"
                        height="30" />
                    </div>
                    <div class="d-flex flex-column">
                      <p class="mb-0 text-heading">*5699</p>
                      <small class="text-body">Credit</small>
                    </div>
                  </div>
                </td>
                <td>
                  <div class="d-flex flex-column">
                    <p class="mb-0 text-heading">Sent</p>
                    <small class="text-body text-nowrap">8 Jan 2022</small>
                  </div>
                </td>
                <td><span class="badge bg-label-secondary">Pending</span></td>
                <td>
                  <p class="mb-0 text-heading">+$2,345</p>
                </td>
              </tr>
              <tr>
                <td>
                  <div class="d-flex justify-content-start align-items-center">
                    <div class="me-4">
                      <img src="{{ asset('assets/img/icons/payments/visa-img.png') }}" alt="Visa"
                        height="30" />
                    </div>
                    <div class="d-flex flex-column">
                      <p class="mb-0 text-heading">*5699</p>
                      <small class="text-body">Credit</small>
                    </div>
                  </div>
                </td>
                <td>
                  <div class="d-flex flex-column">
                    <p class="mb-0 text-heading">Sent</p>
                    <small class="text-body text-nowrap">8 Jan 2022</small>
                  </div>
                </td>
                <td><span class="badge bg-label-danger">Rejected</span></td>
                <td>
                  <p class="mb-0 text-heading">-$234</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <!--/ Last Transaction -->

    <!-- Activity Timeline -->
    <div class="col-xxl-6">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between">
          <h5 class="card-title m-0 me-2 pt-1 mb-2 d-flex align-items-center"><i
              class="icon-base ti tabler-list-details me-3"></i> Activity Timeline</h5>
          <div class="dropdown">
            <button class="btn btn-text-secondary rounded-pill text-body-secondary border-0 p-2 me-n1" type="button"
              id="timelineWapper" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              <i class="icon-base ti tabler-dots-vertical icon-md text-body-secondary"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="timelineWapper">
              <a class="dropdown-item" href="javascript:void(0);">Download</a>
              <a class="dropdown-item" href="javascript:void(0);">Refresh</a>
              <a class="dropdown-item" href="javascript:void(0);">Share</a>
            </div>
          </div>
        </div>
        <div class="card-body pb-xxl-0">
          <ul class="timeline mb-0">
            <li class="timeline-item timeline-item-transparent">
              <span class="timeline-point timeline-point-primary"></span>
              <div class="timeline-event">
                <div class="timeline-header mb-3">
                  <h6 class="mb-0">12 Invoices have been paid</h6>
                  <small class="text-body-secondary">12 min ago</small>
                </div>
                <p class="mb-2">Invoices have been paid to the company</p>
                <div class="d-flex align-items-center mb-1">
                  <div class="badge bg-lighter rounded-3">
                    <img src="{{ asset('assets/img/icons/misc/pdf.png') }}" alt="img" width="15"
                      class="me-2" />
                    <span class="h6 mb-0 text-body">invoices.pdf</span>
                  </div>
                </div>
              </div>
            </li>
            <li class="timeline-item timeline-item-transparent">
              <span class="timeline-point timeline-point-success"></span>
              <div class="timeline-event">
                <div class="timeline-header mb-3">
                  <h6 class="mb-0">Client Meeting</h6>
                  <small class="text-body-secondary">45 min ago</small>
                </div>
                <p class="mb-2">Project meeting with john @10:15am</p>
                <div class="d-flex justify-content-between flex-wrap gap-2">
                  <div class="d-flex flex-wrap align-items-center">
                    <div class="avatar avatar-sm me-2">
                      <img src="{{ asset('assets/img/avatars/1.png') }}" alt="Avatar" class="rounded-circle" />
                    </div>
                    <div>
                      <p class="mb-0 small fw-medium">Lester McCarthy (Client)</p>
                      <small>CEO of {{ config('variables.creatorName') }}</small>
                    </div>
                  </div>
                </div>
              </div>
            </li>
            <li class="timeline-item timeline-item-transparent">
              <span class="timeline-point timeline-point-info"></span>
              <div class="timeline-event">
                <div class="timeline-header mb-3">
                  <h6 class="mb-0">Create a new project for client</h6>
                  <small class="text-body-secondary">2 Day Ago</small>
                </div>
                <p class="mb-2">6 team members in a project</p>
                <ul class="list-group list-group-flush">
                  <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap p-0">
                    <div class="d-flex flex-wrap align-items-center">
                      <ul class="list-unstyled users-list d-flex align-items-center avatar-group m-0 me-2">
                        <li data-bs-toggle="tooltip" data-popup="tooltip-custom" data-bs-placement="top"
                          title="Vinnie Mostowy" class="avatar pull-up">
                          <img class="rounded-circle" src="{{ asset('assets/img/avatars/5.png') }}" alt="Avatar" />
                        </li>
                        <li data-bs-toggle="tooltip" data-popup="tooltip-custom" data-bs-placement="top"
                          title="Allen Rieske" class="avatar pull-up">
                          <img class="rounded-circle" src="{{ asset('assets/img/avatars/12.png') }}" alt="Avatar" />
                        </li>
                        <li data-bs-toggle="tooltip" data-popup="tooltip-custom" data-bs-placement="top"
                          title="Julee Rossignol" class="avatar pull-up">
                          <img class="rounded-circle" src="{{ asset('assets/img/avatars/6.png') }}" alt="Avatar" />
                        </li>
                        <li class="avatar">
                          <span class="avatar-initial rounded-circle pull-up text-heading" data-bs-toggle="tooltip"
                            data-bs-placement="bottom" title="3 more">+3</span>
                        </li>
                      </ul>
                    </div>
                  </li>
                </ul>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>
    <!--/ Activity Timeline -->
  </div> --}}
  @endsection
