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
    order: [[10, 'desc']],
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
        d.txn_id = $('#filter-txn').val();
        d.user = $('#filter-user').val();
        d.status = $('#filter-status').val();
        d.api_txn_id = $('#filter-api-txn-id').val();
        d.from_date = $('#filter-from-date').val();
        d.to_date = $('#filter-to-date').val();
      }
    },

    columns: [
      { data: 'DT_RowIndex', width: '100px', orderable: false },
      { data: 'user_info', width: '200px' },
      { data: 'txn_id', width: '150px' },
      { data: 'api_txn_id', width: '150px' },
      { data: 'api', width: '90px' },
      { data: 'amount', width: '110px', className: 'text-end' },
      { data: 'total_charge', width: '200px', className: 'text-end' },
      { data: 'total_amount', width: '120px', className: 'text-end' },
      { data: 'status', width: '120px', className: 'text-center' },
      { data: 'utr', width: '160px' },
      { data: 'created_at', width: '170px' },
      { data: 'response_message', width: '220px' },
      { data: 'actions', width: '100px', orderable: false }
    ],

    initComplete: function () {
      table.buttons().container().appendTo('#export-buttons');
    }
  });
  $('#applyFilter').click(function () {
    table.draw(); // reload with filters
  });
  $('#resetFilter').click(function () {
    $('#filter-txn, #filter-user, #filter-account').val('');
    $('#filter-status, #filter-payment-status').val('');
    $('#filter-from-date, #filter-to-date').val('');
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
  function getStatusBadge(status) {
    switch (status) {
      case 'P':
        return `<span class="badge bg-warning">Pending</span>`;
      case 'S':
        return `<span class="badge bg-success">Success</span>`;
      case 'F':
        return `<span class="badge bg-danger">Failed</span>`;
      case 'R':
        return `<span class="badge bg-info">Refunded</span>`;
      case 'Q':
        return `<span class="badge bg-dark">Queued</span>`;
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
      let basicDetailsHtml = '';
  
      if (data.basic_details) {
        let details = typeof data.basic_details === 'string' ? JSON.parse(data.basic_details) : data.basic_details;
  
        basicDetailsHtml = Object.entries(details)
          .map(([key, value]) => {
            return `
                <div class="row mb-1">
                    <div class="col-5 fw-semibold text-capitalize">
                        ${key.replaceAll('_', ' ')}
                    </div>
                    <div class="col-7">
                        ${value ?? '-'}
                    </div>
                </div>
              `;
          })
          .join('');
      }
  
      let html = `
          <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-5 fw-semibold">Txn ID</div>
                <div class="col-7">${data.txn_id}</div>
            </div>
  
            <div class="row mb-2">
                <div class="col-5 fw-semibold">API Txn ID</div>
                <div class="col-7">${data.api_txn_id ?? '-'}</div>
            </div>
  
            <div class="row mb-2">
                <div class="col-5 fw-semibold">User Info</div>
                <div class="col-7">
                    ${data.user?.company_name ?? '-'}<br>
                    ${data.user?.email ?? '-'}<br>
                    ${data.user?.mobile_number ?? '-'}
                </div>
            </div>
  
            <div class="row mb-2">
              <div class="col-5 fw-semibold">Amount</div>
              <div class="col-7">₹${data.amount}</div>
            </div>
  
            <div class="row mb-2">
              <div class="col-5 fw-semibold">Charges</div>
              <div class="col-7">
                  ₹${data.total_charge}  
                  (GST ${data.gst_amount} + Charge ${data.charge_amount})
              </div>
            </div>
  
            <div class="row mb-2">
                <div class="col-5 fw-semibold">Total Amount</div>
                <div class="col-7">₹${data.total_amount}</div>
            </div>
  
            <div class="row mb-2">
                <div class="col-5 fw-semibold">Transfer Mode</div>
                <div class="col-7">${data.transfer_mode}</div>
            </div>
  
            <div class="row mb-2">
                <div class="col-5 fw-semibold">Status</div>
                <div class="col-7">${getStatusBadge(data.status)}</div>
            </div>
  
            <div class="row mb-2">
                <div class="col-5 fw-semibold">UTR</div>
                <div class="col-7">${data.utr ?? '-'}</div>
            </div>
  
            <div class="row mb-2">
                <div class="col-5 fw-semibold">API</div>
                <div class="col-7">${data.api ?? '-'}</div>
            </div>
  
            <div class="row mb-2">
                <div class="col-5 fw-semibold">Description</div>
                <div class="col-7">${data.description ?? '-'}</div>
            </div>
  
            <div class="row mb-2">
                <div class="col-5 fw-semibold">Response</div>
                <div class="col-7">${data.response_message ?? '-'}</div>
            </div>
  
            <h6 class="fw-semibold text-capitalize mt-3">Basic Details</h6>
            <div class="mb-4 ps-3">
              ${basicDetailsHtml ?? '-'}
            </div>
  
            <div class="row mb-2">
                <div class="col-5 fw-semibold">IP</div>
                <div class="col-7">${data.ip ?? '-'}</div>
            </div>
  
            <div class="row mb-2">
                <div class="col-5 fw-semibold">Created At</div>
                <div class="col-7">${data.created_at}</div>
            </div>
          </div>
  
        `;
  
      $('#txnSidebarBody').html(html);
    });
  });
  
  // Close sidebar
  $('#closeSidebar, #sidebarOverlay').on('click', function () {
    $('#txnSidebar').removeClass('open');
    $('#sidebarOverlay').removeClass('show');
  });
});
