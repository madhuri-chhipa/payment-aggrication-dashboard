@extends('layouts/layoutMaster')

@section('title', 'Payout Receipt')

@section('content')
<div class="container my-4">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">

            {{-- Header --}}
            <div class="text-center border-bottom pb-3 mb-4">
                <h4 class="fw-bold text-primary mb-1">Payout Receipt</h4>
                <small class="text-muted">Transaction ID: {{ $transaction->txn_id }}</small>
            </div>

            {{-- Status Banner --}}
            <div class="text-center mb-4">
                @php
                    $statusColor = match($transaction->status) {
                        'S' => 'success',
                        'P' => 'warning',
                        'F', 'R' => 'danger',
                        default => 'secondary'
                    };
                    $statusText = match($transaction->status) {
                        'S' => 'Success',
                        'P' => 'Pending',
                        'F' => 'Failed',
                        'R' => 'Rejected',
                        default => 'Processing'
                    };
                @endphp

                <span class="badge bg-{{ $statusColor }} fs-6 px-3 py-2">
                    {{ $statusText }}
                </span>
            </div>

            {{-- Beneficiary Details --}}
            <div class="row mb-4">
                <div class="col-md-6">
                    <h6 class="text-muted mb-2">Beneficiary</h6>
                    <p class="mb-1"><strong>Name:</strong> {{ $transaction->bene_name }}</p>
                    <p class="mb-1"><strong>Account Number:</strong> {{ $transaction->bene_account }}</p>
                    <p class="mb-0"><strong>IFSC Code:</strong> {{ $transaction->bene_ifsc }}</p>
                </div>

                <div class="col-md-6">
                    <h6 class="text-muted mb-2">Transaction Info</h6>
                    <p class="mb-1"><strong>Transfer Mode:</strong> {{ ucfirst($transaction->transfer_mode) }}</p>
                    <p class="mb-1"><strong>UTR Number:</strong> {{ $transaction->utr ?? 'Will be updated after processing' }}</p>
                    <p class="mb-0"><strong>Date & Time:</strong> {{ \Carbon\Carbon::parse($transaction->created_at)->format('d M Y, h:i A') }}</p>
                </div>
            </div>

            {{-- Amount Summary --}}
            <h6 class="text-muted mb-2">Amount Details</h6>
            <div class="table-responsive mb-4">
                <table class="table table-striped">
                    <tbody>
                        <tr>
                            <th>Transfer Amount</th>
                            <td class="text-end">₹ {{ number_format($transaction->amount, 2) }}</td>
                        </tr>
                        <tr>
                            <th>Service Charges</th>
                            <td class="text-end">₹ {{ number_format($transaction->charge_amount, 2) }}</td>
                        </tr>
                        <tr>
                            <th>GST</th>
                            <td class="text-end">₹ {{ number_format($transaction->gst_amount, 2) }}</td>
                        </tr>
                        <tr>
                            <th>Platform Fee</th>
                            <td class="text-end">₹ {{ number_format($transaction->platform_fee, 2) }}</td>
                        </tr>
                        <tr class="fw-bold border-top">
                            <th>Total Charges</th>
                            <td class="text-end">₹ {{ number_format($transaction->total_charge, 2) }}</td>
                        </tr>
                        <tr class="fw-bold fs-5">
                            <th>Total Debited</th>
                            <td class="text-end text-primary">₹ {{ number_format($transaction->total_amount, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Note --}}
            <div class="alert alert-light border small text-muted">
                This is a system-generated receipt for your payout transaction.  
                If you have any questions, please contact support with your Transaction ID.
            </div>

            {{-- Footer --}}
            <div class="text-center mt-4">
                <small class="text-muted">Thank you for using our services.</small>
            </div>

        </div>
    </div>

    {{-- Print Button --}}
    <div class="text-end mt-3">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fa fa-print me-1"></i> Download / Print Receipt
        </button>
    </div>
</div>
@endsection
