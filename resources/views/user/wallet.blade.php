@extends('layouts/layoutMaster')

@section('title', 'Virtual Wallet')
@section('vendor-style')
  @vite(['resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss', 'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss', 'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss', 'resources/assets/vendor/libs/select2/select2.scss', 'resources/assets/vendor/libs/@form-validation/form-validation.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/moment/moment.js', 'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js', 'resources/assets/vendor/libs/select2/select2.js', 'resources/assets/vendor/libs/@form-validation/popular.js', 'resources/assets/vendor/libs/@form-validation/bootstrap5.js', 'resources/assets/vendor/libs/@form-validation/auto-focus.js', 'resources/assets/vendor/libs/cleave-zen/cleave-zen.js'])
@endsection
@section('page-style')
  @vite(['resources/assets/vendor/scss/pages/page-profile.scss'])
  <style>
    .va-hero {
      background: linear-gradient(135deg, rgba(105, 108, 255, .12), rgba(3, 195, 236, .10));
      border: 1px solid rgba(105, 108, 255, .15);
    }

    .soft-card {
      border: 1px solid rgba(0, 0, 0, .06);
      box-shadow: 0 6px 18px rgba(0, 0, 0, .04);
    }

    .kpi {
      border: 1px dashed rgba(0, 0, 0, .12);
      background: rgba(0, 0, 0, .015);
    }

    .upload-drop {
      border: 1px dashed rgba(0, 0, 0, .2);
      background: rgba(0, 0, 0, .02);
      border-radius: .75rem;
      padding: 1rem;
    }

    .img-thumb {
      max-height: 140px;
      border-radius: .75rem;
      border: 1px solid rgba(0, 0, 0, .08);
    }

    .table thead th {
      white-space: nowrap;
    }

    .cursor-pointer {
      cursor: pointer;
    }

    /* .refresh-wallet:hover {
                      transform: rotate(90deg);
                      transition: transform 0.2s ease;
                    } */

    .icon-btn {
      width: 42px;
      height: 42px;
      border-radius: 12px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: 0;
      background: transparent;
      transition: all .15s ease;
    }

    .icon-btn i {
      font-size: 20px;
      line-height: 1;
    }

    .icon-btn-outline {
      border: 1px solid rgba(105, 108, 255, .35);
      color: var(--bs-primary);
      background: rgba(105, 108, 255, .08);
    }

    .icon-btn-outline:hover {
      background: rgba(105, 108, 255, .14);
      transform: translateY(-1px);
    }

    .icon-btn-outline:active {
      transform: translateY(0);
    }

    #vaHistoryTable_wrapper .dt-buttons {
      gap: .5rem !important;
    }
  </style>
@endsection

@section('content')
  @php
    /**
     * Expected variables from controller:
     * $walletBalance (number/string)
     * $virtualAccount (object|null)   // includes: account_number, ifsc, bank_name, holder_name, status, created_at, vpa(optional), etc.
     * $history (collection/array)     // each: created_at, type, amount, reference, status, closing_balance, narration
     */

    $walletBalance = $walletBalance ?? 0;
    $virtualAccount = $virtualAccount ?? null;
    $history = $history ?? [];
    $fmtMoney = function ($val) {
        if ($val === null || $val === '') {
            return '0.00';
        }
        return number_format((float) $val, 2, '.', ',');
    };

    $fmtDate = function ($dt) {
        if (!$dt) {
            return '-';
        }
        try {
            return \Carbon\Carbon::parse($dt)->format('d M Y, h:i A');
        } catch (\Exception $e) {
            return $dt;
        }
    };
  @endphp

  {{-- HERO / HEADER --}}
  <div class="row g-4 mb-4">
    <div class="col-12">
      <div class="card soft-card va-hero">
        <div
          class="card-body d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
          <div>
            <h4 class="mb-1">Virtual Wallet</h4>
            <div class="text-muted">View wallet balance, virtual account details, and credit history.</div>
          </div>
          <div class="d-flex gap-2">
            @if (!$virtualAccount)
              <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createVaModal">
                <i class="icon-base ti tabler-plus me-1"></i> Create Virtual Account
              </button>
            @endif

            <a href="javascript:void(0)" class="btn btn-outline-secondary refresh-wallet"
              data-url="{{ route('virtual.wallet.refresh') }}">
              <i class="icon-base ti tabler-refresh me-1"></i> Refresh
            </a>

          </div>
        </div>
      </div>
    </div>

    {{-- KPI CARDS --}}
    <div class="col-12 col-lg-4">
      <div class="card soft-card h-100">
        <div class="card-body">
          <div class="d-flex flex-column align-items-center justify-content-between">
            <div class="d-flex align-items-center justify-content-between w-100 mb-3">
              <div>
                <div class="d-flex align-items-center">
                  <h6 class="text-muted mb-0">Virtual Balance</h6>
                  <button type="button" class="icon-btn refresh-wallet" data-url="{{ route('virtual.wallet.refresh') }}"
                    title="Refresh balance">
                    <i class="icon-base ti tabler-refresh"></i>
                  </button>
                </div>
                <h3 class="mb-0">
                  ₹ <span class="virtualBalanceText">{{ number_format($walletBalance, 2) }}</span>
                </h3>
              </div>

              <div class="d-flex align-items-center gap-2">
                <!-- Wallet Icon -->
                <div class="avatar avatar-lg">
                  <span class="avatar-initial rounded bg-primary text-white force-primary">
                    <i class="icon-base ti tabler-wallet icon-lg"></i>
                  </span>
                </div>
              </div>
            </div>
            <div class="d-flex align-items-center justify-content-between w-100 mb-3">
              <div>
                <div class="d-flex align-items-center">
                  <h6 class="text-muted mb-2">Payout Balance</h6>
                </div>
                <h3 class="mb-0">
                  ₹ <span class="payoutBalanceText">{{ number_format($payoutBalance, 2) }}</span>
                </h3>
              </div>

              <div class="d-flex align-items-center gap-2">
                <!-- Wallet Icon -->
                <div class="avatar avatar-lg">
                  <span class="avatar-initial rounded bg-primary text-white force-primary">
                    <i class="icon-base ti tabler-arrow-up-right icon-lg"></i>
                  </span>
                </div>
              </div>
            </div>
            <div class="d-flex align-items-center justify-content-between w-100 mb-1">
              <div>
                <div class="d-flex align-items-center">
                  <h6 class="text-muted mb-2">Payin Balance</h6>
                </div>
                <h3 class="mb-0">
                  ₹ <span class="payinBalanceText">{{ number_format($payinBalance, 2) }}</span>
                </h3>
              </div>

              <div class="d-flex align-items-center gap-2">
                <!-- Wallet Icon -->
                <div class="avatar avatar-lg">
                  <span class="avatar-initial rounded bg-primary text-white force-primary">
                    <i class="icon-base ti tabler-arrow-down-left icon-lg"></i>
                  </span>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-3 kpi rounded p-3">
            <div class="d-flex justify-content-between">
              <span class="text-muted">Currency</span>
              <span class="fw-semibold">INR</span>
            </div>
            <div class="d-flex justify-content-between mt-1">
              <span class="text-muted">Type</span>
              <span class="fw-semibold">Virtual Wallet</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Virtual Account Status --}}
    <div class="col-12 col-lg-8">
      <div class="card soft-card h-100">
        <div class="card-body">
          <div class="d-flex align-items-start justify-content-between gap-3">
            <div>
              <h5 class="mb-1">Virtual Account Details</h5>
              <div class="text-muted">
                @if ($virtualAccount)
                  Your virtual account is available for receiving funds.
                @else
                  No virtual account found. Create one to get dedicated bank details.
                @endif
              </div>
            </div>

            @if ($virtualAccount)
              @php
                $status = $virtualAccount->status ?? 'A'; // A / B
                $badge = $status === 'A' ? 'bg-label-success' : 'bg-label-danger';
                $label = $status === 'A' ? 'ACTIVE' : 'BLOCKED';
              @endphp

              <span class="badge {{ $badge }}">{{ $label }}</span>
            @else
              <span class="badge bg-label-danger">NOT CREATED</span>
            @endif
          </div>

          <hr class="my-4">

          @if ($virtualAccount)
            <div class="row g-3">
              <div class="col-12 col-md-6">
                <div class="p-3 rounded kpi">
                  <div class="text-muted">Account Holder</div>
                  <div class="fw-semibold">{{ $virtualAccount->name ?? '-' }}</div>
                </div>
              </div>
              <div class="col-12 col-md-6">
                <div class="p-3 rounded kpi">
                  <div class="text-muted">Bank Name</div>
                  <div class="fw-semibold">{{ $virtualAccount->bank_name ?? '-' }}</div>
                </div>
              </div>

              <div class="col-12 col-md-6">
                <div class="p-3 rounded kpi">
                  <div class="text-muted">Account Number</div>
                  <div class="fw-semibold">{{ $virtualAccount->account_number ?? '-' }}</div>
                </div>
              </div>
              <div class="col-12 col-md-6">
                <div class="p-3 rounded kpi">
                  <div class="text-muted">IFSC</div>
                  <div class="fw-semibold">{{ $virtualAccount->ifsc ?? '-' }}</div>
                </div>
              </div>

              <div class="col-12 col-md-6">
                <div class="p-3 rounded kpi">
                  <div class="text-muted">Created At</div>
                  <div class="fw-semibold">{{ $fmtDate($virtualAccount->created_at ?? null) }}</div>
                </div>
              </div>
              <div class="col-12 col-md-6">
                <div class="p-3 rounded kpi">
                  <div class="text-muted">Virtual ID / VPA (optional)</div>
                  <div class="fw-semibold">{{ $virtualAccount->vpa ?? '-' }}</div>
                </div>
              </div>
            </div>
          @else
            <div
              class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
              <div>
                <div class="fw-semibold">Create Virtual Account</div>
                <div class="text-muted">Add beneficiary details to generate virtual bank details.</div>
              </div>
              <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createVaModal">
                <i class="icon-base ti tabler-plus me-1"></i> Create Now
              </button>
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- HISTORY TABLE --}}
  <div class="card soft-card">
    <div
      class="card-header d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
      <div>
        <h5 class="mb-0">Virtual Load History</h5>
      </div>
    </div>

    <div class="card-body">
      {{-- FILTER BAR --}}
      <div class="row g-3 mb-3">
        <div class="col-12 col-md-3">
          <label class="form-label">Request ID</label>
          <input type="text" class="form-control" id="f_request_id" placeholder="">
        </div>

        <div class="col-12 col-md-2">
          <label class="form-label">Amount</label>
          <input type="text" class="form-control" id="f_amount" step="0.01" placeholder="">
        </div>

        <div class="col-12 col-md-2">
          <label class="form-label">Status</label>
          <select class="form-select" id="f_status">
            <option value="">All</option>
            <option value="A">Approved</option>
            <option value="P">Pending</option>
            <option value="R">Failed</option>
          </select>
        </div>

        <div class="col-12 col-md-2">
          <label class="form-label">Wallet Txn ID</label>
          <input type="text" class="form-control" id="f_wallet_txn_id" placeholder="">
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label">UTR</label>
          <input type="text" class="form-control" id="f_utr" placeholder="">
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label">Sender Account No</label>
          <input type="text" class="form-control" id="f_sender_acc" placeholder="">
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label">Date From</label>
          <input type="date" class="form-control" id="f_date_from">
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label">Date To</label>
          <input type="date" class="form-control" id="f_date_to">
        </div>

        <div class="col-12 col-md-3 d-flex align-items-end gap-2">
          <button class="btn btn-primary w-50" id="btnApplyFilters">
            <i class="icon-base ti tabler-filter me-1"></i> Apply
          </button>
          <button class="btn btn-outline-secondary w-50" id="btnResetFilters">
            <i class="icon-base ti tabler-refresh me-1"></i> Reset
          </button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle" id="vaHistoryTable">
          <thead>
            <tr>
              <th>S.No</th>
              <th>Request ID</th>
              <th>Wallet Txn ID</th>
              <th class="text-end">Amount</th>
              <th>Status</th>
              <th>UTR</th>
              <th>Sender Acc No</th>
              <th>Mode</th>
              <th>Date</th>
              <th>Description</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($history as $row)
              @php
                $s = $row->status ?? '';
                $badge = $s === 'A' ? 'bg-label-success' : ($s === 'P' ? 'bg-label-warning' : 'bg-label-danger');
                $statusText = $s === 'A' ? 'APPROVED' : ($s === 'P' ? 'PENDING' : 'FAILED');
                $created = $row->created_at;
              @endphp
              <tr data-request-id="{{ $row->request_id ?? '' }}" data-wallet-txn-id="{{ $row->wallet_txn_id ?? '' }}"
                data-utr="{{ $row->transaction_utr ?? '' }}" data-sender="{{ $row->sender_account_number ?? '' }}"
                data-amount="{{ $row->amount ?? 0 }}" data-status="{{ $row->status ?? '' }}"
                data-date="{{ $created }}">
                <td>{{ $loop->iteration }}</td>
                <td class="fw-semibold">{{ $row->request_id ?? '-' }}</td>
                <td>{{ $row->wallet_txn_id ?? '-' }}</td>
                <td class="text-end fw-semibold">₹ {{ number_format((float) ($row->amount ?? 0), 2) }}</td>
                <td><span class="badge {{ $badge }}">{{ $statusText }}</span></td>
                <td>{{ $row->transaction_utr ?? '-' }}</td>
                <td>{{ $row->sender_account_number ?? '-' }}</td>
                <td>{{ $row->mode ?? '-' }}</td>
                <td>{{ $fmtDate($row->created_at ?? null) }}</td>
                <td class="text-truncate" style="max-width: 240px;" title="{{ $row->description ?? '' }}">
                  {{ $row->description ?? '-' }}
                </td>
              </tr>
            @endforeach
          </tbody>

        </table>
      </div>
    </div>

  </div>

  {{-- CREATE/UPDATE VA MODAL --}}
  <div class="modal fade" id="createVaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            {{ $virtualAccount ? 'Update Beneficiary Details' : 'Create Virtual Account' }}
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <form id="createVaForm" enctype="multipart/form-data" data-url="{{ route('create.user.vwallet') }}">
          @csrf

          <div class="modal-body">
            <div class="row g-3">
              <div class="col-12 col-md-6">
                <label class="form-label">Bene Name</label>
                <input type="text" name="bene_name" class="form-control" placeholder="Beneficiary Name" required>
                <small class="text-danger" data-err="bene_name"></small>
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label">Bene Bank Account</label>
                <input type="text" name="bene_account" class="form-control" placeholder="Account Number" required>
                <small class="text-danger" data-err="bene_account"></small>
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label">Bene IFSC Code</label>
                <input type="text" name="bene_ifsc" class="form-control" placeholder="e.g. HDFC0001234" required>
                <small class="text-danger" data-err="bene_ifsc"></small>
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label">Bene Email</label>
                <input type="email" name="bene_email" class="form-control" placeholder="email@example.com" required>
                <small class="text-danger" data-err="bene_email"></small>
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label">Bene Mobile</label>
                <input type="text" name="bene_mobile" class="form-control" placeholder="10-digit mobile" required>
                <small class="text-danger" data-err="bene_mobile"></small>
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label">Cancel Cheque / Proof of Bank Account</label>
                <div class="upload-drop">
                  <input type="file" name="bene_proof" class="form-control" id="beneProof" accept="image/*"
                    required>
                  <small class="text-muted d-block mt-2">Upload an image (JPG/PNG/WebP).</small>
                  <small class="text-danger" data-err="bene_proof"></small>

                  <div class="mt-3 d-flex gap-3 align-items-start">
                    <img id="beneProofPreview" class="img-thumb d-none" alt="Preview">
                    <div class="text-muted" id="beneProofMeta"></div>
                  </div>
                </div>
              </div>

              <div class="col-12">
                <div class="alert alert-info mb-0">
                  <div class="fw-semibold mb-1">Note</div>
                  Virtual account details will be shown after successful creation.
                </div>
              </div>

            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="createVaSubmitBtn">
              <span class="spinner-border spinner-border-sm me-2 d-none" id="createVaSpinner"></span>
              Submit
            </button>
          </div>
        </form>

      </div>
    </div>
  </div>
