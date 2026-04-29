@extends('layouts/layoutMaster')
@section('title', 'Track Complaint')

@section('page-style')
  <style>
    #txnRefList {
      background: #ffffff;
      border-radius: 6px;
      box-shadow: 0 6px 14px rgba(0, 0, 0, 0.08);
    }

    #txnRefList .list-group-item {
      background: #ffffff;
      border: none;
      border-bottom: 1px solid #f1f1f1;
      cursor: pointer;
    }

    #txnRefList .list-group-item:last-child {
      border-bottom: none;
    }

    #txnRefList .list-group-item:hover {
      background: #f8f9fa;
      color: #000;
    }
  </style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
@endsection

@section('content')

  <div class="card">
    <div class="card-header px-1 pt-1 d-flex justify-content-between align-items-center">
      <h5 class="mb-0 pt-2 ps-4">Check Transaction Status</h5>
      <img src="{{ asset('assets/img/logo/bbpsPrimaryLogo.jpg') }}" alt="Bharat-Connect" style="height: 60px;">
    </div>
    <div class="card-body">
      <form id="checkStatusForm">
        @csrf
        <!-- Transaction Ref ID -->
        <div class="mb-3 position-relative">
          <label class="form-label">B-Connect Transaction ID</label>
          <input type="text" name="txn_ref_id" id="txn_ref_id" class="form-control"
            placeholder="Enter B-Connect Transaction ID" autocomplete="off">
          <div id="txnRefList" class="list-group position-absolute w-100 d-none"
            style="z-index:1050; max-height:220px; overflow-y:auto; background:#fff; border:1px solid #dee2e6;">
          </div>
        </div>
        <!-- OR Divider -->
        <div class="text-center my-3">
          <span class="badge bg-label-secondary px-3 py-2">OR</span>
        </div>

        <!-- Mobile Search -->
        <div class="mb-3">
          <label class="form-label">Mobile Number</label>
          <input type="text" name="mobile" id="mobile" class="form-control" placeholder="Enter Mobile Number">
        </div>

        <div class="row">
          <div class="col-md-6">
            <label class="form-label">From Date</label>
            <input type="date" name="from_date" id="from_date" class="form-control">
          </div>

          <div class="col-md-6">
            <label class="form-label">To Date</label>
            <input type="date" name="to_date" id="to_date" class="form-control">
          </div>
        </div>

        <!-- Single Button -->
        <div class="mt-4">
          <button type="submit" class="btn btn-primary">
            Check Status
          </button>
        </div>

      </form>
      <div class="modal fade" id="txnStatusModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">

            <div class="modal-header">
              <h5 class="modal-title">Transaction Status</h5>
              <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

              <div class="alert alert-success mb-3" id="customMessage"></div>

              <table class="table table-bordered">

                <tr>
                  <th>Status</th>
                  <td id="m_status"></td>
                </tr>

                <tr>
                  <th>Date</th>
                  <td id="m_date"></td>
                </tr>

                <tr>
                  <th>Amount</th>
                  <td id="m_amount"></td>
                </tr>

                <tr>
                  <th>Biller ID</th>
                  <td id="m_biller"></td>
                </tr>

                <tr>
                  <th>Transaction Ref</th>
                  <td id="m_txn_ref"></td>
                </tr>

                <tr>
                  <th>Approval Ref</th>
                  <td id="m_approval"></td>
                </tr>

                <tr>
                  <th>Customer</th>
                  <td id="m_customer"></td>
                </tr>

                <tr>
                  <th>Mobile</th>
                  <td id="m_mobile"></td>
                </tr>

                <tr>
                  <th>Bill Number</th>
                  <td id="m_bill_no"></td>
                </tr>

                <tr>
                  <th>Bill Period</th>
                  <td id="m_bill_period"></td>
                </tr>

                <tr>
                  <th>Due Date</th>
                  <td id="m_due"></td>
                </tr>

                <tr>
                  <th>Convenience Fee</th>
                  <td id="m_fee"></td>
                </tr>

                <tr>
                  <th>Request ID</th>
                  <td id="m_req"></td>
                </tr>

              </table>

            </div>

          </div>
        </div>
      </div>
    </div>
  </div>

@endsection


