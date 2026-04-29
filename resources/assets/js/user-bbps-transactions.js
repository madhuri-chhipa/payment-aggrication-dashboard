$(document).ready(function () {
  const table = $('#txn-table').DataTable({
    processing: true,
    serverSide: true,
    searching: false,
    paging: true,
    lengthChange: false,
    pageLength: 10,
    autoWidth: false,
    scrollX: true, // enables horizontal scroll ONLY for table
    order: [[8, 'desc']],
    dom: 'Brtip', // remove default pagination & info

    buttons: [
      {
        extend: 'excelHtml5',
        text: '<i class="fa fa-file-excel me-1"></i> Excel',
        className: 'btn bg-primary btn-sm',
        exportOptions: {
          columns: ':not(:last-child)' // ✅ excludes last column
        },
        action: function (e, dt, button, config) {
          var self = this;
          var oldStart = dt.settings()[0]._iDisplayStart;

          dt.one('preXhr', function (e, s, data) {
            data.start = 0;
            data.length = -1;
          });

          dt.one('preDraw', function (e, settings) {
            $.fn.dataTable.ext.buttons.excelHtml5.action.call(self, e, dt, button, config);

            settings._iDisplayStart = oldStart;

            dt.one('preXhr', function (e, s, data) {
              data.start = oldStart;
            });

            setTimeout(function () {
              dt.ajax.reload(null, false);
            }, 100);

            return false;
          });

          dt.draw();
        }
      },
      {
        extend: 'pdfHtml5',
        text: '<i class="fa fa-file-pdf me-1"></i> PDF',
        className: 'btn bg-secondary btn-sm',
        orientation: 'landscape',
        pageSize: 'A4',

        customize: function (doc) {
          doc.defaultStyle.fontSize = 7;
          doc.styles.tableHeader.fontSize = 8;
          doc.pageMargins = [10, 10, 10, 10];
          doc.content[1].table.widths = Array(doc.content[1].table.body[0].length).fill('*');
        },

        action: function (e, dt, button, config) {
          var self = this;
          var oldStart = dt.settings()[0]._iDisplayStart;

          dt.one('preXhr', function (e, s, data) {
            data.start = 0;
            data.length = -1;
          });

          dt.one('preDraw', function (e, settings) {
            $.fn.dataTable.ext.buttons.pdfHtml5.action.call(self, e, dt, button, config);

            settings._iDisplayStart = oldStart;

            dt.one('preXhr', function (e, s, data) {
              data.start = oldStart;
            });

            setTimeout(function () {
              dt.ajax.reload(null, false);
            }, 100);

            return false; // 🔥 VERY IMPORTANT
          });

          dt.draw();
        }
      }
    ],

    ajax: {
      url: window.routes.txnData,
      data: function (d) {
        d.request_id = $('#filter-request-id').val();
        d.biller = $('#filter-biller').val();
        d.status = $('#filter-status').val();
        d.bbps_txn = $('#filter-bbps-txn').val();
        d.from_date = $('#filter-from-date').val();
        d.to_date = $('#filter-to-date').val();
      }
    },

    columns: [
      { data: 'DT_RowIndex', orderable: false },

      { data: 'request_id' },

      { data: 'biller' },

      { data: 'customer_name' },

      { data: 'amount' },

      { data: 'status' },

      { data: 'bbps_txn_ref_id' },

      { data: 'approval_ref_number' },

      { data: 'created_at' },

      { data: 'actions', orderable: false }
    ],

    initComplete: function () {
      table.buttons().container().appendTo('#export-buttons');
    }
  });

  $('#applyFilter').click(function () {
    table.draw();
  });

  $('#resetFilter').click(function () {
    $('#filter-request-id').val('');
    $('#filter-biller').val('');
    $('#filter-status').val('');
    $('#filter-bbps-txn').val('');
    $('#filter-from-date').val('');
    $('#filter-to-date').val('');

    table.draw();
  });
  $(document).on('shown.bs.dropdown', function (e) {
    var $dropdown = $(e.target).closest('.dropdown');

    // Only move DataTable action dropdowns, not navbar
    if ($dropdown.hasClass('dt-dropdown')) {
      var $menu = $dropdown.find('.dropdown-menu');

      // Get button position
      var offset = $dropdown.offset();
      var height = $dropdown.outerHeight();

      // Append to body and position it
      $menu.appendTo('body').css({
        position: 'absolute',
        top: offset.top + height,
        left: offset.left,
        'z-index': 1050 // Bootstrap dropdown z-index
      });
    }
  });

  // Restore dropdown to original place when hidden
  $(document).on('hidden.bs.dropdown', function (e) {
    var $dropdown = $(e.target).closest('.dropdown');
    if ($dropdown.hasClass('dt-dropdown')) {
      var $menu = $('body')
        .find('.dropdown-menu')
        .filter(function () {
          return $(this).data('original-dropdown') === $dropdown[0];
        });
      $menu.appendTo($dropdown);
    }
  });
  /* VIEW SIDEBAR */
  function getStatusBadge(status) {
    switch (status) {
      case 'PENDING':
        return `<span class="badge bg-warning">Pending</span>`;
      case 'SUCCESS':
        return `<span class="badge bg-success">Success</span>`;
      case 'FAILED':
        return `<span class="badge bg-danger">Failed</span>`;
      default:
        return `<span class="badge bg-secondary">Unknown</span>`;
    }
  }
  $(document).on('click', '.view-txn', function () {
    let id = $(this).data('id');

    $('#txnSidebar').addClass('open');
    $('#sidebarOverlay').addClass('show');
    $('#txnSidebarBody').html('<div class="p-4 text-center">Loading...</div>');

    $.get(`${window.routes.viewTxn}/${id}`, function (data) {
      let api = {};

      if (data.api_response) {
        try {
          api = JSON.parse(data.api_response);
        } catch (e) {
          api = {};
        }
      }

      /* INPUT PARAMS */

      let inputHtml = '';

      if (api.inputParams?.input) {
        api.inputParams.input.forEach(p => {
          inputHtml += `
            <div class="row mb-1">
              <div class="col-5 fw-semibold">${p.paramName}</div>
              <div class="col-7">${p.paramValue}</div>
            </div>
          `;
        });
      }

      let html = `
  
      <div class="container-fluid px-0">
  
        <h6 class="fw-bold">Transaction Info</h6>
  
        <div class="row mb-2">
          <div class="col-5 fw-semibold">Request ID</div>
          <div class="col-7">${data.request_id}</div>
        </div>
  
        <div class="row mb-2">
          <div class="col-5 fw-semibold">Biller ID & Name</div>
          <div class="col-7">${data.biller_id ?? '-'}<br>${data.biller_name ?? '-'}</div>
        </div>
  
        <div class="row mb-2">
          <div class="col-5 fw-semibold">BBPS Txn Ref</div>
          <div class="col-7">${api.txnRefId ?? '-'}</div>
        </div>
  
        <div class="row mb-2">
          <div class="col-5 fw-semibold">Approval Ref</div>
          <div class="col-7">${api.approvalRefNumber ?? '-'}</div>
        </div>
  
        <div class="row mb-2">
          <div class="col-5 fw-semibold">Response Code</div>
          <div class="col-7">${api.responseCode ?? '-'}</div>
        </div>
  
        <div class="row mb-2">
          <div class="col-5 fw-semibold">Response Reason</div>
          <div class="col-7">${api.responseReason ?? '-'}</div>
        </div>
  
        <hr>
  
        <h6 class="fw-bold">Customer Info</h6>
  
        <div class="row mb-2">
          <div class="col-5 fw-semibold">Customer Name</div>
          <div class="col-7">${api.respCustomerName ?? '-'}</div>
        </div>
  
        <div class="row mb-2">
          <div class="col-5 fw-semibold">Mobile</div>
          <div class="col-7">${data.customer_mobile ?? '-'}</div>
        </div>
  
        <div class="row mb-2">
          <div class="col-5 fw-semibold">Email</div>
          <div class="col-7">${data.customer_email ?? '-'}</div>
        </div>
  
        <hr>
  
        <h6 class="fw-bold">Bill Info</h6>
  
        <div class="row mb-2">
          <div class="col-5 fw-semibold">Bill Amount</div>
          <div class="col-7">₹ ${api.respAmount ?? '-'}</div>
        </div>
  
        <div class="row mb-2">
          <div class="col-5 fw-semibold">Bill Number</div>
          <div class="col-7">${api.respBillNumber ?? '-'}</div>
        </div>
  
        <div class="row mb-2">
          <div class="col-5 fw-semibold">Bill Period</div>
          <div class="col-7">${api.respBillPeriod ?? '-'}</div>
        </div>
  
        <div class="row mb-2">
          <div class="col-5 fw-semibold">Bill Date</div>
          <div class="col-7">${api.respBillDate ?? '-'}</div>
        </div>
  
        <div class="row mb-2">
          <div class="col-5 fw-semibold">Due Date</div>
          <div class="col-7">${api.respDueDate ?? '-'}</div>
        </div>
  
        <div class="row mb-2">
          <div class="col-5 fw-semibold">Convenience Fee</div>
          <div class="col-7">₹ ${api.custConvFee ?? '0'}</div>
        </div>
  
        <hr>
  
        <h6 class="fw-bold">Bill Parameters</h6>
  
        <div class="mb-3 ps-3">
          ${inputHtml || '-'}
        </div>
  
        <hr>
  
        <h6 class="fw-bold">System Info</h6>
  
        <div class="row mb-2">
          <div class="col-5 fw-semibold">Status</div>
          <div class="col-7">${getStatusBadge(data.status)}</div>
        </div>
  
        <div class="row mb-2">
          <div class="col-5 fw-semibold">Created At</div>
          <div class="col-7">${data.created_at_formatted}</div>
        </div>
  
      </div>
      `;

      $('#txnSidebarBody').html(html);
    });
  });

  $('#closeSidebar,#sidebarOverlay').click(function () {
    $('#txnSidebar').removeClass('open');
    $('#sidebarOverlay').removeClass('show');
  });

  /* PRINT */
  $(document).on('click', '.print-txn', function () {
    let id = $(this).data('id');
    window.open('/transactions/print/' + id, '_blank');
  });
});
