@extends('layouts/layoutMaster')

@section('title', 'Payout')

@section('content')
  <div class="card">
    <div class="card-header">
      <h5 class="mb-1">Payout</h5>
      <p class="text-muted mb-0">
        Transfer funds securely to beneficiaries and track payout status in real time.
      </p>
    </div>
    <div class="card-body p-4 p-md-5">
      <div id="ajaxMessage"></div>

      <form id="payoutForm">
        @csrf

        <input type="hidden" name="reqLat" id="reqLat">
        <input type="hidden" name="reqLong" id="reqLong">

        <div class="row g-3">

          <!-- Beneficiary Name -->
          <div class="col-12">
            <label class="form-label fw-semibold">Beneficiary Name</label>
            <input type="text" class="form-control" name="bene_name" placeholder="Enter beneficiary name" required>
          </div>

          <!-- Account Number -->
          <div class="col-md-6">
            <label class="form-label fw-semibold">Account Number</label>
            <input type="text" class="form-control" name="bene_account_number" placeholder="Enter account number"
              required>
          </div>

          <!-- IFSC Code -->
          <div class="col-md-6">
            <label class="form-label fw-semibold">IFSC Code</label>
            <input type="text" class="form-control" name="ifsc_code" placeholder="Enter IFSC code" required>
          </div>

          <!-- Bank Name -->
          <div class="col-md-6">
            <label class="form-label fw-semibold">Bank Name</label>
            <input type="text" class="form-control" name="bank_name" placeholder="Enter bank name">
          </div>

          <!-- Bank Branch -->
          <div class="col-md-6">
            <label class="form-label fw-semibold">Bank Branch</label>
            <input type="text" class="form-control" name="bank_branch" placeholder="Enter branch name">
          </div>

          <!-- Mobile -->
          <div class="col-md-6">
            <label class="form-label fw-semibold">Mobile Number</label>
            <input type="text" class="form-control" name="bene_mobile" maxlength="10"
              placeholder="Enter mobile number">
          </div>

          <!-- Email -->
          <div class="col-md-6">
            <label class="form-label fw-semibold">Email Address</label>
            <input type="email" class="form-control" name="bene_email" placeholder="Enter email address">
          </div>

          <!-- Amount -->
          <div class="col-12">
            <label class="form-label fw-semibold">Amount</label>
            <div class="input-group">
              <span class="input-group-text">₹</span>
              <input type="number" min="1" class="form-control" name="amount" placeholder="Enter amount"
                required>
            </div>
          </div>

          <!-- Transfer Mode -->
          <div class="col-12">
            <label class="form-label fw-semibold">Transfer Mode</label>
            <select name="transfer_mode" class="form-select" required>
              <option value="">Select transfer mode</option>
              <option value="IMPS">IMPS</option>
              <option value="NEFT">NEFT</option>
              <option value="RTGS">RTGS</option>
            </select>
          </div>

          <!-- Submit -->
          <div class="col-12 pt-2">
            <div class="text-center pt-3">
              <button type="submit" class="btn btn-primary px-5 d-flex" id="submitBtn">
                <span id="btnText">
                  <i class="bx bx-send me-1"></i>
                  Submit
                </span>

                <span id="btnLoader" class="d-none">
                  <span class="spinner-border spinner-border-sm me-2"></span>
                  Processing...
                </span>
              </button>
            </div>
          </div>
        </div>
      </form>

      <div id="paymentMetaCard" class="alert alert-warning mt-4 d-none mb-0" role="alert">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
          <div>
            <div class="fw-semibold text-dark">Payment status is being checked</div>
            <div class="small mb-0">Please complete the payment in the newly opened tab.</div>
          </div>
          <div class="text-md-end">
            <div class="small text-muted">Transaction ID</div>
            <div class="fw-semibold" id="trackTxnId">--</div>
          </div>
        </div>
      </div>

    </div>
  </div>
  <div id="pageLoader"
    class="position-fixed top-0 start-0 w-100 h-100 bg-white d-none justify-content-center align-items-center"
    style="z-index: 9999;">
    <div class="text-center px-3">
      <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status"></div>
      <h5 class="mb-1 fw-semibold">Checking Payment Status</h5>
      <p class="text-muted mb-0">Please wait while we verify your transaction.</p>
    </div>
  </div>
@endsection

