$(function () {
  const table = $('#accounts-table').DataTable({
    processing: true,
    serverSide: true,
    searching: false,
    paging: true, // keep paging ON (IMPORTANT)
    lengthChange: false,
    pageLength: 10,
    order: [[7, 'desc']],
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
        d.bank_name = $('#filter-bank').val();
        d.account_holder_name = $('#filter-holder').val();
        d.account_number = $('#filter-account').val();
        d.status = $('#filter-status').val();
        d.from_date = $('#filter-from-date').val();
        d.to_date = $('#filter-to-date').val();
      }
    },
    columns: [
      { data: 'DT_RowIndex', orderable: false },
      { data: 'bank_name' },
      { data: 'branch_name' },
      { data: 'account_holder_name' },
      { data: 'account_number' },
      { data: 'ifsc' },
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
    $('#filter-bank, #filter-holder, #filter-account, #filter-status, #filter-from-date, #filter-to-date').val('');
    table.draw();
  });
  $('#addNew').click(() => {
    $('#accountForm')[0].reset();
    $('#account_id').val('');
    $('#accountModal').modal('show');
  });

  $('#save').click(function (e) {
    e.preventDefault();

    // Clear old errors
    $('.is-invalid').removeClass('is-invalid');
    $('.invalid-feedback').remove();

    let bankName = $('#bank_name').val().trim();
    let branchName = $('#branch_name').val().trim();
    let holderName = $('#account_holder_name').val().trim();
    let accountNumber = $('#account_number').val().trim();
    let ifsc = $('#ifsc').val().trim().toUpperCase();

    let isValid = true;

    function showError(input, message) {
      input.addClass('is-invalid');
      input.after(`<div class="invalid-feedback">${message}</div>`);
      isValid = false;
    }

    if (!bankName) showError($('#bank_name'), 'Bank name is required');

    if (!branchName) showError($('#branch_name'), 'Branch name is required');

    if (!holderName) showError($('#account_holder_name'), 'Account holder name is required');

    if (!accountNumber) {
      showError($('#account_number'), 'Account number is required');
    } else if (accountNumber.length < 6) {
      showError($('#account_number'), 'Account number looks too short');
    }

    // IFSC format check (Indian IFSC format)
    let ifscRegex = /^[A-Z]{4}0[A-Z0-9]{6}$/;
    if (!ifsc) {
      showError($('#ifsc'), 'IFSC code is required');
    } else if (!ifscRegex.test(ifsc)) {
      showError($('#ifsc'), 'Invalid IFSC format (Example: SBIN0001234)');
    }

    if (!isValid) return;

    // If validation passed → AJAX
    let id = $('#account_id').val();
    let url = id ? `${window.routes.update}/${id}` : window.routes.store;
    let method = id ? 'PUT' : 'POST';

    $.ajax({
      url,
      method,
      data: {
        _token: $('meta[name="csrf-token"]').attr('content'),
        bank_name: bankName,
        branch_name: branchName,
        account_holder_name: holderName,
        account_number: accountNumber,
        ifsc: ifsc,
        status: $('#status').val()
      },
      success: () => {
        $('#accountModal').modal('hide');
        toastr.success('Success', 'Bank account saved successfully.');
        table.ajax.reload(null, false);
        $('#accountForm')[0].reset();
        $('#account_id').val('');
      },
      error: xhr => {
        if (xhr.responseJSON?.errors) {
          Object.entries(xhr.responseJSON.errors).forEach(([key, messages]) => {
            showError($(`#${key}`), messages[0]);
          });
        } else {
          toastr.error('Error', 'Something went wrong.');
        }
      }
    });
  });

  $(document).on('click', '.edit-account', function () {
    let id = $(this).data('id');
    $.get(`${window.routes.edit}/${id}`, res => {
      $('#account_id').val(res.id);
      $('#bank_name').val(res.bank_name);
      $('#branch_name').val(res.branch_name);
      $('#account_holder_name').val(res.account_holder_name);
      $('#account_number').val(res.account_number);
      $('#ifsc').val(res.ifsc);
      $('#accountModal').modal('show');
    });
  });

  $(document).on('click', '.delete-account', function () {
    let id = $(this).data('id');

    Swal.fire({
      title: 'Are you sure?',
      text: 'This account will be moved to trash!',
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
            Swal.fire('Deleted!', res.message ?? 'Company Account deleted successfully', 'success');
            table.ajax.reload(null, false);
            $('#accounts-table').DataTable().draw(false);
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
        toastr.success(status ? 'Company Account activated successfully' : 'Company Account deactivated successfully');
      },
      error: function () {
        toastr.error('Something went wrong');
      }
    });
  });
});