@extends('layouts/layoutMaster')
@section('title', 'Register Complaint')
@section('page-style')
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
@endsection
@section('content')
  <div class="card">
    <div class="card-header px-1 pt-1 d-flex justify-content-between align-items-center">
      <h5 class="mb-0 pt-2 ps-4">Raise Complaint</h5>
      <img src="{{ asset('assets/img/logo/bbpsPrimaryLogo.jpg') }}" alt="Bharat-Connect" style="height: 60px;">
    </div>
    <div class="card-body">
      <form action="{{ route('complaint.register') }}" method="POST">
        @csrf
        {{-- Complaint Type --}}
        <div class="mb-3">
          <label class="form-label">Complaint Type *</label>
          <select name="complaintType" id="complaintType" class="form-control" required>
            <option value="">Select Type</option>
            <option value="Transaction" {{ old('complaintType') == 'Transaction' ? 'selected' : '' }}>
              Transaction
            </option>
            <option value="Service" {{ old('complaintType') == 'Service' ? 'selected' : '' }}>
              Service
            </option>
          </select>
          @error('complaintType')
            <small class="text-danger">{{ $message }}</small>
          @enderror
        </div>

        <div class="mb-3">
          <label class="form-label">B-connect Transaction ID *</label>
          <select name="txnRefId" class="form-control" required>
            <option value="">Select B-Connect TXN ID</option>
            @foreach ($transactions as $txn)
              <option value="{{ $txn->bbps_txn_ref_id }}">
                {{ $txn->bbps_txn_ref_id }} | ₹{{ $txn->amount }} | {{ $txn->biller_name }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Mobile Number *</label>
          <input type="text" name="mobileNumber" class="form-control" placeholder="Enter Mobile Number"
            value="{{ old('mobile_number') }}" required />
          @error('mobile_number')
            <small class="text-danger">{{ $message }}</small>
          @enderror
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">From Date</label>
            <input type="date" name="from_date" id="from_date" class="form-control">
          </div>

          <div class="col-md-6">
            <label class="form-label">To Date</label>
            <input type="date" name="to_date" id="to_date" class="form-control">
          </div>
        </div>
        {{-- Complaint Description --}}
        <div class="mb-3">
          <label class="form-label">Complaint Description *</label>
          <textarea name="complaintDesc" maxlength="255" class="form-control" required>{{ old('complaintDesc') }}</textarea>
          @error('complaintDesc')
            <small class="text-danger">{{ $message }}</small>
          @enderror
        </div>

        {{-- Service Reason (Only for Service) --}}
        <div class="mb-3 d-none" id="servReasonDiv">
          <label class="form-label">Service Reason *</label>
          <textarea name="servReason" maxlength="255" class="form-control">{{ old('servReason') }}</textarea>
          @error('servReason')
            <small class="text-danger">{{ $message }}</small>
          @enderror
        </div>

        {{-- Complaint Disposition (Only for Transaction) --}}
        <div class="mb-3">
          <label class="form-label">Complaint Disposition *</label>
          <select name="complaintDisposition" id="complaintDisposition" class="form-control">
            <option value="">Select Disposition</option>
            @foreach (config('constant.COMPLAINT_DISPOSITION') as $key => $option)
              <option value="{{ $option }}">
                {{ $option }}
              </option>
            @endforeach
            {{-- <option value="Others">Others, provide details</option> --}}
          </select>
          @error('complaintDisposition')
            <small class="text-danger">{{ $message }}</small>
          @enderror
        </div>

        <!-- Custom Field -->
        <div class="mb-3 d-none" id="otherDispositionBox">
          <label class="form-label">Other Disposition *</label>
          <textarea name="otherDisposition" class="form-control" id="otherDisposition" maxlength="255" required></textarea>
        </div>

        <button type="submit" class="btn btn-success">
          Submit Complaint
        </button>
      </form>
    </div>
  </div>
@endsection
@section('page-script')
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
  <script>
    @if (session('warning'))
      toastr.warning("{{ session('warning') }}");
    @endif

    @if (session('info'))
      toastr.info("{{ session('info') }}");
    @endif
  </script>
  <script>
    toastr.options = {
      "closeButton": true,
      "progressBar": true,
      "positionClass": "toast-top-right",
      "timeOut": "5000"
    }
  </script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const complaintType = document.getElementById('complaintType');

      function toggleFields() {
        let type = complaintType.value;

        // Reset
        document.getElementById('servReasonDiv').classList.add('d-none');

        if (type === 'Service') {
          document.getElementById('servReasonDiv').classList.remove('d-none');
        }
      }

      complaintType.addEventListener('change', toggleFields);

      toggleFields(); // Run on load (for old values)
    });
    $(document).on('change', '#complaintDisposition', function() {
      let value = $(this).val();
      if (value === 'Others') {
        $('#otherDispositionBox').removeClass('d-none');
        $('#otherDisposition').attr('required', true);
      } else {
        $('#otherDispositionBox').addClass('d-none');
        $('#otherDisposition').removeAttr('required');
        $('#otherDisposition').val('');
      }
    });
    $(document).on('change', '#complaintType', function() {
      if ($(this).val() === 'Transaction') {
        $('#complaintDisposition').prop('disabled', false);
      } else {
        $('#complaintDisposition').prop('disabled', true);
      }
    });
  </script>
@endsection
