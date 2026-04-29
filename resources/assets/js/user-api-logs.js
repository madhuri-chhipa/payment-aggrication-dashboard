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
    order: [[7, 'desc']],
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
        d.event = $('#filter-event').val();
        d.api_url = $('#filter-api-url').val();
        d.http_code = $('#filter-http-code').val();
        d.from_date = $('#filter-from-date').val();
        d.to_date = $('#filter-to-date').val();
      }
    },

    columns: [
      { data: 'DT_RowIndex', width: '80px', orderable: false },
      { data: 'event', width: '180px' },
      { data: 'api_url', width: '100px' },
      { data: 'header', width: '100px' },
      { data: 'request', width: '100px' },
      { data: 'http_code', width: '100px' },
      { data: 'response', width: '100px' },
      { data: 'created_at', width: '170px' },
    ],

    initComplete: function () {
      table.buttons().container().appendTo('#export-buttons');
    }
  });
  $('#applyFilter').click(function () {
    table.draw(); // reload with filters
  });
  $('#resetFilter').click(function () {
    $('#filter-event','#filter-api-url','#filter-http-code').val('');
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
});