@endsection

@section('page-script')
  <script>
    // Helpers
    function clearErrors(form) {
      form.querySelectorAll('[data-err]').forEach(el => el.textContent = '');
    }

    function setErrors(form, errors) {
      Object.keys(errors || {}).forEach(key => {
        const holder = form.querySelector(`[data-err="${key}"]`);
        if (holder) holder.textContent = Array.isArray(errors[key]) ? errors[key][0] : errors[key];
      });
    }

    // File preview
    const beneProof = document.getElementById('beneProof');
    const previewImg = document.getElementById('beneProofPreview');
    const metaBox = document.getElementById('beneProofMeta');

    if (beneProof) {
      beneProof.addEventListener('change', function() {
        metaBox.textContent = '';
        previewImg.classList.add('d-none');
        previewImg.src = '';

        const file = this.files && this.files[0];
        if (!file) return;

        metaBox.innerHTML = `
          <div class="fw-semibold">${file.name}</div>
          <div class="text-muted">${Math.round(file.size / 1024)} KB</div>
        `;

        const reader = new FileReader();
        reader.onload = e => {
          previewImg.src = e.target.result;
          previewImg.classList.remove('d-none');
        };
        reader.readAsDataURL(file);
      });
    }

    // Create/Update VA submit (AJAX)
    const createVaForm = document.getElementById('createVaForm');
    const spinner = document.getElementById('createVaSpinner');
    const submitBtn = document.getElementById('createVaSubmitBtn');

    if (createVaForm) {
      createVaForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        clearErrors(createVaForm);
        const url = createVaForm.dataset.url;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const fd = new FormData(createVaForm);
        submitBtn.disabled = true;
        spinner.classList.remove('d-none');
        try {
          const res = await fetch(url, {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': csrf,
              'Accept': 'application/json'
            },
            body: fd
          });
          const data = await res.json().catch(() => ({}));
          if (!res.ok) {
            if (data && data.errors) setErrors(createVaForm, data.errors);
            toastr?.error(data.message || 'Validation error. Please check details.');
            return;
          }
          if (!data.success) {
            toastr?.error(data.message || 'Something went wrong');
            return;
          }
          toastr?.success(data.message || 'Virtual account created successfully');
          const modalEl = document.getElementById('createVaModal');
          const modal = bootstrap.Modal.getInstance(modalEl);
          modal?.hide();
          setTimeout(() => window.location.reload(), 600);
        } catch (err) {
          toastr?.error('Server error. Please try again.');
        } finally {
          submitBtn.disabled = false;
          spinner.classList.add('d-none');
        }
      });
    }

    document.addEventListener('DOMContentLoaded', function() {

      if (!(window.jQuery && $.fn.DataTable)) {
        console.warn('DataTables not loaded');
        return;
      }

      const table = $('#vaHistoryTable').DataTable({
        pageLength: 10,
        order: [
          [8, 'desc']
        ],
        dom: '<"d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-3"' +
          '<"d-flex gap-2"B><"ms-auto"f>' +
          '>' +
          'rt' +
          '<"d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 mt-3"ip>',

        buttons: [{
            extend: 'excelHtml5',
            text: '<i class="fa fa-file-excel me-1"></i> Excel',
            className: 'btn bg-primary btn-sm',
            exportOptions: {
              columns: ':not(:last-child)' // ✅ excludes last column
            },
            action: function(e, dt, button, config) {
              var self = this;
              var oldStart = dt.settings()[0]._iDisplayStart;

              dt.one('preXhr', function(e, s, data) {
                data.start = 0;
                data.length = -1; // ✅ request ALL rows from server
              });

              dt.one('preDraw', function(e, settings) {
                $.fn.dataTable.ext.buttons.excelHtml5.action.call(self, e, dt, button, config);

                settings._iDisplayStart = oldStart;
                dt.draw(false);
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

            customize: function(doc) {
              doc.defaultStyle.fontSize = 7;
              doc.styles.tableHeader.fontSize = 8;
              doc.pageMargins = [10, 10, 10, 10];
              doc.content[1].table.widths = Array(doc.content[1].table.body[0].length).fill('*');
            },

            action: function(e, dt, button, config) {
              var self = this;
              var oldStart = dt.settings()[0]._iDisplayStart;

              dt.one('preXhr', function(e, s, data) {
                data.start = 0;
                data.length = -1;
              });

              dt.one('draw', function() {
                $.fn.dataTable.ext.buttons.pdfHtml5.action.call(self, e, dt, button, config);

                setTimeout(function() {
                  dt.page(oldStart / dt.page.len()).draw(false);

                  // 🔥 REMOVE BUTTON LOADING STATE
                  dt.button(button).processing(false);
                }, 100);
              });

              dt.draw();
            }
          }
        ],

        language: {
          emptyTable: 'No transactions found.'
        },

        // ✅ This runs after DataTables builds the DOM (perfect place to fix spacing)
        initComplete: function() {
          const $btnWrap = $('#vaHistoryTable_wrapper .dt-buttons');

          // Remove Bootstrap grouping that forces buttons to touch
          $btnWrap.removeClass('btn-group');

          // Add flex + gap
          $btnWrap.addClass('d-flex gap-2 flex-wrap align-items-center');

          // Optional: if any old btn-group styles still affect buttons, force spacing
          $btnWrap.find('button.btn').addClass('me-0');
        }
      });


      function normalize(s) {
        return (s || '').toString().trim().toLowerCase();
      }

      function applyFilters() {
        const reqId = normalize(document.getElementById('f_request_id').value);
        const amount = normalize(document.getElementById('f_amount').value);
        const status = (document.getElementById('f_status').value || '').trim();
        const walletTxn = normalize(document.getElementById('f_wallet_txn_id').value);
        const utr = normalize(document.getElementById('f_utr').value);
        const sender = normalize(document.getElementById('f_sender_acc').value);
        const dateFrom = (document.getElementById('f_date_from').value || '').trim(); // YYYY-MM-DD
        const dateTo = (document.getElementById('f_date_to').value || '').trim();

        // Custom filtering using row dataset
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
          const row = table.row(dataIndex).node();
          if (!row) return true;

          const rReqId = normalize(row.getAttribute('data-request-id'));
          const rWalletTxn = normalize(row.getAttribute('data-wallet-txn-id'));
          const rUtr = normalize(row.getAttribute('data-utr'));
          const rSender = normalize(row.getAttribute('data-sender'));
          const rAmount = normalize(row.getAttribute('data-amount'));
          const rStatus = (row.getAttribute('data-status') || '').trim();
          const rDate = (row.getAttribute('data-date') || '').trim(); // YYYY-MM-DD

          if (reqId && !rReqId.includes(reqId)) return false;
          if (walletTxn && !rWalletTxn.includes(walletTxn)) return false;
          if (utr && !rUtr.includes(utr)) return false;
          if (sender && !rSender.includes(sender)) return false;

          if (amount && !(rAmount === amount)) return false;

          if (status && rStatus !== status) return false;

          if (dateFrom && rDate && rDate < dateFrom) return false;
          if (dateTo && rDate && rDate > dateTo) return false;

          return true;
        });

        table.draw();

        // remove our filter function so it doesn't stack multiple times
        $.fn.dataTable.ext.search.pop();
      }

      function resetFilters() {
        document.getElementById('f_request_id').value = '';
        document.getElementById('f_amount').value = '';
        document.getElementById('f_status').value = '';
        document.getElementById('f_wallet_txn_id').value = '';
        document.getElementById('f_utr').value = '';
        document.getElementById('f_sender_acc').value = '';
        document.getElementById('f_date_from').value = '';
        document.getElementById('f_date_to').value = '';
        table.search('').columns().search('').draw();
      }

      document.getElementById('btnApplyFilters')?.addEventListener('click', applyFilters);
      document.getElementById('btnResetFilters')?.addEventListener('click', resetFilters);
    });


    // Refresh button (optional endpoint)
    document.querySelectorAll('.refresh-wallet').forEach(btn => {
      btn.addEventListener('click', async function() {

        const url = this.dataset.url;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

        if (!url) {
          toastr?.error('Refresh URL missing');
          return;
        }

        // spinner effect
        const icon = this.querySelector('i');
        icon?.classList.add('ti-spin');

        try {
          const res = await fetch(url, {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': csrf,
              'Accept': 'application/json'
            }
          });

          const data = await res.json();

          if (!res.ok || !data.success) {
            toastr?.error(data.message || 'Unable to refresh balance');
            return;
          }

          // ✅ Update ALL balance texts on page
          // ✅ Update Virtual Balance
          document.querySelector('.virtualBalanceText').textContent = data.data.virtual_balance;
          document.querySelector('.payoutBalanceText').textContent = data.data.payout_balance;
          document.querySelector('.payinBalanceText').textContent = data.data.payin_balance;

          toastr?.success('Wallet balance updated');

        } catch (e) {
          toastr?.error('Server error while refreshing');
        } finally {
          icon?.classList.remove('ti-spin');
        }
      });
    });
  </script>
@endsection