@section('page-script')
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

  <script>
    let paymentInterval = null;
    let paymentWindow = null;

    function showFullPageLoader() {
      $('#pageLoader').removeClass('d-none').addClass('d-flex');
    }

    function hideFullPageLoader() {
      $('#pageLoader').removeClass('d-flex').addClass('d-none');
    }

    function startStatusCheck(txnId) {
      $('#paymentMetaCard').removeClass('d-none');
      $('#trackTxnId').text(txnId);

      showFullPageLoader();

      paymentInterval = setInterval(function() {
        $.ajax({
          url: "{{ route('service.payin.check-status') }}",
          type: "POST",
          dataType: "json",
          data: {
            txn_id: txnId,
            _token: "{{ csrf_token() }}"
          },
          success: function(res) {
            if (!res.success) {
              return;
            }

            if (res.status === 'P') {
              return;
            }

            clearInterval(paymentInterval);
            hideFullPageLoader();

            if (paymentWindow && !paymentWindow.closed) {
              paymentWindow.close();
            }

            if (res.status === 'S') {
              Swal.fire({
                icon: 'success',
                title: 'Payment Successful',
                text: res.message || 'Your payment has been completed successfully.',
                confirmButtonText: 'OK'
              }).then(() => {
                window.location.reload();
              });
            } else if (res.status === 'F') {
              Swal.fire({
                icon: 'error',
                title: 'Payment Failed',
                text: res.message || 'Your payment could not be completed.',
                confirmButtonText: 'OK'
              }).then(() => {
                window.location.reload();
              });
            }
          },
          error: function() {
            // polling continue silently
          }
        });
      }, 3000);
    }

    $(document).ready(function() {

      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(pos) {
          $('#reqLat').val(pos.coords.latitude);
          $('#reqLong').val(pos.coords.longitude);
        }, function() {
          $('#reqLat').val('28.414009');
          $('#reqLong').val('77.066509');
        });
      } else {
        $('#reqLat').val('28.414009');
        $('#reqLong').val('77.066509');
      }

      $('#payoutForm').submit(function(e) {
        e.preventDefault();

        $('#ajaxMessage').html('');
        $('#submitBtn').prop('disabled', true);
        $('#btnText').addClass('d-none');
        $('#btnLoader').removeClass('d-none');

        $.ajax({
          url: "{{ route('service.payout.submit') }}",
          type: "POST",
          data: $(this).serialize(),
          dataType: "json",

          success: function(response) {
            const txnStatus = response?.data?.txn_status || response?.status || 'PENDING';
            const message = response?.message || 'Transaction status received.';
            const transactionId = response?.data?.transaction_id || '';
            const bankReference = response?.data?.bank_reference || '';

            if (txnStatus === 'SUCCESS') {
              Swal.fire({
                icon: 'success',
                title: 'Transaction Successful',
                html: `
                <div class="text-start">
                    <p><strong>Status:</strong> ${txnStatus}</p>
                    <p><strong>Message:</strong> ${message}</p>
                    <p><strong>Transaction ID:</strong> ${transactionId}</p>
                    <p><strong>Bank Reference:</strong> ${bankReference}</p>
                </div>
            `,
                confirmButtonText: 'OK'
              }).then(() => {
                window.location.reload();
              });
              return;
            }

            if (txnStatus === 'FAILED') {
              Swal.fire({
                icon: 'error',
                title: 'Transaction Failed',
                html: `
                <div class="text-start">
                    <p><strong>Status:</strong> ${txnStatus}</p>
                    <p><strong>Message:</strong> ${message}</p>
                    <p><strong>Transaction ID:</strong> ${transactionId}</p>
                    <p><strong>Bank Reference:</strong> ${bankReference || 'N/A'}</p>
                </div>
            `,
                confirmButtonText: 'OK'
              }).then(() => {
                window.location.reload();
              });
              return;
            }

            if (txnStatus === 'PENDING') {
              Swal.fire({
                icon: 'info',
                title: 'Transaction Pending',
                html: `
                <div class="text-start">
                    <p><strong>Status:</strong> ${txnStatus}</p>
                    <p><strong>Message:</strong> ${message}</p>
                    <p><strong>Transaction ID:</strong> ${transactionId}</p>
                    <p><strong>Bank Reference:</strong> ${bankReference || 'N/A'}</p>
                </div>
            `,
                confirmButtonText: 'OK'
              }).then(() => {
                window.location.reload();
              });
              return;
            }

            Swal.fire({
              icon: 'warning',
              title: 'Unknown Response',
              text: message,
              confirmButtonText: 'OK'
            }).then(() => {
              window.location.reload();
            });
          },
          error: function(xhr) {
            let message = 'Something went wrong';

            if (xhr.responseJSON && xhr.responseJSON.message) {
              message = xhr.responseJSON.message;
            }

            $('#ajaxMessage').html(
              '<div class="alert alert-danger">' + message + '</div>'
            );
          },

          complete: function() {
            $('#submitBtn').prop('disabled', false);
            $('#btnText').removeClass('d-none');
            $('#btnLoader').addClass('d-none');
          }
        });
      });
    });
  </script>
@endsection
