$(document).ready(function () {
  const table = $('#login-logs-table').DataTable({
    processing: true,
    serverSide: true,
    searching: false,
    paging: true, // keep paging ON (IMPORTANT)
    lengthChange: false,
    pageLength: 10,
    order: [[5, 'desc']],

    dom: 'Brtip',
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
      url: window.routes.loginLogData,
      data: function (d) {
        d.user = $('#filter-user').val();
        d.ip_address = $('#filter-ip').val();
        d.from_date = $('#filter-from-date').val();
        d.to_date = $('#filter-to-date').val();
      }
    },

    columns: [
      { data: 'DT_RowIndex', orderable: false },
      { data: 'user_info', orderable: false },
      { data: 'otp', className: 'text-center' },
      { data: 'ip_address' },
      { data: 'location', orderable: false },
      { data: 'logged_at' }
    ],
    initComplete: function () {
      table.buttons().container().appendTo('#export-buttons');
    }
  });
  $('#applyFilter').click(function () {
    table.draw(); // reload with filters
  });
  $('#resetFilter').click(function () {
    $('#filter-user, #filter-ip, #filter-from-date, #filter-to-date').val('');
    table.draw();
  });
});
