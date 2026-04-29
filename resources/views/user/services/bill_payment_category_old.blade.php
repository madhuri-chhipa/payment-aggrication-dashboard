@extends('layouts/layoutMaster')
@section('title', ucfirst($category))
@section('page-style')
  <style>
    .bbps-receipt-popup {
      width: 980px !important;
      max-width: 980px !important;
      border-radius: 8px !important;
      padding: 10px !important;
    }

    .bbps-receipt-html {
      overflow: visible !important;
      max-height: none !important;
      margin: 0 !important;
      padding: 0 !important;
    }

    .swal2-html-container {
      overflow: visible !important;
      max-height: none !important;
    }

    .swal2-icon {
      display: none !important;
    }
  </style>
@endsection

@section('content')
  <div class="card">
    <div class="card-header d-flex justify-content-between">
      <h5 class="mb-4">{{ ucfirst($category) }} Bill Payment</h5>
      <div class="d-flex">
        <div class="text-center">
          <img src="{{ asset('assets/img/logo/bbpsLogo.png') }}" width="110">
        </div>
        <a href="{{ route('complaint.create', 'CC014110BAAE00054718') }}" class="btn btn-primary ms-3">
          Raise Complaint
        </a>
      </div>
    </div>
    <div class="card-body">
      <div class="row mb-3">
        <div class="col-md-6">
          <div class="row mb-3">
            <div class="col-12">
              <label>Select Biller</label>
              <select class="form-select mt-2" id="biller_id">
                <option value="">Select</option>
                @foreach ($billers as $biller)
                  <option value="{{ $biller->biller_id }}">
                    {{ $biller->biller_name }}
                  </option>
                @endforeach
              </select>
            </div>
          </div>
          <div id="dynamicFields"></div>
        </div>
        <div class="col-md-6">
          <div id="billCardContainer"></div>
        </div>
      </div>
    </div>
  </div>
  <input type="hidden" id="hiddenBillerId" name="biller_id" value="">
  <input type="hidden" id="selectedPlanId" value="">
