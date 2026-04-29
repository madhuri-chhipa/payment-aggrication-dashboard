$(document).ready(function () {
  $(document).on('click', '.delete-user', function () {
    let id = $(this).data('id');
  
    Swal.fire({
      title: 'Are you sure?',
      text: 'This user will be moved to trash!',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Yes, delete it',
      cancelButtonText: 'Cancel'
    }).then(result => {
      if (result.isConfirmed) {
        $.ajax({
          url: window.routes.deleteUser + '/' + id,
          type: 'DELETE',
          data: {
            _token: window.csrfToken
          },
          success: function (res) {
            Swal.fire('Deleted!', res.message ?? 'User deleted successfully', 'success');
  
            $('#users-table').DataTable().draw(false);
          },
          error: function () {
            Swal.fire('Error!', 'Something went wrong while deleting', 'error');
          }
        });
      }
    });
  });
  $(document).on('change', '.status-toggle', function () {
    let userId = $(this).data('id');
    let status = $(this).prop('checked') ? 'A' : 'B';
  
    $.ajax({
      url: window.routes.toggleStatus + '/' + userId,
      type: 'POST',
      data: {
        _token: window.csrfToken,
        status: status
      },
      success: function (res) {
        toastr.success(status ? 'User activated successfully' : 'User deactivated successfully');
      },
      error: function () {
        toastr.error('Something went wrong');
      }
    });
  });
  const table = $('#users-table').DataTable({
    processing: true,
    serverSide: true,
    searching: false,
    paging: true, // keep paging ON (IMPORTANT)
    lengthChange: false,
    pageLength: 10,
    order: [[6, 'desc']],
    dom: 'Brtip', // remove default pagination & info
    buttons: [
      {
        extend: 'excelHtml5',
        text: '<i class="fa fa-file-excel me-1"></i> Excel',
        className: 'btn bg-primary btn-sm',
        exportOptions: {
          columns: ':not(:last-child)',
          format: {
            body: function (data, row, column, node) {
              // 🔥 If status column contains hidden export text
              if ($(node).find('.export-status').length) {
                return $(node).find('.export-status').text().trim();
              }

              return data;
            }
          }
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
            dt.ajax.reload(null, false);

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

        exportOptions: {
          columns: ':not(:last-child)',
          format: {
            body: function (data, row, column, node) {
              if ($(node).find('.export-status').length) {
                return $(node).find('.export-status').text().trim();
              }

              return data;
            }
          }
        },

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

            // 🔥 Restore table state properly
            settings._iDisplayStart = oldStart;
            dt.ajax.reload(null, false);

            return false; // VERY IMPORTANT
          });

          dt.draw();
        }
      }
    ],
    ajax: {
      url: window.routes.userData,
      data: function (d) {
        d.uid = $('#filter-uid').val();
        d.user = $('#filter-user').val();
        d.status = $('#filter-status').val();
        d.delete = $('#filter-delete').val();
        d.from_date = $('#filter-from-date').val();
        d.to_date = $('#filter-to-date').val();
      }
    },
    columns: [
      {
        data: 'DT_RowIndex',
        name: 'DT_RowIndex',
        orderable: false,
        searchable: false,
        width: '60px'
      },
      {
        data: 'uid',
        name: 'uid'
      },
      {
        data: 'company_name',
        name: 'company_name'
      },
      {
        data: 'mobile_number',
        name: 'mobile_number',
        title: 'Phone'
      },
      {
        data: 'email',
        name: 'email'
      },
      {
        data: 'status',
        name: 'status',
        className: 'text-center',
        width: '70px'
      },
      {
        data: 'created_at',
        name: 'created_at',
        className: 'text-center',
        width: '120px'
      },
      {
        data: 'actions',
        orderable: false,
        searchable: false,
        className: 'text-center',
        width: '70px'
      }
    ],
    initComplete: function () {
      table.buttons().container().appendTo('#export-buttons');
    }
  });
  $('#applyFilter').click(function () {
    table.draw(); // reload with filters
  });
  $('#filter-uid, #filter-user').val('');
  $('#resetFilter').click(function () {
    $('#filter-status, #filter-delete').val('');
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
