@extends('layouts/layoutMaster')

@section('title', 'Bank Statement')

@section('vendor-style')
  <style>
    .statement-table th,
    .statement-table td {
      vertical-align: middle;
      font-size: 13px;
    }

    .statement-table .text-nowrap {
      white-space: nowrap;
    }

    .statement-table .particular-col {
      min-width: 260px;
      white-space: normal;
      word-break: break-word;
    }

    .statement-table .date-col {
      min-width: 110px;
    }

    .statement-table .postdate-col {
      min-width: 170px;
    }

    .statement-table .amount-col,
    .statement-table .balance-col {
      min-width: 110px;
      text-align: right;
    }

    .pagination {
      margin-bottom: 0;
      flex-wrap: wrap;
    }

    .pagination svg {
      width: 14px !important;
      height: 14px !important;
    }

    .page-link {
      padding: 0.4rem 0.75rem;
      font-size: 14px;
      line-height: 1.2;
    }

    .table-responsive {
      overflow-x: auto;
    }
  </style>
@endsection

@section('content')
  <div class="row">
    <div class="col-12">
      <div class="card border shadow-xs mb-4">
        <div class="card-header border-bottom-0 pb-0">
          <div class="d-sm-flex align-items-center justify-content-between">
            <div>
              <h5 class="font-weight-semibold text-lg mb-0">Bank Statement</h5>
            </div>

            <div class="d-flex gap-2 mt-3 mt-sm-0">
              <a href="{{ route('admin.report.bank_statement_excel', request()->query()) }}" class="btn btn-success">
                Excel Export
              </a>
              <a href="{{ route('admin.report.bank_statement_pdf', request()->query()) }}" class="btn btn-danger"
                target="_blank">
                PDF Export
              </a>
            </div>
          </div>
        </div>

        <div class="card-body">
          <form method="GET" action="{{ route('admin.report.bank_statement') }}" class="row g-3 mb-4">
            <div class="col-md-3">
              <label for="startDate" class="form-label">Start Date</label>
              <input type="date" name="startDate" id="startDate"
                class="form-control @error('startDate') is-invalid @enderror"
                value="{{ old('startDate', \Carbon\Carbon::createFromFormat('d-m-Y', $startDate)->format('Y-m-d')) }}">
              @error('startDate')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-3">
              <label for="endDate" class="form-label">End Date</label>
              <input type="date" name="endDate" id="endDate"
                class="form-control @error('endDate') is-invalid @enderror"
                value="{{ old('endDate', \Carbon\Carbon::createFromFormat('d-m-Y', $endDate)->format('Y-m-d')) }}">
              @error('endDate')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-2 d-flex align-items-end">
              <button type="submit" class="btn btn-primary w-100">Search</button>
            </div>

            <div class="col-md-2 d-flex align-items-end">
              <a href="{{ route('admin.report.bank_statement') }}" class="btn btn-secondary w-100">Today</a>
            </div>
          </form>

          @if ($transactions->count() > 0)
            <div class="table-responsive">
              <table class="table table-bordered table-striped align-middle statement-table">
                <thead>
                  <tr>
                    <th class="text-nowrap">#</th>
                    <th class="text-nowrap">Tran ID</th>
                    <th class="text-nowrap date-col">Value Date</th>
                    <th class="text-nowrap date-col">Tran Date</th>
                    <th class="text-nowrap postdate-col">Post Date</th>
                    <th class="particular-col">Particulars</th>
                    <th class="text-nowrap amount-col">Amount</th>
                    <th class="text-nowrap">Dr/Cr</th>
                    <th class="text-nowrap">Type</th>
                    <th class="text-nowrap">Sub Type</th>
                    <th class="text-nowrap balance-col">Balance After Tran</th>
                    <th class="text-nowrap">Instrument No</th>
                    <th class="text-nowrap">Serial No</th>
                    <th class="text-nowrap">Last Tran</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach ($transactions as $index => $txn)
                    <tr>
                      <td class="text-nowrap">{{ $transactions->firstItem() + $index }}</td>
                      <td class="text-nowrap">{{ $txn['tranId'] ?? '' }}</td>

                      <td class="text-nowrap">
                        @if (!empty($txn['tranValueDate']))
                          {{ \Carbon\Carbon::createFromFormat('Ymd', $txn['tranValueDate'])->format('d-m-Y') }}
                        @endif
                      </td>

                      <td class="text-nowrap">
                        @if (!empty($txn['tranDate']))
                          {{ \Carbon\Carbon::createFromFormat('Ymd', $txn['tranDate'])->format('d-m-Y') }}
                        @endif
                      </td>

                      <td class="text-nowrap">
                        @if (!empty($txn['tranPostDate']))
                          {{ \Carbon\Carbon::createFromFormat('YmdHis', $txn['tranPostDate'])->format('d-m-Y h:i:s A') }}
                        @endif
                      </td>

                      <td class="particular-col">{{ $txn['tranParticulars'] ?? '' }}</td>
                      <td class="text-nowrap text-end">{{ $txn['tranAmount'] ?? '' }}</td>
                      <td class="text-nowrap">{{ $txn['drCRIndicator'] ?? '' }}</td>
                      <td class="text-nowrap">{{ $txn['tranType'] ?? '' }}</td>
                      <td class="text-nowrap">{{ $txn['tranSubType'] ?? '' }}</td>
                      <td class="text-nowrap text-end">{{ $txn['balAfterTran'] ?? '' }}</td>
                      <td class="text-nowrap">{{ $txn['instrumentNumber'] ?? '' }}</td>
                      <td class="text-nowrap">{{ $txn['serialNo'] ?? '' }}</td>
                      <td class="text-nowrap">{{ $txn['isLastTran'] ?? '' }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>

            <div class="mt-3 d-flex justify-content-end">
              {{ $transactions->appends(request()->query())->links('pagination::bootstrap-5') }}
            </div>
          @else
            <div class="alert alert-warning mb-0">
              No transactions found for selected date range.
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection
