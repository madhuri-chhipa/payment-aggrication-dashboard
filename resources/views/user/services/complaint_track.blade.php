@extends('layouts/layoutMaster')
@section('title', 'Track Complaint')

@section('page-style')
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
@endsection

@section('content')

  <div class="card">
    <div class="card-header px-1 pt-1 d-flex justify-content-between align-items-center">
      <h5 class="mb-0 pt-2 ps-4">Track Complaint Status</h5>
      <img src="{{ asset('assets/img/logo/bbpsPrimaryLogo.jpg') }}" alt="Bharat-Connect" style="height: 60px;">
    </div>

    <div class="card-body">
      <form id="trackComplaintForm">
        @csrf
        {{-- Complaint ID --}}
        <div class="mb-3">
          <label class="form-label">Bharat Connect Complaint ID *</label>
          <input type="text" name="complaintId" id="complaintId" class="form-control"
            placeholder="Enter B-Connect Complaint ID" required>
        </div>

        {{-- Complaint Type --}}
        <div class="mb-3">
          <label class="form-label">Complaint Type *</label>
          <select name="complaintType" id="complaintType" class="form-control" required>
            <option value="">Select Type</option>
            <option value="Transaction">Transaction</option>
            <option value="Service">Service</option>
          </select>
        </div>

        <button type="submit" class="btn btn-primary">
          Track Status
        </button>

      </form>
      <div class="modal fade" id="complaintStatusModal" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content">

            <div class="modal-header">
              <h5 class="modal-title">Complaint Status</h5>
              <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

              <div class="alert alert-success mb-3" id="complaintMessage"></div>

              <table class="table table-bordered">

                <tr>
                  <th>Complaint ID</th>
                  <td id="c_id"></td>
                </tr>

                <tr>
                  <th>Status</th>
                  <td id="c_status"></td>
                </tr>

                <tr>
                  <th>Assigned To</th>
                  <td id="c_assigned"></td>
                </tr>

                <tr>
                  <th>Remarks</th>
                  <td id="c_remarks"></td>
                </tr>

                <tr>
                  <th>Response Code</th>
                  <td id="c_code"></td>
                </tr>

                <tr>
                  <th>Response Reason</th>
                  <td id="c_reason"></td>
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
    $('#complaintStatusModal').on('hidden.bs.modal', function() {

      $('#trackComplaintForm')[0].reset();

      $('#complaintStatusModal td').text('');

    });
    $(document).on('submit', '#trackComplaintForm', function(e) {
      e.preventDefault();
      let btn = $(this).find('button[type="submit"]');
      let originalText = btn.text();
      btn.prop('disabled', true)
        .html('<span class="spinner-border spinner-border-sm"></span> Tracking...');
      $.ajax({
        url: "{{ route('complaint.track') }}",
        type: "POST",
        data: $(this).serialize(),
        success: function(res) {
          btn.prop('disabled', false).text(originalText);
          if (res.status) {
            let d = res.data;
            $("#complaintMessage").text(res.message);
            $("#c_id").text(d.complaint_id);
            $("#c_status").text(d.status);
            $("#c_assigned").text(d.assigned);
            $("#c_remarks").text(d.remarks);
            $("#c_code").text(d.code);
            $("#c_reason").text(d.reason);
            $("#complaintStatusModal").modal("show");
            toastr.success("Complaint status fetched");
            $("#trackComplaintForm")[0].reset();
          } else {
            toastr.error(res.message);
          }
        },
        error: function() {
          btn.prop('disabled', false).text(originalText);
          toastr.error("Something went wrong");
        }
      });
    });
  </script>

@endsection
