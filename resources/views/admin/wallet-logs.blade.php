@extends('layouts/layoutMaster')

@section('title', 'Wallet Logs')
@section('vendor-style')
  <style>
    #wallet-table {
      table-layout: fixed;
      width: 100% !important;
    }

    #wallet-table_wrapper .dt-info,
    #wallet-table_wrapper .dt-paging {
      display: inline-block !important;
      padding: 10px !important;
    }

    #wallet-table th:first-child,
    #wallet-table td:first-child {
      white-space: nowrap;
      width: 80px !important;
    }

    #wallet-table_wrapper .dt-info {
      margin-top: 10px !important;
    }

    #wallet-table_wrapper .dt-paging {
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
  <script>
    window.routes = {
      walletData: "{{ route('admin.wallet-history.datatable') }}",
    };
    window.csrfToken = "{{ csrf_token() }}";
  </script>
  @vite('resources/assets/js/wallet-logs.js')
@endsection

@section('page-style')
  @vite(['resources/assets/vendor/scss/pages/page-profile.scss'])
@endsection

@section('content')
  <div class="row g-4 mb-4">
    <div class="col-12">
      <div class="card soft-card">
        <div
          class="card-header d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
          <div>
            <h5 class="mb-0">Virtual Wallet History</h5>
          </div>
        </div>

        <div class="card-body">
          {{-- Filters --}}
          <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3 mb-3">
            <!-- User -->
            <div class="col">
              <label class="form-label small text-muted">User</label>
              <input type="text" id="filter-user" class="form-control" placeholder="Name / Email / Phone">
            </div>

            <div class="col">
              <label class="form-label small text-muted">Ref ID</label>
              <input class="form-control" id="filter-ref" placeholder="Ref ID">
            </div>

            <div class="col">
              <label class="form-label small text-muted">Wallet Type</label>
              <select class="form-select" id="filter-wallet">
                <option value="">All Wallets</option>
                <option value="Reserve">Reserve</option>
                <option value="Payout">Payout</option>
                <option value="Payin">Payin</option>
                <option value="Virtual">Virtual</option>
              </select>
            </div>

            <div class="col">
              <label class="form-label small text-muted">From Date</label>
              <input type="date" class="form-control" id="filter-from-date">
            </div>

            <div class="col">
              <label class="form-label small text-muted">To Date</label>
              <input type="date" class="form-control" id="filter-to-date">
            </div>

            <div class="col align-self-end">
              <button class="btn btn-primary me-2" id="applyFilter">
                <i class="icon-base ti tabler-filter me-1"></i> Apply
              </button>
              <button class="btn btn-outline-secondary" id="resetFilter">
                <i class="icon-base ti tabler-refresh me-1"></i> Reset
              </button>
            </div>
          </div>

          {{-- Table --}}
          <div class="d-flex justify-content-end align-items-center p-3 border-bottom bg-white">
            <div id="export-buttons"></div>
          </div>

          <div class="table-responsive">
            <table class="table table-hover align-middle" id="wallet-table">
              <thead class="bg-light">
                <tr>
                  <th>S.No</th>
                  <th>User Info</th>
                  <th>Ref ID</th>
                  <th>Wallet</th>
                  <th>Amount</th>
                  <th>Credit</th>
                  <th>Debit</th>
                  <th>Closing</th>
                  <th>Date</th>
                  <th>Description</th>
                </tr>
              </thead>

              <tbody>
                {{-- @foreach ($history as $row)
                  @php
                    $created = $row->created_at;
                    $wallet = ucfirst($row->service_name ?? '');
                    $amount = (float) ($row->amount ?? 0);
                    $credit = (float) ($row->credit ?? 0);
                    $debit = (float) ($row->debit ?? 0);
                    $closing = (float) ($row->closing_balance ?? 0);
                    $desc = $row->description ?? '-';
                  @endphp

                  <tr data-refid="{{ $row->refid ?? '' }}" data-service="{{ $wallet }}"
                    data-date="{{ $created }}" data-amount="{{ $amount }}" data-credit="{{ $credit }}"
                    data-debit="{{ $debit }}">
                    <td></td>
                    <td class="fw-semibold">{{ $row->refid ?? '-' }}</td>
                    <td>{{ $wallet ?: '-' }}</td>
                    <td class="text-end fw-semibold">₹ {{ number_format($amount, 2) }}</td>
                    <td class="text-end text-success fw-semibold">₹ {{ number_format($credit, 2) }}</td>
                    <td class="text-end text-danger fw-semibold">₹ {{ number_format($debit, 2) }}</td>
                    <td class="text-end fw-semibold">₹ {{ number_format($closing, 2) }}</td>
                    <td>{{ $row->created_at }}</td>
                    <td class="text-truncate" style="max-width: 240px;" title="{{ $desc }}">{{ $desc }}
                    </td>
                  </tr>
                @endforeach --}}
              </tbody>

            </table>
          </div>

        </div>
      </div>
    </div>
  </div>
@endsection