@endsection
@section('page-script')
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    $(function() {
      const $biller = $('#biller_id');
      const $dynamic = $('#dynamicFields');
      const BILL_FETCH_URL = "{{ route('service.bill-payment.bill-fetch') }}";
      const BILL_QUICKPAY_URL = "{{ route('service.bill.quickpay') }}";
      const BILL_PAY_URL = "{{ route('service.bill.pay') }}";
      window.lastBillFetch = null; // store latest fetch response
      const paymentModeHtml = `
       <div class="mt-3">
          <label class="form-label"><b>Payment Method</b></label>
          <select id="paymentMode" class="form-select">
            <option value="Cash" selected>Cash</option>
            <option value="UPI">UPI</option>
            <option value="Credit Card">Credit Card</option>
            <option value="Debit Card">Debit Card</option>
            <option value="Internet Banking">Internet Banking</option>
          </select>
        </div>
        <div id="dynamicPaymentFields" class="mt-2"></div>
      `;

      function loading() {
        $dynamic.html(`
          <div class="row">
            <div class="col-12"><p>Loading...</p></div>
          </div>
        `);
      }

      function errorBox(msg) {
        $dynamic.html(`
          <div class="row">
            <div class="col-12">
              <p class="text-danger">${msg}</p>
            </div>
          </div>
        `);
      }

      function ensureCustomerMobileField() {
        return `
          <div class="row mb-3">
            <div class="col-12">
              <label>Customer Mobile</label>
              <input type="text"
               id="customerMobile"
                name="customer[mobile]"
                maxlength="10"
                class="form-control"
                required
                placeholder="Enter mobile number">
                <label>Customer Name</label>
              <input type="text"
               id="customerName"
                name="customer[name]"
                maxlength="10"
                class="form-control"
                required
                placeholder="Enter Name">
            </div>
          </div>
        `;
      }

      function ensureAmountField(paymentModes) {
        const modes = paymentModes?.paymentModeInfo || [];
        let optionsHtml = modes.map(mode => {
          return `<option value="${mode.paymentMode}">
              ${mode.paymentMode}
            </option>`;
        }).join('');
        return `
        <div class="row mb-3">
          <div class="col-12">
            <label>Amount</label>
            <input type="number" id="payAmount" class="form-control" min="1" step="1" required>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-12">
            <label>Payment Mode</label>
            <select id="paymentMode" class="form-control" required>
              <option value="">Select Payment Mode</option>
              ${optionsHtml}
            </select>
          </div>
        </div>

        <div id="dynamicPaymentFields"></div>
      `;
      }

      function renderPayButton(billerId, isPlan) {
        return `
        <div class="row mt-2">
          <div class="col-12">
            <button type="button" id="btnQuickPayBill" class="btn btn-success" data-is-plan="${isPlan}" data-biller-id="${escapeHtml(billerId)}">
              Pay Bill
            </button>
          </div>
        </div>
        <div id="billPayResult" class="mt-3"></div>
      `;
      }

      function renderPayButtonPlan(billerId, isPlan) {
        return `
        <div class="row mt-2">
          <div class="col-12">
            <button type="button" id="btnPayBill" class="btn btn-success" data-is-plan="${isPlan}" data-biller-id="${escapeHtml(billerId)}">
              Pay Bill
            </button>
          </div>
        </div>
        <div id="billPayResult" class="mt-3"></div>
      `;
      }
      $(document).on('change', '#paymentMode', function() {
        const mode = $(this).val();
        const $container = $('#dynamicPaymentFields');
        $container.empty();

        if (!mode) return;

        if (mode === 'UPI') {
          $container.append(`
          <div class="row mb-3">
            <div class="col-12">
              <label>UPI ID</label>
              <input type="text" id="upiId" class="form-control" placeholder="example@upi" required>
            </div>
          </div>
        `);
        }

        if (mode === 'Credit Card' || mode === 'Debit Card') {
          $container.append(`
      <div class="row mb-3">
        <div class="col-12">
          <label>Card Number</label>
          <input type="text" id="cardNumber" class="form-control" maxlength="16" required>
        </div>
      </div>
      <div class="row mb-3">
        <div class="col-6">
          <label>Expiry</label>
          <input type="text" id="cardExpiry" class="form-control" placeholder="MM/YY" required>
        </div>
        <div class="col-6">
          <label>CVV</label>
          <input type="password" id="cardCvv" class="form-control" maxlength="3" required>
        </div>
      </div>
    `);
        }

        if (mode === 'Internet Banking') {
          $container.append(`
      <div class="row mb-3">
        <div class="col-12">
          <label>Bank Name</label>
          <input type="text" id="bankName" class="form-control" required>
        </div>
      </div>
    `);
        }

        if (mode === 'Cash') {
          // No extra fields
        }
      });


      function renderFields(res) {
        console.log(res);
        $dynamic.empty();
        $dynamic.append(ensureCustomerMobileField());
        (res.fields || []).forEach(function(field) {
          if (field.visibility === "N") return;

          const required = field.mandatory === "Y" ? "required" : "";
          const maxLength = field.maxLength ?? "";

          $dynamic.append(`
            <div class="row mb-3">
              <div class="col-12">
                <label>${field.paramName}</label>
                <input type="text"
                  name="inputs[${field.paramName}]"
                  data-name="${field.paramName}"
                  maxlength="${maxLength}"
                  class="form-control biller-param"
                  ${required}>
              </div>
            </div>
          `);
        });
        if (String(res.is_plan_mdm_require) === "1") {
          $dynamic.append('');
        }
        if (String(res.is_validation_api) === "1") {
          $dynamic.append('');
        } else if (String(res.is_fetch_api) !== "1") {
          $dynamic.append(ensureAmountField(res.payment_method));
        }
      }

      function renderButtons(res, billerId) {
        $('#actionButtons').empty();
        let buttons = `
          <div class="row">
            <div class="mt-3 col-12 d-flex gap-2 flex-wrap">
        `;

        if (res.is_fetch_api === "1") {
          buttons += `
            <button type="button"
              class="btn btn-primary"
              id="btnFetchBill"
              data-biller-id="${billerId}">
              Fetch Bill
            </button>
          `;
        } else if (res.is_validation_api === "1" || res.is_plan_mdm_require === "1") {
          buttons += '';
        } else {
          buttons += renderPayButton(billerId, isPlan = "0");
        }

        if (res.is_validation_api === "1") {
          buttons += `
            <button type="button"
              class="btn btn-warning"
              id="btnValidateBill"
              data-biller-id="${billerId}">
              Validate Bill
            </button>
          `;
        }

        if (res.is_plan_mdm_require === "1") {
          buttons += `
            <button type="button"
              class="btn btn-info"
              id="btnViewPlans"
              data-biller-id="${billerId}"
              data-plans="${res.is_plan_mdm_require}">
              View Plans
            </button>
          `;
        }

        buttons += `
          </div>
          </div>
        `;

        $dynamic.append(buttons);
      }

      function escapeHtml(str) {
        return String(str)
          .replaceAll('&', '&amp;')
          .replaceAll('<', '&lt;')
          .replaceAll('>', '&gt;')
          .replaceAll('"', '&quot;')
          .replaceAll("'", '&#039;');
      }

      function buildBillFetchPayload(billerId) {
        const payloadArr = [];
        // billerId
        payloadArr.push({
          name: 'biller_id',
          value: billerId
        });

        const mobile = $dynamic.find('input[name="customer[mobile]"]').val() || '';
        payloadArr.push({
          name: 'customer[mobile]',
          value: mobile
        });

        const email = $('input[name="customer[email]"]').val();
        const aadhaar = $('input[name="customer[aadhaar]"]').val();
        const pan = $('input[name="customer[pan]"]').val();

        if (email !== undefined) payloadArr.push({
          name: 'customer[email]',
          value: email
        });
        if (aadhaar !== undefined) payloadArr.push({
          name: 'customer[aadhaar]',
          value: aadhaar
        });
        if (pan !== undefined) payloadArr.push({
          name: 'customer[pan]',
          value: pan
        });

        // device fields (optional)
        const ip = $('input[name="device[ip]"]').val();
        const mac = $('input[name="device[mac]"]').val();
        const initChannel = $('input[name="device[initChannel]"]').val();

        if (ip !== undefined) payloadArr.push({
          name: 'device[ip]',
          value: ip
        });
        if (mac !== undefined) payloadArr.push({
          name: 'device[mac]',
          value: mac
        });
        if (initChannel !== undefined) payloadArr.push({
          name: 'device[initChannel]',
          value: initChannel
        });
        $dynamic.find(':input[name^="inputs["]').each(function() {
          payloadArr.push({
            name: $(this).attr('name'),
            value: $(this).val()
          });
        });

        return $.param(payloadArr);
      }
      $biller.on('change', function() {
        const billerId = $(this).val();
        if (!billerId) return;

        loading();
        $.get('/service/bill-payment/biller-info/' + billerId, function(res) {
          if (res.error) {
            errorBox(res.error);
            return;
          }
          renderFields(res);
          renderButtons(res, billerId);
          $('#hiddenBillerId').val(billerId);
          console.log("Loaded from: " + res.source);
        }).fail(function() {
          errorBox('Something went wrong');
        });
      });

      function buildBillValidationPayload(billerId) {
        const payloadArr = [];
        let paramIndex = 0;

        payloadArr.push({
          name: 'biller_id',
          value: billerId
        });

        const ip = $('input[name="device[ip]"]').val();
        const mac = $('input[name="device[mac]"]').val();
        const initChannel = $('input[name="device[initChannel]"]').val();

        if (ip !== undefined) {
          payloadArr.push({
            name: 'device[ip]',
            value: ip
          });
        }

        if (mac !== undefined) {
          payloadArr.push({
            name: 'device[mac]',
            value: mac
          });
        }

        if (initChannel !== undefined) {
          payloadArr.push({
            name: 'device[initChannel]',
            value: initChannel
          });
        }

        // collect all dynamic params
        $dynamic.find('.biller-param').each(function() {

          const paramName = ($(this).data('name') || '').trim();
          const paramValue = ($(this).val() || '').trim();

          if (!paramName || !paramValue) return;

          payloadArr.push({
            name: `params[${paramIndex}][name]`,
            value: paramName
          });

          payloadArr.push({
            name: `params[${paramIndex}][value]`,
            value: paramValue
          });

          paramIndex++;
        });

        // ALWAYS push plan Id param
        const selectedPlanId = ($('#selectedPlanId').val() || '').trim();

        payloadArr.push({
          name: `params[${paramIndex}][name]`,
          value: 'Id'
        });

        payloadArr.push({
          name: `params[${paramIndex}][value]`,
          value: selectedPlanId
        });

        console.log('Selected Plan ID =>', selectedPlanId);
        console.log('Validation payload =>', payloadArr);

        return $.param(payloadArr);
      }
      $(document).on('click', '#btnValidateBill', function() {
        const billerId = $(this).data('biller-id');
        const $btn = $(this);

        if (!billerId) return;

        const data = buildBillValidationPayload(billerId);

        $btn.prop('disabled', true).text('Validating...');

        $.ajax({
            url: `/service/bill-payment/validate/${billerId}`,
            type: 'POST',
            data: data,
            headers: {
              'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
          })
          .done(function(res) {
            if (res.responseCode === "000") {
              Swal.fire({
                icon: 'success',
                title: 'Validation Successful',
                text: res.responseReason || 'Validation Successful'
              });
              console.log('Validation response =>', res);
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Validation Failed',
                text: res.responseReason || 'Validation failed'
              });
            }
          })
          .fail(function(xhr) {
            Swal.fire({
              icon: 'error',
              title: 'Validation Failed',
              text: xhr.responseJSON?.error || 'Validation failed'
            });
          })
          .always(function() {
            $btn.prop('disabled', false).text('Validate Bill');
          });
      });
      $(document).on('click', '#btnViewPlans', function() {
        const isPlan = $(this).data('plans');
        const billerId = $(this).data('biller-id');
        const $btn = $(this);
        const $result = $('#billCardContainer');

        if (!billerId) return;

        $btn.prop('disabled', true).text('Loading Plans...');
        $result.html('<p>Loading plans...</p>');

        $.ajax({
            url: `/service/bill-payment/view-plans/${billerId}`,
            type: 'GET'
          })
          .done(function(res) {

            if (!res || !res.plans) {
              $result.html('<p class="text-danger">Plans not found</p>');
              return;
            }

            window.mobilePlans = res.plans;

            $result.html(renderPlansCard(res.plans, billerId, isPlan));
            $btn.prop('disabled', true).text('plans loaded');
            $btn.hide();
          })
          .fail(function(xhr) {

            const msg = xhr.responseJSON?.error || 'Failed to load plans';
            $result.html(`<p class="text-danger">${msg}</p>`);

          })
      });
      $(document).on('change', '#planSelect', function() {
        const $selected = $(this).find(':selected');
        const amount = $selected.data('amount');
        const planId = $selected.val();

        $('#planAmount').val(amount || '');
        $('#selectedPlanId').val(planId || '');
      });
      $(document).on('click', '#btnFetchBill', function() {
        const billerId = $(this).data('biller-id');
        if (!billerId) return;

        const $btn = $(this);
        const $result = $('#billCardContainer');

        const data = buildBillFetchPayload(billerId);

        $btn.prop('disabled', true).text('Fetching...');
        $result.html('<p>Fetching bill...</p>');

        $.ajax({
            url: BILL_FETCH_URL,
            type: 'POST',
            data: data,
            headers: {
              'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
          })
          .done(function(res) {
            if (!res || res.success !== true) {
              const msg = res?.message || 'Bill Fetch Failed';
              $result.html(`<p class="text-danger">${escapeHtml(msg)}</p>`);
              return;
            }

            const requestId = res.data?.requestId;
            const bbps = res.data?.bbps;

            if (!bbps) {
              $result.html(`<p class="text-danger">Invalid response: missing bbps</p>`);
              return;
            }

            if (bbps.responseCode !== '000') {
              const err = bbps?.errorInfo?.error?.[0]?.errorMessage || 'Bill Fetch Failed';
              $result.html(`<p class="text-danger">${escapeHtml(err)}</p>`);
              return;
            }

            window.lastBillFetch = {
              requestId,
              bbps,
              billerId: billerId
            };

            $result.html(renderBillCard(bbps, requestId));
            $('#btnFetchBill').hide();
          })
          .fail(function(xhr) {
            const msg = xhr.responseJSON?.message || xhr.responseText || 'Bill Fetch Failed';
            $result.html(`<p class="text-danger">${escapeHtml(msg)}</p>`);
          })
          .always(function() {
            $btn.prop('disabled', false).text('Fetch Bill');
          });
      });

      // Build nice UI from billerResponse
      function renderBillCard(bbps, requestId) {
        const br = bbps.billerResponse || {};
        const opts = br.amountOptions?.option || [];
        const addl = bbps.additionalInfo?.info || [];
        const inputs = bbps.inputParams?.input || [];

        const billAmount = parseFloat(br.billAmount || '0') || 0;

        // Sum of extra charges/options
        const optionsTotal = opts.reduce((sum, o) => {
          const v = parseFloat(o.amountValue || '0') || 0;
          return sum + v;
        }, 0);

        // Total payable
        const payableTotal = billAmount + optionsTotal;

        const dueDate = br.dueDate || '';
        const billDate = br.billDate || '';
        const customerName = br.customerName || '';
        const billNumber = br.billNumber || '';
        const billPeriod = br.billPeriod || '';

        const addlHtml = addl.length ?
          `<ul class="mb-0">${addl.map(x => `<li>${escapeHtml(x.infoName)}: ${escapeHtml(x.infoValue)}</li>`).join('')}</ul>` :
          '<small class="text-muted">No additional info</small>';

        const amountBreakupHtml = `
<table class="table table-sm mt-2">
  <thead>
    <tr>
      <th>CHARGE</th>
      <th class="text-end">AMOUNT (₹)</th>
    </tr>
  </thead>
  <tbody>

    <tr>
      <td><b>Bill Amount</b></td>
      <td class="text-end">
        <b>₹ ${escapeHtml((billAmount / 100).toFixed(2))}</b>
      </td>
    </tr>

    ${opts.map(o => `
                      <tr>
                        <td>${escapeHtml(o.amountName || '')}</td>
                        <td class="text-end">
                          ₹ ${escapeHtml((Number(o.amountValue || 0) / 100).toFixed(2))}
                        </td>
                      </tr>
                    `).join('')}

    <tr>
      <td class="text-end"><b>Total Payable</b></td>
      <td class="text-end">
        <b>₹ ${escapeHtml((payableTotal / 100).toFixed(2))}</b>
      </td>
    </tr>

  </tbody>
</table>
`;
        return `
        <div class="card">
          <div class="card-body">
            <h5 class="card-title mb-3">Bill Details</h5>

            ${requestId ? `<div><small class="text-muted">Request Id: ${escapeHtml(requestId)}</small></div>` : ''}

            <div class="row g-2 mt-2">
              <div class="col-md-6"><b>Customer Name:</b> ${escapeHtml(customerName)}</div>
              <div class="col-md-6"><b>Bill Number:</b> ${escapeHtml(billNumber)}</div>
              <div class="col-md-6"><b>Bill Date:</b> ${escapeHtml(billDate)}</div>
              <div class="col-md-6"><b>Due Date:</b> ${escapeHtml(dueDate)}</div>
              <div class="col-md-6"><b>Bill Period:</b> ${escapeHtml(billPeriod)}</div>
            </div>

            <hr>

            <div class="mb-2"><b>Amount Details(₹)</b>${amountBreakupHtml}</div>

            <hr>

            <div class="mb-2 mt-3"><b>Additional Info</b>${addlHtml}</div>

            <hr>
            ${paymentModeHtml}
          <div class="mt-3 d-flex gap-2">
              <button id="btnPayBill" class="btn btn-success">Pay ₹ ${escapeHtml((payableTotal / 100).toFixed(2))}</button>
            </div>

            <div id="billPayResult" class="mt-3"></div>
          </div>
        </div>`;
      }

      function renderPlansCard(plans, billerId, isPlan) {
        let options = '<option value="">Select Plan</option>';
        plans.forEach(plan => {
          options += `
            <option value="${plan.plan_id}" data-amount="${plan.amount}">
                ₹${plan.amount} - ${escapeHtml(plan.plan_desc)}
            </option>
          `;
        });
        return `
          <div class="card mt-3">
            <div class="card-body">
              <div class="mb-3">
                  <label class="form-label">Select Plan</label>
                  <select class="form-control" id="planSelect">
                      ${options}
                  </select>
              </div>
              <div class="mb-3">
                  <label class="form-label">Amount</label>
                  <input type="text" id="planAmount" class="form-control" readonly>
              </div>
              ${paymentModeHtml}
              ${renderPayButtonPlan(billerId, isPlan)}
              </div>
          </div>
        `;
      }

      function buildPaymentInfoArrayFromUI() {
        const mode = $('#paymentMode').val() || 'Cash';
        const info = [];

        if (mode === 'UPI') {
          info.push({
            infoName: 'VPA',
            infoValue: ($('#upiId').val() || '').trim()
          });
        }

        if (mode === 'Credit Card' || mode === 'Debit Card') {
          info.push({
            infoName: 'CardNum',
            infoValue: ($('#cardNumber').val() || '').trim()
          });
          info.push({
            infoName: 'Expiry',
            infoValue: ($('#cardExpiry').val() || '').trim()
          });
          info.push({
            infoName: 'CVV',
            infoValue: ($('#cardCvv').val() || '').trim()
          });
        }

        if (mode === 'Internet Banking') {
          info.push({
            infoName: 'BankName',
            infoValue: ($('#bankName').val() || '').trim()
          });
        }

        return {
          mode,
          info
        };
      }

      function validatePaymentFields(mode, infoArray) {
        // Returns { ok: true } or { ok: false, message: '...' }
        const get = (name) => (infoArray.find(x => x.infoName === name)?.infoValue || '').trim();

        if (mode === 'UPI') {
          const vpa = get('VPA');
          if (!vpa) return {
            ok: false,
            message: 'Please enter UPI ID'
          };
          // lightweight validation
          if (!vpa.includes('@')) return {
            ok: false,
            message: 'Invalid UPI ID format'
          };
        }

        if (mode === 'Credit Card' || mode === 'Debit Card') {
          const card = get('CardNum').replace(/\s+/g, '');
          const exp = get('Expiry');
          const cvv = get('CVV');

          if (!card) return {
            ok: false,
            message: 'Please enter Card Number'
          };
          if (card.length < 12) return {
            ok: false,
            message: 'Invalid Card Number'
          };
          if (!exp) return {
            ok: false,
            message: 'Please enter Expiry (MM/YY)'
          };
          if (!/^\d{2}\/\d{2}$/.test(exp)) return {
            ok: false,
            message: 'Expiry must be MM/YY'
          };
          if (!cvv) return {
            ok: false,
            message: 'Please enter CVV'
          };
          if (!/^\d{3,4}$/.test(cvv)) return {
            ok: false,
            message: 'Invalid CVV'
          };
        }

        if (mode === 'Internet Banking') {
          const bank = get('BankName');
          if (!bank) return {
            ok: false,
            message: 'Please enter Bank Name'
          };
        }

        return {
          ok: true
        };
      }

      function buildBillPayPayload(fetchObj) {
        const bbps = fetchObj?.bbps || {};
        const br = bbps?.billerResponse || {};

        const opts = Array.isArray(br?.amountOptions?.option) ?
          br.amountOptions.option : [];

        const billAmount = Number(br.billAmount || 0) || 0;

        const optionsTotal = opts.reduce((sum, o) => {
          const v = Number(o?.amountValue || 0) || 0;
          return sum + v;
        }, 0);

        const payable = billAmount + optionsTotal;
        const fetchRequestId = (fetchObj?.requestId || '').trim();

        const {
          mode,
          info: uiPaymentInfo
        } = buildPaymentInfoArrayFromUI();

        const biller_id =
          ($('#hiddenBillerId').val() || '').trim() ||
          (fetchObj?.billerId || '').trim() ||
          (bbps?.billerId || '').trim();

        if (!biller_id) {
          console.error('Missing biller_id in payload', {
            hidden: $('#hiddenBillerId').val(),
            fetchObj,
            bbps
          });
        }

        const customerMobile = ($('#customerMobile').val() || '').trim();

        // convert fetch inputs to simple key-value object
        const inputMap = {};
        (bbps.inputParams?.input || []).forEach(x => {
          const name = (x?.paramName || '').trim();
          const value = (x?.paramValue || '').trim();
          if (name) {
            inputMap[name] = value;
          }
        });

        // IMPORTANT: send same billerResponse as fetch, but normalize numeric fields
        const billerResponse = JSON.parse(JSON.stringify(br || {}));

        if (billerResponse.billAmount !== undefined && billerResponse.billAmount !== '') {
          billerResponse.billAmount = Number(billerResponse.billAmount);
        }

        if (
          billerResponse.amountOptions &&
          Array.isArray(billerResponse.amountOptions.option)
        ) {
          billerResponse.amountOptions.option = billerResponse.amountOptions.option.map(o => ({
            amountName: String(o.amountName || '').trim(),
            amountValue: Number(o.amountValue || 0)
          }));
        }

        // clean additionalInfo
        const additionalInfo = Array.isArray(bbps.additionalInfo?.info) ?
          bbps.additionalInfo.info
          .filter(x => x && x.infoName)
          .map(x => ({
            infoName: String(x.infoName || '').trim(),
            infoValue: String(x.infoValue || '').trim()
          })) : [];

        // if no extra payment info from UI, send default for Cash
        let paymentInfoFinal = uiPaymentInfo || [];
        if (!paymentInfoFinal.length) {
          paymentInfoFinal = [{
            infoName: 'Payment Account Info',
            infoValue: mode || 'Cash'
          }];
        }

        return {
          biller_id: biller_id,
          fetchRequestId: fetchRequestId,

          inputs: inputMap,

          billerResponse: billerResponse,

          additionalInfo: additionalInfo,

          customer: {
            mobile: customerMobile,
            name: String(br.customerName || '').trim(),
            email: String(fetchObj?.customer?.email || '').trim()
          },

          amount: Number(payable),

          paymentMethod: {
            paymentMode: mode || 'Cash',
            quickPay: 'N',
            splitPay: 'N'
          },

          paymentInfo: {
            info: paymentInfoFinal
          }
        };
      }
      //Quick Pay
      $(document).on('click', '#btnQuickPayBill', function() {
        const billerId = $(this).data('biller-id');
        const isPlan = $(this).data('is-plan');
        const $out = $('#billPayResult');
        const customerMobile = $('#customerMobile').val()?.trim() || '';
        const customerEmail = $('#customerEmail').val()?.trim() || '';
        const customerName = $('#customerName').val()?.trim() || '';
        const amount = $('#payAmount').val()?.trim() || $('#planAmount').val()?.trim() || '';
        const planId = $('#planSelect').val()?.trim() || '';

        if (isPlan == "1") {
          if (!planId) {
            $out.html(`<p class="text-danger">Plan is required</p>`);
            return;
          }
        }
        if (!customerMobile) {
          $out.html(`<p class="text-danger">Customer mobile is required</p>`);
          return;
        }
        if (!amount || Number(amount) <= 0) {
          $out.html(`<p class="text-danger">Valid amount is required</p>`);
          return;
        }
        const mode = $('#paymentMode').val();

        let paymentInfoArray = [];

        if (mode === 'UPI') {
          paymentInfoArray.push({
            infoName: 'VPA',
            infoValue: $('#upiId').val()
          });
        }

        if (mode === 'Credit Card' || mode === 'Debit Card') {
          paymentInfoArray.push({
            infoName: 'CardNum',
            infoValue: $('#cardNumber').val()
          });
          paymentInfoArray.push({
            infoName: 'Expiry',
            infoValue: $('#cardExpiry').val()
          });
          paymentInfoArray.push({
            infoName: 'CVV',
            infoValue: $('#cardCvv').val()
          });
        }

        if (mode === 'Internet Banking') {
          paymentInfoArray.push({
            infoName: 'BankName',
            infoValue: $('#bankName').val()
          });
        }
        // collect biller inputs: inputs[ParamName] fields
        const inputs = {};
        $('[name^="inputs["]').each(function() {
          const nameAttr = $(this).attr('name'); // inputs[Consumer Number]
          const key = nameAttr.substring(7, nameAttr.length - 1); // inside []
          inputs[key] = $(this).val()?.trim() || '';
        });

        const payload = {
          biller_id: billerId,
          inputs: inputs,
          customer: {
            mobile: customerMobile,
            email: customerEmail,
            name: customerName
          },
          amount: amount,
          paymentMethod: {
            paymentMode: mode,
            quickPay: 'Y',
            splitPay: 'N'
          },
          paymentInfo: {
            info: paymentInfoArray
          },
          plan_id: planId,
          is_plan: isPlan
        };

        $('#btnPayBill').prop('disabled', true).text('Paying...');
        $out.html('<p>Processing payment...</p>');

        $.ajax({
            url: BILL_QUICKPAY_URL, // set this
            type: 'POST',
            data: payload,
            headers: {
              'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
          })
          .done(function(res) {
            if (!res || res.success !== true) {
              $out.html(`<p class="text-danger">${escapeHtml(res?.message || 'Payment failed')}</p>`);
              return;
            }

            const data = res?.data || {};
            const receipt = data?.receipt || {};
            const status = data?.status || 'FAILED';

            const swalIcon =
              status === 'SUCCESS' ? 'success' :
              status === 'PENDING' ? 'warning' : 'error';

            const logoUrl = '/assets/img/logo/bassured.png?v=312213765';
            const audio = new Audio('/assets/audio/BharatConnect.mp3');

            const html = `
  <div id="receiptPrintArea" style="font-family:Arial, sans-serif; background:#fff; width:100%; max-width:980px; margin:0 auto; color:#4b5563;">

    <div style="position:relative; padding:18px 22px 10px 22px; min-height:110px;">
      <div style="text-align:center;">
        <div style="font-size:18px; font-weight:700; color:#3f95c5; margin-top:28px;">
          Bharat Connect - Payment Confirmation
        </div>
        <div style="margin-top:10px; font-size:13px; line-height:1.6; color:#4f5f73; font-weight:600; text-align:left; max-width:620px;">
          Thank You. We have received your payment request. Please quote your
          Transaction Reference ID for any queries for the below payment.
        </div>
      </div>

      <div style="position:absolute; top:8px; left:50%; transform:translateX(-50%); font-size:13px; font-weight:700; color:#e66a3d;">
        ${escapeHtml(receipt.payment_mode || '')}
      </div>

      <div style="position:absolute; top:6px; right:10px;">
        <img src="${logoUrl}" alt="Assured" style="height:82px; object-fit:contain;">
      </div>
    </div>

    <div style="padding:0 22px 18px 22px;">
      <table style="width:100%; border-collapse:collapse; font-size:13px; table-layout:fixed;">
        <thead>
          <tr>
            <th colspan="2" style="background:#d8edf7; color:#4b5f6f; border:1px solid #c9dbe4; padding:8px 10px; text-align:left; font-size:13px;">
              Transaction Details
            </th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td style="width:40%; border:1px solid #e5e7eb; padding:7px 10px; color:#617184; font-weight:600;">Name of the biller</td>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#394150; font-weight:600;">${escapeHtml(receipt.biller_name || '')}</td>
          </tr>
          <tr>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#617184; font-weight:600;">Mobile number</td>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#394150; font-weight:600;">${escapeHtml(receipt.mobile || '')}</td>
          </tr>
          <tr>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#617184; font-weight:600;">Bill Number</td>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#394150; font-weight:600;">${escapeHtml(receipt.bill_number || '')}</td>
          </tr>
          <tr>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#617184; font-weight:600;">Bill Date (dd/mm/yyyy)</td>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#394150; font-weight:600;">${escapeHtml(receipt.bill_date || '')}</td>
          </tr>
          <tr>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#617184; font-weight:600;">Bill Due Date (dd/mm/yyyy)</td>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#394150; font-weight:600;">${escapeHtml(receipt.due_date || '')}</td>
          </tr>
          <tr>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#617184; font-weight:600;">B-Connect Txn ID</td>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#394150; font-weight:600;">${escapeHtml(receipt.bbps_txn_ref_id || '')}</td>
          </tr>
          <tr>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#617184; font-weight:600;">Registered Mobile Number</td>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#394150; font-weight:600;">${escapeHtml(receipt.mobile || '')}</td>
          </tr>
          <tr>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#617184; font-weight:600;">Payment Mode</td>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#394150; font-weight:600;">${escapeHtml(receipt.payment_mode || '')}</td>
          </tr>
          <tr>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#617184; font-weight:600;">Payment Channel</td>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#394150; font-weight:600;">${escapeHtml(receipt.payment_channel || '')}</td>
          </tr>
          <tr>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#617184; font-weight:600;">Bill Amount</td>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#394150; font-weight:600;">${escapeHtml(receipt.bill_amount || '0.00')}</td>
          </tr>
          <tr>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#617184; font-weight:600;">Customer Convenience Fee</td>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#394150; font-weight:600;">${escapeHtml(receipt.cust_conv_fee || '0.00')}</td>
          </tr>
          <tr>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#617184; font-weight:600;">Total Amount</td>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#394150; font-weight:600;">${escapeHtml(receipt.total_amount || '0.00')}</td>
          </tr>
          <tr>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#617184; font-weight:600;">Transaction Date and Time</td>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#394150; font-weight:600;">${escapeHtml(receipt.transaction_datetime || '')}</td>
          </tr>
          <tr>
            <td style="border:1px solid #d1d5db; padding:7px 10px; color:#617184; font-weight:700;">Status</td>
            <td style="border:1px solid #d1d5db; padding:7px 10px; color:#394150; font-weight:700;">
              ${escapeHtml(receipt.status || status)}
            </td>
          </tr>
        </tbody>
      </table>

      <div style="display:flex; justify-content:space-between; align-items:center; margin-top:14px; font-size:13px;">
        <a href="javascript:void(0)" onclick="printReceipt('receiptPrintArea')" style="color:#7b89b4; text-decoration:underline; font-weight:600;">
          Print This Page
        </a>
        <a href="javascript:void(0)" onclick="window.location.reload()" style="color:#7b89b4; text-decoration:underline; font-weight:600;">
          Make Another Payment
        </a>
      </div>
    </div>
  </div>
  `;

            $out.html('');

            Swal.fire({
              icon: swalIcon,
              title: status === 'SUCCESS' ? 'Payment Successful' : (status === 'PENDING' ?
                'Payment Pending' : 'Payment Failed'),
              html: html,
              width: 980,
              padding: '0.8rem',
              confirmButtonText: 'OK',
              showDenyButton: true,
              denyButtonText: 'Print',
              allowOutsideClick: false,
              didOpen: () => {
                audio.currentTime = 0;
                audio.play().catch(() => {});
              },
              customClass: {
                popup: 'bbps-receipt-popup'
              }
            }).then((result) => {
              if (result.isDenied) {
                printReceipt('receiptPrintArea');
                return false;
              }

              if (result.isConfirmed) {
                window.location.reload();
              }
            });
          })
          .fail(function(xhr) {
            const msg = xhr.responseJSON?.message || xhr.responseText || 'Payment failed';
            $out.html(`<p class="text-danger">${escapeHtml(msg)}</p>`);
          })
          .always(function() {
            $('#btnPayBill').prop('disabled', false).text('Pay Bill');
          });
      });


      //Fetch Pay
      $(document).on('click', '#btnPayBill', function(e) {
        e.preventDefault();
        console.log('hit');
        if (!window.lastBillFetch?.bbps) {
          console.warn('lastBillFetch missing');
          return;
        }
        const $btn = $(this);
        const $out = $('#billPayResult');
        const {
          mode,
          info
        } = buildPaymentInfoArrayFromUI();
        const v = validatePaymentFields(mode, info);
        if (!v.ok) {
          $out.html(`<p class="text-danger">${escapeHtml(v.message)}</p>`);
          return;
        }
        const payload = buildBillPayPayload(window.lastBillFetch);
        console.log('bill pay payload =>', payload);
        $btn.prop('disabled', true).text('Paying...');
        $out.html('<p>Processing payment...</p>');
        $.ajax({
            url: BILL_PAY_URL,
            type: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify(payload),
            headers: {
              'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
          })
          .done(function(res) {
            const data = res?.data || {};
            const receipt = data?.receipt || {};
            const status = data?.status || 'FAILED';

            const swalIcon =
              status === 'SUCCESS' ? 'success' :
              status === 'PENDING' ? 'warning' : 'error';

            const statusBadgeColor =
              status === 'SUCCESS' ? '#28a745' :
              status === 'PENDING' ? '#f0ad4e' : '#dc3545';

            const logoUrl = '/assets/img/logo/bassured.png?v=312213765';
            const audio = new Audio('/assets/audio/BharatConnect.mp3');

            const html = `
  <div id="receiptPrintArea" style="font-family:Arial, sans-serif; background:#fff; width:100%; max-width:980px; margin:0 auto; color:#4b5563;">

    <div style="position:relative; padding:18px 22px 10px 22px; min-height:110px;">
      <div style="text-align:center;">
        <div style="font-size:18px; font-weight:700; color:#3f95c5; margin-top:28px;">
          Bharat Connect - Payment Confirmation
        </div>
        <div style="margin-top:10px; font-size:13px; line-height:1.6; color:#4f5f73; font-weight:600; text-align:left; max-width:620px;">
          Thank You. We have received your payment request. Please quote your
          Transaction Reference ID for any queries for the below payment.
        </div>
      </div>

      <div style="position:absolute; top:8px; left:50%; transform:translateX(-50%); font-size:13px; font-weight:700; color:#e66a3d;">
        ${escapeHtml(receipt.payment_mode || '')}
      </div>

      <div style="position:absolute; top:6px; right:10px;">
        <img src="${logoUrl}" alt="Assured" style="height:82px; object-fit:contain;">
      </div>
    </div>

    <div style="padding:0 22px 18px 22px;">
      <table style="width:100%; border-collapse:collapse; font-size:13px; table-layout:fixed;">
        <thead>
          <tr>
            <th colspan="2" style="background:#d8edf7; color:#4b5f6f; border:1px solid #c9dbe4; padding:8px 10px; text-align:left; font-size:13px;">
              Transaction Details
            </th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td style="width:40%; border:1px solid #e5e7eb; padding:7px 10px; color:#617184; font-weight:600;">Name of the biller</td>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#394150; font-weight:600;">${escapeHtml(receipt.biller_name || '')}</td>
          </tr>
          <tr>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#617184; font-weight:600;">Mobile number</td>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#394150; font-weight:600;">${escapeHtml(receipt.mobile || '')}</td>
          </tr>
          <tr>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#617184; font-weight:600;">Bill Number</td>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#394150; font-weight:600;">${escapeHtml(receipt.bill_number || '')}</td>
          </tr>
          <tr>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#617184; font-weight:600;">Bill Date (dd/mm/yyyy)</td>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#394150; font-weight:600;">${escapeHtml(receipt.bill_date || '')}</td>
          </tr>
          <tr>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#617184; font-weight:600;">Bill Due Date (dd/mm/yyyy)</td>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#394150; font-weight:600;">${escapeHtml(receipt.due_date || '')}</td>
          </tr>
          <tr>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#617184; font-weight:600;">B-Connect Txn ID</td>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#394150; font-weight:600;">${escapeHtml(receipt.bbps_txn_ref_id || '')}</td>
          </tr>
          <tr>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#617184; font-weight:600;">Registered Mobile Number</td>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#394150; font-weight:600;">${escapeHtml(receipt.mobile || '')}</td>
          </tr>
          <tr>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#617184; font-weight:600;">Payment Mode</td>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#394150; font-weight:600;">${escapeHtml(receipt.payment_mode || '')}</td>
          </tr>
          <tr>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#617184; font-weight:600;">Payment Channel</td>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#394150; font-weight:600;">${escapeHtml(receipt.payment_channel || '')}</td>
          </tr>
          <tr>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#617184; font-weight:600;">Bill Amount</td>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#394150; font-weight:600;">${escapeHtml(receipt.bill_amount || '0.00')}</td>
          </tr>
          <tr>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#617184; font-weight:600;">Customer Convenience Fee</td>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#394150; font-weight:600;">${escapeHtml(receipt.cust_conv_fee || '0.00')}</td>
          </tr>
          <tr>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#617184; font-weight:600;">Total Amount</td>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#394150; font-weight:600;">${escapeHtml(receipt.total_amount || '0.00')}</td>
          </tr>
          <tr>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#617184; font-weight:600;">Transaction Date and Time</td>
            <td style="border:1px solid #e5e7eb; padding:7px 10px; color:#394150; font-weight:600;">${escapeHtml(receipt.transaction_datetime || '')}</td>
          </tr>
          <tr>
            <td style="border:1px solid #d1d5db; padding:7px 10px; color:#617184; font-weight:700;">Status</td>
            <td style="border:1px solid #d1d5db; padding:7px 10px; color:#394150; font-weight:700;">
              ${escapeHtml(receipt.status || status)}
            </td>
          </tr>
        </tbody>
      </table>

      <div style="display:flex; justify-content:space-between; align-items:center; margin-top:14px; font-size:13px;">
        <a href="javascript:void(0)" onclick="printReceipt('receiptPrintArea')" style="color:#7b89b4; text-decoration:underline; font-weight:600;">
          Print This Page
        </a>
        <a href="javascript:void(0)" onclick="window.location.reload()" style="color:#7b89b4; text-decoration:underline; font-weight:600;">
          Make Another Payment
        </a>
      </div>
    </div>
  </div>
`;

            $out.html('');

            Swal.fire({
              icon: swalIcon,
              title: status === 'SUCCESS' ? 'Payment Successful' : (status === 'PENDING' ?
                'Payment Pending' : 'Payment Failed'),
              html: html,
              width: 980,
              padding: '0.8rem',
              confirmButtonText: 'OK',
              showDenyButton: true,
              denyButtonText: 'Print',
              allowOutsideClick: false,
              didOpen: () => {
                audio.currentTime = 0;
                audio.play().catch(() => {});
              },
              customClass: {
                popup: 'bbps-receipt-popup'
              }
            }).then((result) => {
              if (result.isDenied) {
                printReceipt('receiptPrintArea');
                return false;
              }

              if (result.isConfirmed) {
                window.location.reload();
              }
            });
          })
          .fail(function(xhr) {
            console.error('Bill Pay Error =>', xhr);
            const msg = xhr.responseJSON?.message || xhr.responseText || 'Bill Payment Failed';
            $out.html(`<p class="text-danger">${escapeHtml(msg)}</p>`);
          })
          .always(function() {
            $btn.prop('disabled', false).text('Pay Bill');
          });
      });

      function printReceipt(elementId) {
        const content = document.getElementById(elementId);
        if (!content) return;

        const printWindow = window.open('', '_blank', 'width=900,height=700');
        printWindow.document.write(`
    <html>
      <head>
        <title>Payment Receipt</title>
        <style>
          body { font-family: Arial, sans-serif; padding: 20px; }
          table { width: 100%; border-collapse: collapse; font-size: 14px; }
          th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
          th { background: #d9edf7; }
          img { max-height: 70px; }
        </style>
      </head>
      <body>
        ${content.innerHTML}
      </body>
    </html>
  `);
        printWindow.document.close();
        printWindow.focus();

        setTimeout(() => {
          printWindow.print();
          printWindow.close();
        }, 500);
      }
    });
  </script>
@endsection