@section('page-script')
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

  <script>
    $(document).ready(function() {
      // fetch top 15 on focus
      $('#txn_ref_id').on('focus', function() {
        fetchTxn('');
      });
      // search when typing
      $('#txn_ref_id').on('keyup', function() {
        let search = $(this).val();
        fetchTxn(search);
      });

      function fetchTxn(search) {
        $.ajax({
          url: "{{ route('transactions.search-ref') }}",
          type: "GET",
          data: {
            search: search
          },
          success: function(res) {
            let html = '';
            if (res.length > 0) {
              res.forEach(function(item) {
                html += `<a href="#" 
                class="list-group-item list-group-item-action txnItem">
                ${item.bbps_txn_ref_id}
               </a>`;
              });
            } else {
              html = `<div class="list-group-item text-muted">
                        No results found
                      </div>`;
            }
            $('#txnRefList').html(html).removeClass('d-none');
          }
        });
      }

      // select item
      $(document).on('click', '.txnItem', function(e) {
        e.preventDefault();
        let value = $(this).text();
        $('#txn_ref_id').val(value);
        $('#txnRefList').addClass('d-none');
      });

      // hide when clicking outside
      $(document).click(function(e) {
        if (!$(e.target).closest('#txn_ref_id, #txnRefList').length) {
          $('#txnRefList').addClass('d-none');
        }
      });

      $('#txnStatusModal').on('hidden.bs.modal', function() {
        // clear form fields
        $('#checkStatusForm')[0].reset();
        // clear modal data
        $('#txnStatusModal td').text('');
      });
      $('#txn_ref_id').on('input', function() {
        if ($(this).val()) {
          $('#mobile, #from_date, #to_date').prop('disabled', true);
        } else {
          $('#mobile, #from_date, #to_date').prop('disabled', false);
        }
      });

      $('#mobile').on('input', function() {
        if ($(this).val()) {
          $('#txn_ref_id').prop('disabled', true);
        } else {
          $('#txn_ref_id').prop('disabled', false);
        }
      });
    });
    $(document).on('submit', '#checkStatusForm', function(e) {
      e.preventDefault();
      let btn = $(this).find("button[type='submit']");
      let originalText = btn.text();
      btn.prop("disabled", true)
        .html('<span class="spinner-border spinner-border-sm"></span> Checking...');
      let txnRef = $('#txn_ref_id').val();
      let mobile = $('#mobile').val();
      let fromDate = $('#from_date').val();
      let toDate = $('#to_date').val();
      let data = {
        _token: "{{ csrf_token() }}"
      };
      if (txnRef) {
        data.trackType = 'TRANS_REF_ID';
        data.trackValue = txnRef;
      } else if (mobile && fromDate && toDate) {
        data.trackType = 'MOBILE_NO';
        data.trackValue = mobile;
        data.from_date = fromDate;
        data.to_date = toDate;
      } else {
        toastr.error("Enter Transaction Ref ID OR Mobile + Date Range");
        btn.prop("disabled", false).text(originalText);
        return;
      }
      $.ajax({
        url: "{{ route('transactions.check-status') }}",
        type: "POST",
        data: data,
        success: function(res) {
          btn.prop("disabled", false).text(originalText);
          if (res.status) {
            let d = res.data;
            $("#customMessage").text(res.message);
            $("#m_status").text(d.status);
            $("#m_date").text(d.date);
            $("#m_amount").text(d.amount);
            $("#m_biller").text(d.biller_id);
            $("#m_txn_ref").text(d.txn_reference);
            $("#m_approval").text(d.approval_ref);
            $("#m_customer").text(d.customer_name);
            $("#m_mobile").text(d.mobile);
            $("#m_bill_no").text(d.bill_number);
            $("#m_bill_period").text(d.bill_period);
            $("#m_due").text(d.due_date);
            $("#m_fee").text(d.conv_fee);
            $("#m_req").text(d.request_id);
            $("#txnStatusModal").modal("show");
            // reset form fields
            $("#checkStatusForm")[0].reset();
          } else {
            toastr.error(res.message);
          }
        },
        error: function() {
          btn.prop("disabled", false).text(originalText);
          toastr.error("Something went wrong");
        }
      });

    });
    // $(document).on('submit', '#checkStatusForm', function(e) {
    //   e.preventDefault();

    //   let btn = $(this).find('button[type="submit"]');
    //   btn.prop('disabled', true).text('Tracking...');

    //   $.ajax({
    //     url: "{{ route('complaint.track') }}",
    //     type: "POST",
    //     data: $(this).serialize(),

    //     success: function(res) {

    //       if (res.status) {

    //         $("#resultBox").removeClass("d-none");
    //         $("#complaint_status").text(res.complaint_status);
    //         $("#complaint_remarks").text(res.remarks);

    //         toastr.success(res.message);

    //       } else {
    //         toastr.error(res.message);
    //       }

    //       btn.prop('disabled', false).text('Track Status');
    //     },

    //     error: function() {
    //       toastr.error("Something went wrong");
    //       btn.prop('disabled', false).text('Track Status');
    //     }
    //   });
    // });
  </script>

@endsection
