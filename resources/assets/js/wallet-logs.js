$(document).ready(function () {
  const table = $('#wallet-table').DataTable({
    processing: true,
    serverSide: true,
    searching: false,
    paging: true,
    lengthChange: false,
    pageLength: 10,
    autoWidth: false,
    scrollX: true,
    order: [[8, 'desc']],
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
            }, 300);

            return false; // 🔥 VERY IMPORTANT
          });

          dt.draw();
        }
      }
    ],

    ajax: {
      url: window.routes.walletData,
      data: function (d) {
        d.refid = $('#filter-ref').val();
        d.user = $('#filter-user').val();
        d.wallet = $('#filter-wallet').val();
        d.from_date = $('#filter-from-date').val();
        d.to_date = $('#filter-to-date').val();
      }
    },

    columns: [
      { data: 'DT_RowIndex', width: '60px', orderable: false },
      { data: 'user_info', width: '200px' },
      { data: 'refid', width: '120px' },
      { data: 'service_name', width: '120px' },
      { data: 'total_amount', width: '200px' },
      { data: 'credit', width: '120px' },
      { data: 'debit', width: '120px' },
      { data: 'closing_balance', width: '120px' },
      { data: 'created_at', width: '120px' },
      { data: 'description', width: '220px' }
    ],
    initComplete: function () {
      table.buttons().container().appendTo('#export-buttons');
    }
  });
  $('#applyFilter').click(function () {
    table.draw(); // reload with filters
  });
  $('#resetFilter').click(function () {
    $('#filter-ref, #filter-user, #filter-wallet').val('');
    $('#filter-from-date, #filter-to-date').val('');
    table.draw();
  });
});
