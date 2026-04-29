$(document).ready(function () {
  const table = $('#fund-request-table').DataTable({
    processing: true,
    serverSide: true,
    searching: false,
    paging: true,
    lengthChange: false,
    pageLength: 10,
    autoWidth: false,
    scrollX: true,
    order: [[10, 'desc']],
    dom: 'rtip',

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
      url: window.routes.fundRequestData,
      data: function (d) {
        d.reqid = $('#filter-reqid').val();
        d.wallet_txn_id = $('#filter-wallet-txn-id').val();
        d.sender_account_number = $('#filter-sender-account-number').val();
        d.company_account_number = $('#filter-company-account-number').val();
        d.transaction_utr = $('#filter-transaction-utr').val();
        d.status = $('#filter-status').val();
        d.source = $('#filter-source').val();
        d.from_date = $('#filter-from-date').val();
        d.to_date = $('#filter-to-date').val();
      }
    },

    columns: [
      { data: 'DT_RowIndex', width: '60px', orderable: false },
      { data: 'request_id', width: '150px' },
      { data: 'wallet_txn_id', width: '150px' },
      { data: 'amount', width: '150px' },
      { data: 'sender_account_number', width: '150px' },
      { data: 'company_account_number', width: '110px', className: 'text-end' },
      { data: 'transaction_utr', width: '120px', className: 'text-end' },
      { data: 'mode', width: '120px', className: 'text-end' },
      { data: 'source', width: '120px', className: 'text-center' },
      { data: 'status', width: '120px', className: 'text-center' },
      { data: 'created_at', width: '170px' }
    ],
    initComplete: function () {
      table.buttons().container().appendTo('#export-buttons');
    }
  });
  $('#applyFilter').click(function () {
    table.draw(); // reload with filters
  });
  $('#resetFilter').click(function () {
    $('#filter-reqid, #filter-wallet-txn-id, #filter-sender-account-number').val('');
    $('#filter-company-account-number, #filter-transaction-utr').val('');
    $('#filter-status, #filter-source').val('');
    $('#filter-from-date, #filter-to-date').val('');
    table.draw();
  });
  $(document).on('click', '#custom-pagination a', function () {
    table.page($(this).data('page')).draw(false);
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
  
  $(document).on('click', '.view-request', function () {
    let id = $(this).data('id');
  
    // Show sidebar
    let sidebar = new bootstrap.Offcanvas(document.getElementById('fundRequestSidebar'));
    sidebar.show();
  
    // Loading spinner
    $('#fundRequestDetails').html(`
          <div class="text-center py-5">
              <div class="spinner-border text-primary"></div>
          </div>
      `);
  
    // AJAX call
    $.ajax({
      url: '/fund-request/details/' + id,
      type: 'GET',
      success: function (response) {
        $('#fundRequestDetails').html(response);
      },
      error: function () {
        $('#fundRequestDetails').html('<div class="text-danger">Failed to load details.</div>');
      }
    });
  });
});
