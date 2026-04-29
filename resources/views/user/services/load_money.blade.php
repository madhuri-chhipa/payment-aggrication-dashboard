@extends('layouts/layoutMaster')

@section('title', 'Load Money')

@section('content')
  <div class="card">
    <div class="card-header">
      <h5 class="mb-1">Load Money</h5>
      <p class="text-muted mb-0">
        Add money securely using your card and track payment status automatically.
      </p>
    </div>
    <div class="card-body p-4 p-md-5">
      <div class="alert alert-light border rounded-3 mb-4" role="alert">
        <div class="d-flex">
          <i class="bx bx-info-circle text-primary me-2 fs-5"></i>
          <div class="small text-dark">
            After clicking <span class="fw-semibold text-dark">Proceed to Payment</span>, the payment page will
            open in a new tab. This page will automatically check and update your transaction status.
          </div>
        </div>
      </div>

      <div id="ajaxMessage"></div>

      <form id="loadMoneyForm">
        @csrf

        <input type="hidden" name="transfer_mode" value="PG">
        <input type="hidden" name="reqLat" id="reqLat">
        <input type="hidden" name="reqLong" id="reqLong">

        <div class="row g-3">

          <div class="col-12">
            <label class="form-label fw-semibold">Amount</label>
            <div class="input-group">
              <span class="input-group-text">₹</span>
              <input type="number" min="1" class="form-control" name="amount" placeholder="Enter amount"
                required>
            </div>
          </div>

          <div class="col-12">
            <label class="form-label fw-semibold">Payer Name</label>
            <input type="text" class="form-control" name="payer_name" placeholder="Enter payer name"
              required>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Mobile Number</label>
            <input type="text" class="form-control" name="transfer_phone_number" maxlength="10"
              placeholder="Optional">
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Email Address</label>
            <input type="email" class="form-control" name="transfer_email" placeholder="Optional">
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Card Type</label>
            <select name="cardType" class="form-select" required>
              <option value="">Select card type</option>
              <option value="DebitCard">Debit Card</option>
              <option value="CreditCard">Credit Card</option>
              <option value="PrepaidCard">Prepaid Card</option>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Card Network</label>
            <select name="cardNetwork" class="form-select" required>
              <option value="">Select network</option>
              <option value="VISA CARD">VISA</option>
              <option value="MASTER CARD">Mastercard</option>
              <option value="RUPAY CARD">Rupay</option>
            </select>
          </div>

          <div class="col-12 pt-2">
            <div class="text-center pt-3">
              <button type="submit" class="btn btn-primary px-5 d-flex" id="submitBtn">
                <span id="btnText">
                  <i class="bx bx-credit-card me-1"></i>
                  Proceed to Payment
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

      $('#loadMoneyForm').submit(function(e) {
        e.preventDefault();

        $('#ajaxMessage').html('');
        $('#submitBtn').prop('disabled', true);
        $('#btnText').addClass('d-none');
        $('#btnLoader').removeClass('d-none');

        $.ajax({
          url: "{{ route('service.payin.submit') }}",
          type: "POST",
          data: $(this).serialize(),
          dataType: "json",

          success: function(response) {
            if (response.data.payment_url) {
              paymentWindow = window.open(response.data.payment_url, '_blank');
              if (!paymentWindow) {
                $('#ajaxMessage').html(
                  '<div class="alert alert-warning">Popup was blocked by the browser. Please allow popups and try again.</div>'
                );
                return;
              }
              startStatusCheck(response.data.transaction_id);
            } else {
              $('#ajaxMessage').html(
                '<div class="alert alert-danger">' + (response.message ||
                  'Unable to create payment link.') +
                '</div>'
              );
            }
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
