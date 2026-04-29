@extends('layouts/layoutMaster')

@section('title', 'User View')
<!-- Vendor Styles -->
@section('vendor-style')
  <style>
    .error-text {
      color: #dc3545;
      font-size: 0.85rem;
      margin-top: 4px;
    }

    .is-invalid {
      border-color: #dc3545;
    }
  </style>
  @vite(['resources/assets/vendor/libs/bs-stepper/bs-stepper.scss', 'resources/assets/vendor/libs/bootstrap-select/bootstrap-select.scss', 'resources/assets/vendor/libs/select2/select2.scss'])
@endsection

<!-- Vendor Scripts -->
@section('vendor-script')
  @vite(['resources/assets/vendor/libs/bs-stepper/bs-stepper.js', 'resources/assets/vendor/libs/bootstrap-select/bootstrap-select.js', 'resources/assets/vendor/libs/select2/select2.js'])
@endsection

<!-- Page Scripts -->
@section('page-script')
  <script>
    document.querySelectorAll('[data-bs-target="#docModal"]').forEach(btn => {
      btn.addEventListener('click', function() {
        document.getElementById('modalImg').src = this.dataset.url;
      });
    });
  </script>
  @vite(['resources/assets/js/form-wizard-icons.js'])
@endsection
@section('content')
  @php
    use Illuminate\Support\Facades\Crypt;
    use App\Helpers\Helpers;

    $company = $user->companyDetail;
    $services = $user->services;
    $api = $user->apiKey;

    /* ===== Decrypt helper ===== */
    $decrypt = function ($value) {
        if (empty($value)) {
            return '-';
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return $value; // fallback if not encrypted
        }
    };
  @endphp

  <div class="container-fluid px-0">

    <h5 class="mb-4">User Details</h5>

    {{-- ================= USER INFO ================= --}}
    <div class="card mb-4">
      <div class="card-header">User Information</div>
      <div class="card-body">
        <table class="table table-bordered">
          <tr>
            <th>UID</th>
            <td>{{ $user->uid }}</td>
          </tr>
          <tr>
            <th>Company Name</th>
            <td>{{ $user->company_name }}</td>
          </tr>
          <tr>
            <th>Email</th>
            <td>{{ $user->email }}</td>
          </tr>
          <tr>
            <th>Mobile</th>
            <td>{{ $user->mobile_number }}</td>
          </tr>
          <tr>
            <th>Status</th>
            <td>
              {!! $user->active == 'A'
                  ? '<span class="badge bg-label-success">Active</span>'
                  : '<span class="badge bg-label-danger">Blocked</span>' !!}</td>
          </tr>
          <tr>
            <th>Payout Balance</th>
            <td>{{ $user->payout_balance }}</td>
          </tr>
          <tr>
            <th>Payin Balance</th>
            <td>{{ $user->payin_balance }}</td>
          </tr>
          <tr>
            <th>Reserve Balance</th>
            <td>{{ $user->reserve_balance }}</td>
          </tr>
          <tr>
            <th>Freeze Balance</th>
            <td>{{ $user->freeze_balance }}</td>
          </tr>
          <tr>
            <th>Virtual Balance</th>
            <td>{{ $user->virtual_balance }}</td>
          </tr>
        </table>
      </div>
    </div>

    {{-- ================= COMPANY INFO ================= --}}
    <div class="card mb-4">
      <div class="card-header">Company Details</div>
      <div class="card-body">
        <table class="table table-bordered">

          <tr>
            <th>Company Type</th>
            <td>{{ str_replace('_', ' ', $company->company_type) }}</td>
          </tr>

          <tr>
            <th>GST No</th>
            <td>{{ $decrypt($company->gst_no) }}</td>
          </tr>

          <tr>
            <th>CIN</th>
            <td>{{ $decrypt($company->cin) }}</td>
          </tr>

          <tr>
            <th>PAN</th>
            <td>{{ $decrypt($company->pan) }}</td>
          </tr>

          <tr>
            <th>Udyam No</th>
            <td>{{ $decrypt($company->udhyam_number) }}</td>
          </tr>

          <tr>
            <th>Address</th>
            <td>{{ $company->address }}</td>
          </tr>

        </table>
      </div>
    </div>

    {{-- ================= DIRECTOR INFO ================= --}}
    <div class="card mb-4">
      <div class="card-header">Director Details</div>
      <div class="card-body">
        <table class="table table-bordered">

          <tr>
            <th>Name</th>
            <td>{{ $company->director_name }}</td>
          </tr>
          <tr>
            <th>Email</th>
            <td>{{ $company->director_email }}</td>
          </tr>
          <tr>
            <th>Mobile</th>
            <td>{{ $company->director_mobile }}</td>
          </tr>

          <tr>
            <th>Aadhaar</th>
            <td>{{ $decrypt($company->director_aadhar_no) }}</td>
          </tr>

          <tr>
            <th>PAN</th>
            <td>{{ $decrypt($company->director_pan_no) }}</td>
          </tr>

        </table>
      </div>
    </div>

    {{-- ================= DOCUMENTS ================= --}}
    <div class="card mb-4">
      <div class="card-header">Documents</div>
      <div class="card-body">
        <table class="table table-bordered">

          @foreach ([
          'GST Image' => $company->gst_image,
          'CIN Image' => $company->cin_image,
          'PAN Image' => $company->pan_image,
          'Udyam Image' => $company->udhyam_image,
          'MOA Image' => $company->moa_image,
          'BR Image' => $company->br_image,
          'Director Aadhaar' => $company->director_aadhar_image,
          'Director PAN' => $company->director_pan_image,
      ] as $label => $img)
            @if ($img)
              <tr>
                <th>{{ $label }}</th>
                <td>
                  <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#docModal"
                    data-url="{{ route('secure.doc', base64_encode(str_replace('public/', '', $img))) }}">
                    View
                  </button>
                </td>
              </tr>
            @endif
          @endforeach

        </table>
      </div>
    </div>

    {{-- ================= SERVICES ================= --}}
    <div class="card mb-4">
      <div class="card-header">Services</div>
      <div class="card-body">
        <table class="table table-bordered">
          <tr>
            <th>Payout Status</th>
            <td>
              {!! $services->payout_status == 'A'
                  ? '<span class="badge bg-label-success">Active</span>'
                  : '<span class="badge bg-label-danger">Blocked</span>' !!}
            </td>
          </tr>
          <tr>
            <th>Payin Status</th>
            <td>{!! $services->payin_status == 'A'
                ? '<span class="badge bg-label-success">Active</span>'
                : '<span class="badge bg-label-danger">Blocked</span>' !!}</td>
          </tr>
          <tr>
            <th>Min Txn</th>
            <td>{{ $services->minimum_transaction }}</td>
          </tr>
          <tr>
            <th>Max Txn</th>
            <td>{{ $services->maximum_transaction }}</td>
          </tr>
          <tr>
            <th>Payin Min Txn</th>
            <td>{{ $services->payin_minimum_transaction }}</td>
          </tr>
          <tr>
            <th>Payin Max Txn</th>
            <td>{{ $services->payin_maximum_transaction }}</td>
          </tr>
          <tr>
            <th>Wallet Type</th>
            <td>{{ $services->wallet_type }}</td>
          </tr>
          <tr>
            <th>Virtual Type</th>
            <td>{{ $services->virtual_type }}</td>
          </tr>
          <tr>
            <th>Payin Charges</th>
            <td>{{ $services->payin_charges }}</td>
          </tr>
          <tr>
            <th>Payout Charges</th>
            <td>{{ $services->payout_charges }}</td>
          </tr>
          <tr>
            <th>Virtual Charges</th>
            <td>{{ $services->virtual_charges }}</td>
          </tr>
          <tr>
            <th>Platform Fee</th>
            <td>{{ $services->platform_fee }}</td>
          </tr>
          <tr>
            <th>Payout Slab Charges <small class="text-danger text-lowercase">(upto 1000)</small></th>
            <td>{{ $services->pslab_1000 }}</td>
          </tr>
          <tr>
            <th>Payout Slab Charges <small class="text-danger text-lowercase">(1000 to 25000)</small></th>
            <td>{{ $services->pslab_25000 }}</td>
          </tr>
          <tr>
            <th>Payout Slab Charges <small class="text-danger text-lowercase">(25000 to 200000)</small></th>
            <td>{{ $services->pslab_200000 }}</td>
          </tr>
          <tr>
            <th>Percentage Flat Charges <small class="text-danger text-lowercase">(upto 500)</small></th>
            <td>{{ $services->pflat_charges }}</td>
          </tr>
          <tr>
            <th>Percentage Flat Charges <small class="text-danger text-lowercase">(500 to 1000)</small></th>
            <td>{{ $services->pflat_charges_2 }}</td>
          </tr>
          <tr>
            <th>Active Payout API</th>
            <td>{{ $services->active_payout_api ?? '-' }}</td>
          </tr>
          <tr>
            <th>Active Payin API</th>
            <td>{{ $services->active_payin_api ?? '-' }}</td>
          </tr>
        </table>
      </div>
    </div>

    {{-- ================= API KEYS ================= --}}
    <div class="card mb-4">
      <div class="card-header">API Details</div>
      <div class="card-body">
        <table class="table table-bordered">

          <tr>
            <th>Client Key</th>
            <td>{{ $api->client_key ? $decrypt($api->client_key) : 'N/A' }}</td>
          </tr>

          <tr>
            <th>Client Secret</th>
            <td>{{ $api->client_secret ? $decrypt($api->client_secret) : 'N/A' }}</td>
          </tr>
          <tr>
            <th>Payin Webhook</th>
            <td>{{ $api->payin_webhooks ?? 'N/A' }}</td>
          </tr>
          <tr>
            <th>Payout Webhook</th>
            <td>{{ $api->payout_webhooks ?? 'N/A' }}</td>
          </tr>
          <tr>
            <th>Whitelisted IP</th>
            <td>{{ $api->ip ?? 'N/A' }}</td>
          </tr>
          <tr>
            <th>Bulkpe Auth Token</th>
            <td>{{ $api->bulkpe_auth_token ?? 'N/A' }}</td>
          </tr>
          <tr>
            <th>razorpay account number</th>
            <td>{{ $api->razorpay_account_number ?? 'N/A' }}</td>
          </tr>
          <tr>
            <th>razorpay api key</th>
            <td>{{ $api->razorpay_api_key ?? 'N/A' }}</td>
          </tr>
          <tr>
            <th>razorpay secret key</th>
            <td>{{ $api->razorpay_secret_key ?? 'N/A' }}</td>
          </tr>
          <tr>
            <th>paywize api key</th>
            <td>{{ $api->paywize_api_key ?? 'N/A' }}</td>
          </tr>
          <tr>
            <th>paywize secret key</th>
            <td>{{ $api->paywize_secret_key ?? 'N/A' }}</td>
          </tr>
          <tr>
            <th>Buckbox Merchant Id</th>
            <td>{{ $api->buckbox_merchant_id ?? 'N/A' }}</td>
          </tr>
          <tr>
            <th>Buckbox Merchant Name</th>
            <td>{{ $api->buckbox_merchant_name ?? 'N/A' }}</td>
          </tr>
          <tr>
            <th>Buckbox Merchant Email</th>
            <td>{{ $api->buckbox_merchant_email ?? 'N/A' }}</td>
          </tr>
          <tr>
            <th>Buckbox Api Key</th>
            <td>{{ $api->buckbox_api_key ?? 'N/A' }}</td>
          </tr>
          <tr>
            <th>Buckbox Secret Key</th>
            <td>{{ $api->buckbox_secret_key ?? 'N/A' }}</td>
          </tr>
          <tr>
            <th>Buckbox Eny Key</th>
            <td>{{ $api->buckbox_eny_key ?? 'N/A' }}</td>
          </tr>
        </table>
      </div>
    </div>

  </div>

  {{-- ================= IMAGE MODAL ================= --}}
  <div class="modal fade" id="docModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Document Preview</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body text-center">
          <img id="modalImg" src="" class="img-fluid">
        </div>
      </div>
    </div>
  </div>

@endsection
