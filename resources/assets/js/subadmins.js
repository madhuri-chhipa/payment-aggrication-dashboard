$(function () {
  const table = $('#subadmins-table').DataTable({
    processing: true,
    serverSide: true,
    searching: false,
    paging: true, // keep paging ON (IMPORTANT)
    lengthChange: false,
    pageLength: 10,
    order: [[6, 'desc']],
    dom: 'Brtip',
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
      url: window.routes.data,
      data: function (d) {
        d.name = $('#filter-name').val();
        d.mobile_number = $('#filter-phone').val();
        d.email = $('#filter-email').val();
        d.status = $('#filter-status').val();
        d.from_date = $('#filter-from-date').val();
        d.to_date = $('#filter-to-date').val();
      }
    },
    columns: [
      { data: 'DT_RowIndex', orderable: false },
      { data: 'name' },
      { data: 'email' },
      { data: 'mobile_number' },
      { data: 'admin_type' },
      { data: 'status', orderable: false },
      { data: 'created_at' },
      { data: 'action', orderable: false }
    ],
    initComplete: function () {
      table.buttons().container().appendTo('#export-buttons');
    }
  });
  $('#applyFilter').click(function () {
    table.draw(); // reload with filters
  });
  $('#resetFilter').click(function () {
    $('#filter-name, #filter-phone, #filter-email, #filter-status, #filter-from-date, #filter-to-date').val('');
    table.draw();
  });

  $('#addNew').click(() => {
    $('#subAdminForm')[0].reset();
    $('#admin_id').val('');
    $('#subAdminModal').modal('show');
  });

  $('#save').click(function (e) {
    e.preventDefault();

    let id = $('#admin_id').val();
    let url = id ? `${window.routes.update}/${id}` : window.routes.store;
    let method = id ? 'PUT' : 'POST';

    $.ajax({
      url,
      method,
      data: {
        _token: $('meta[name="csrf-token"]').attr('content'),
        name: $('#name').val(),
        email: $('#email').val(),
        mobile_number: $('#mobile_number').val(),
        password: $('#password').val(),
        password_confirmation: $('#password_confirmation').val(),
        admin_type: $('#admin_type').val()
      },
      success: () => {
        $('#subAdminModal').modal('hide');
        toastr.success('success', 'Subadmin created successfully.');
        table.ajax.reload(null, false);
      },
      error: () => {
        $('#subAdminModal').modal('hide');
        toastr.error('error', 'Something Went Wrong.');
        table.ajax.reload(null, false);
      }
    });
  });

  $(document).on('click', '.edit-subadmin', function () {
    let id = $(this).data('id');
    $.get(`${window.routes.edit}/${id}`, res => {
      $('#admin_id').val(res.id);
      $('#name').val(res.name);
      $('#email').val(res.email);
      $('#mobile_number').val(res.mobile_number);
      $('#admin_type').val(res.admin_type);
      $('#subAdminModal').modal('show');
    });
  });

  $(document).on('click', '.delete-subadmin', function () {
    let id = $(this).data('id');

    Swal.fire({
      title: 'Are you sure?',
      text: 'This subadmin will be moved to trash!',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Yes, delete it',
      cancelButtonText: 'Cancel'
    }).then(result => {
      if (result.isConfirmed) {
        $.ajax({
          url: `${window.routes.delete}/${id}`,
          type: 'DELETE',
          data: {
            _token: $('meta[name="csrf-token"]').attr('content')
          },
          success: function (res) {
            Swal.fire('Deleted!', res.message ?? 'Sub admin deleted successfully', 'success');
            table.ajax.reload(null, false);
            $('#subadmins-table').DataTable().draw(false);
          },
          error: function () {
            Swal.fire('Error!', 'Something went wrong while deleting', 'error');
          }
        });
      }
    });
  });
  $(document).on('change', '.status-toggle', function () {
    let id = $(this).data('id');
    let status = $(this).prop('checked') ? 'A' : 'B';

    $.ajax({
      url: `${window.routes.status}/${id}`,
      type: 'POST',
      data: {
        _token: $('meta[name="csrf-token"]').attr('content'),
        status: status
      },
      success: function (res) {
        toastr.success(status ? 'Sub admin activated successfully' : 'Sub admin deactivated successfully');
      },
      error: function () {
        toastr.error('Something went wrong');
      }
    });
  });
});
