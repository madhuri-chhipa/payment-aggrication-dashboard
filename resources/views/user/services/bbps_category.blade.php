@extends('layouts/layoutMaster')
@section('title', ucfirst($category))

@section('content')
  <div class="card">
    <div class="card-body">

      <h5 class="mb-4">{{ ucfirst($category) }} Bill Payment</h5>

      <div class="row mb-3">
        <div class="col-md-6 mx-auto">
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
  </div>
@endsection
@section('page-script')
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script>
    $(function() {
      const $biller = $('#biller_id');
      const $dynamic = $('#dynamicFields');
      const BILL_FETCH_URL = "{{ route('bill.fetch') }}";

      function loading() {
        $dynamic.html(`
      <div class="row">
        <div class="col-md-6 mx-auto"><p>Loading...</p></div>
      </div>
    `);
      }

      function errorBox(msg) {
        $dynamic.html(`
      <div class="row">
        <div class="col-md-6 mx-auto">
          <p class="text-danger">${msg}</p>
        </div>
      </div>
    `);
      }

      function ensureCustomerMobileField() {
        return `
      <div class="row mb-3">
        <div class="col-md-6 col-12 mx-auto">
          <label>Customer Mobile</label>
          <input type="text"
            name="customer[mobile]"
            maxlength="10"
            class="form-control"
            required
            placeholder="Enter mobile number">
        </div>
      </div>
    `;
      }

      function renderFields(res) {
        $dynamic.empty();
        $dynamic.append(ensureCustomerMobileField());

        (res.fields || []).forEach(function(field) {
          if (field.visibility === "N") return;

          const required = field.mandatory === "Y" ? "required" : "";
          const maxLength = field.maxLength ?? "";

          $dynamic.append(`
        <div class="row mb-3">
          <div class="col-md-6 col-12 mx-auto">
            <label>${field.paramName}</label>
            <input type="text"
              name="inputs[${field.paramName}]"
              maxlength="${maxLength}"
              class="form-control"
              ${required}>
          </div>
        </div>
      `);
        });
      }

      function renderButtons(res, billerId) {
        let buttons = `
      <div class="row">
        <div class="mt-3 col-md-6 mx-auto d-flex gap-2 flex-wrap">
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
        }

        if (res.is_validation_api === "1") {
          buttons += `
        <a href="/service/validate-bill/${billerId}" class="btn btn-warning">
          Validate Bill
        </a>
      `;
        }

        if (res.is_plan_mdm_require === "1") {
          buttons += `
        <a href="/service/view-plans/${billerId}" class="btn btn-info">
          View Plans
        </a>
      `;
        }

        buttons += `
        </div>
      </div>
      <div class="row mt-2">
        <div class="col-md-6 mx-auto">
          <div id="billFetchResult"></div>
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
        $.get('/service/biller-fields/' + billerId, function(res) {
          if (res.error) {
            errorBox(res.error);
            return;
          }
          renderFields(res);
          renderButtons(res, billerId);

          console.log("Loaded from: " + res.source);
        }).fail(function() {
          errorBox('Something went wrong');
        });
      });

      $(document).on('click', '#btnFetchBill', function() {
        const billerId = $(this).data('biller-id');
        if (!billerId) return;

        const $btn = $(this);
        const $result = $('#billFetchResult');

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
            $result.html(`<pre class="bg-light p-2">${escapeHtml(JSON.stringify(res, null, 2))}</pre>`);
          })
          .fail(function(xhr) {
            const msg = xhr.responseJSON?.message || xhr.responseText || 'Bill Fetch Failed';
            $result.html(`<p class="text-danger">${escapeHtml(msg)}</p>`);
          })
          .always(function() {
            $btn.prop('disabled', false).text('Fetch Bill');
          });
      });
    });
  </script>
@endsection
