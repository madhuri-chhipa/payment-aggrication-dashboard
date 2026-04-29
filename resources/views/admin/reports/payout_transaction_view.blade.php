@extends('layouts/layoutMaster')

@section('title', 'Payout Receipt')

@section('content')
<div class="container my-4">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">

            {{-- Header --}}
            <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
                <div>
                    <h4 class="mb-0 fw-bold text-primary">Payout Transaction Receipt</h4>
                    <small class="text-muted">Txn ID: {{ $transaction->txn_id }}</small>
                </div>
                <div class="text-end">
                    <h5 class="mb-0 fw-bold">₹ {{ number_format($transaction->total_amount, 2) }}</h5>
                    <small class="text-muted">Total Amount</small>
                </div>
            </div>

            {{-- User & Beneficiary Info --}}
            <div class="row mb-4">
                <div class="col-md-6">
                    <h6 class="text-muted mb-2">User Details</h6>
                    <p class="mb-1"><strong>User ID:</strong> {{ $transaction->user_id }}</p>
                    <p class="mb-1"><strong>Processed By:</strong> {{ $transaction->processed_by ?? '-' }}</p>
                    <p class="mb-0"><strong>IP Address:</strong> {{ $transaction->ip }}</p>
                </div>
                <div class="col-md-6">
                    <h6 class="text-muted mb-2">Beneficiary Details</h6>
                    <p class="mb-1"><strong>Name:</strong> {{ $transaction->bene_name }}</p>
                    <p class="mb-1"><strong>Account:</strong> {{ $transaction->bene_account }}</p>
                    <p class="mb-0"><strong>IFSC:</strong> {{ $transaction->bene_ifsc }}</p>
                </div>
            </div>

            {{-- Transaction Info --}}
            <h6 class="text-muted mb-2">Transaction Details</h6>
            <div class="table-responsive mb-4">
                <table class="table table-bordered align-middle">
                    <tbody>
                        <tr>
                            <th width="30%">Transfer Mode</th>
                            <td>{{ ucfirst($transaction->transfer_mode) }}</td>
                            <th>Status</th>
                            <td>
                                <span class="badge bg-{{ $transaction->status == 'S' ? 'success' : ($transaction->status == 'P' ? 'warning' : 'danger') }}">
                                    {{ $transaction->status }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>API Used</th>
                            <td>{{ $transaction->api ?? '-' }}</td>
                            <th>API Txn ID</th>
                            <td>{{ $transaction->api_txn_id ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>UTR</th>
                            <td>{{ $transaction->utr ?? '-' }}</td>
                            <th>Processed At</th>
                            <td>{{ $transaction->processed_at ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Created At</th>
                            <td colspan="3">{{ $transaction->created_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Amount Breakdown --}}
            <h6 class="text-muted mb-2">Amount Breakdown</h6>
            <div class="table-responsive mb-4">
                <table class="table table-striped">
                    <tbody>
                        <tr>
                            <th>Transfer Amount</th>
                            <td class="text-end">₹ {{ number_format($transaction->amount, 2) }}</td>
                        </tr>
                        <tr>
                            <th>Charge Amount</th>
                            <td class="text-end">₹ {{ number_format($transaction->charge_amount, 2) }}</td>
                        </tr>
                        <tr>
                            <th>GST Amount</th>
                            <td class="text-end">₹ {{ number_format($transaction->gst_amount, 2) }}</td>
                        </tr>
                        <tr>
                            <th>Platform Fee</th>
                            <td class="text-end">₹ {{ number_format($transaction->platform_fee, 2) }}</td>
                        </tr>
                        <tr class="fw-bold border-top">
                            <th>Total Charge</th>
                            <td class="text-end">₹ {{ number_format($transaction->total_charge, 2) }}</td>
                        </tr>
                        <tr class="fw-bold fs-5">
                            <th>Total Debit Amount</th>
                            <td class="text-end text-primary">₹ {{ number_format($transaction->total_amount, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Response Message --}}
            <h6 class="text-muted mb-2">API Response</h6>
            <div class="bg-light p-3 rounded small text-muted">
                {{ $transaction->response_message ?? 'No response message available.' }}
            </div>

            {{-- Footer --}}
            <div class="text-center mt-4">
                <small class="text-muted">
                    This is a system generated receipt and does not require signature.
                </small>
            </div>

        </div>
    </div>

    {{-- Print Button --}}
    <div class="text-end mt-3">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fa fa-print me-1"></i> Print Receipt
        </button>
    </div>
</div>
@endsection
