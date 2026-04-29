$(document).ready(function () {
  const table = $('#txn-table').DataTable({
    processing: true,
    serverSide: true,
    searching: false,
    pageLength: 10,
    scrollX: true,
    order: [[9, 'desc']],
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
      url: window.routes.complaintData,
      data: function (d) {
        d.txn_ref_id = $('#filter-txn-ref').val();
        d.bbps_complaint_id = $('#filter-bbps-id').val();
        d.complaint_type = $('#filter-type').val();
        d.status = $('#filter-status').val();
        d.from_date = $('#filter-from-date').val();
        d.to_date = $('#filter-to-date').val();
      }
    },

    columns: [
      { data: 'DT_RowIndex', orderable: false },
      { data: 'txn_ref_id' },
      { data: 'mobile_number' },
      { data: 'complaint_type' },
      { data: 'biller_id' },
      { data: 'agent_id' },
      { data: 'complaint_desc' },
      { data: 'bbps_complaint_id' },
      { data: 'status', orderable: false },
      { data: 'created_at' },
      { data: 'actions', orderable: false }
    ],
    initComplete: function () {
      table.buttons().container().appendTo('#export-buttons');
    }
  });
  $('#applyFilter').click(function () {
    table.draw(); // reload with filters
  });
  $('#resetFilter').click(function () {
    $('#filter-txn-ref, #filter-bbps-id, #filter-type').val('');
    $('#filter-status, #filter-from-date, #filter-to-date').val('');
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
  $(document).on('click', '.view-complaint', function () {
    let id = $(this).data('id');
  
    $('#txnSidebar').addClass('open');
    $('#sidebarOverlay').addClass('show');
    $('#txnSidebarBody').html('<div class="p-4 text-center">Loading...</div>');
  
    $.get(`${window.routes.viewComplaint}/${id}`, function (data) {
      let html = `
          <div class="container-fluid">
  
              <div class="row mb-2">
                  <div class="col-5 fw-semibold">Txn Ref ID</div>
                  <div class="col-7">${data.txn_ref_id ?? '-'}</div>
              </div>
  
              <div class="row mb-2">
                  <div class="col-5 fw-semibold">Mobile Number</div>
                  <div class="col-7">${data.mobile_number ?? '-'}</div>
              </div>
  
              <div class="row mb-2">
                  <div class="col-5 fw-semibold">Biller ID</div>
                  <div class="col-7">${data.biller_id ?? '-'}</div>
              </div>
  
              <div class="row mb-2">
                  <div class="col-5 fw-semibold">Agent ID</div>
                  <div class="col-7">${data.agent_id ?? '-'}</div>
              </div>
  
              <div class="row mb-2">
                  <div class="col-5 fw-semibold">Complaint Type</div>
                  <div class="col-7">${data.complaint_type ?? '-'}</div>
              </div>
  
              <div class="row mb-2">
                  <div class="col-5 fw-semibold">Participate Type</div>
                  <div class="col-7">${data.participation_type ?? '-'}</div>
              </div>
  
              <div class="row mb-2">
                  <div class="col-5 fw-semibold">Complaint Desc</div>
                  <div class="col-7">${data.complaint_desc ?? '-'}</div>
              </div>
  
              <div class="row mb-2">
                  <div class="col-5 fw-semibold">Reason</div>
                  <div class="col-7">${data.serv_reason ?? '-'}</div>
              </div>
  
              <div class="row mb-2">
                  <div class="col-5 fw-semibold">Complaint Disposition</div>
                  <div class="col-7">${data.complaint_disposition ?? '-'}</div>
              </div>
  
              <div class="row mb-2">
                  <div class="col-5 fw-semibold">BBPS Complaint ID</div>
                  <div class="col-7">${data.bbps_complaint_id ?? '-'}</div>
              </div>
  
              <div class="row mb-2">
                  <div class="col-5 fw-semibold">BBPS Complaint Assigned</div>
                  <div class="col-7">${data.bbps_complaint_assigned ?? '-'}</div>
              </div>
  
              <div class="row mb-2">
                  <div class="col-5 fw-semibold">Response Code</div>
                  <div class="col-7">${data.response_code ?? '-'}</div>
              </div>
  
              <div class="row mb-2">
                  <div class="col-5 fw-semibold">Response Reason</div>
                  <div class="col-7">${data.response_reason ?? '-'}</div>
              </div>
  
              <div class="row mb-2">
                  <div class="col-5 fw-semibold">Error Code</div>
                  <div class="col-7">${data.error_code ?? '-'}</div>
              </div>
  
              <div class="row mb-2">
                  <div class="col-5 fw-semibold">Error</div>
                  <div class="col-7">${data.error_message ?? '-'}</div>
              </div>
  
              <div class="row mb-2">
                  <div class="col-5 fw-semibold">Status</div>
                  <div class="col-7">
                    ${
                      data.status == 'success'
                        ? '<span class="badge bg-success">Success</span>'
                        : data.status == 'failed'
                          ? '<span class="badge bg-danger">Failed</span>'
                          : '<span class="badge bg-warning">Pending</span>'
                    }
                  </div>
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
