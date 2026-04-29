@extends('layouts/layoutMaster')

@section('title', 'My Profile')

@section('page-style')
  @vite(['resources/assets/vendor/scss/pages/page-profile.scss'])
  <style>
    .bg-light-gray {
      background-color: #eeeeef;
    }
  </style>
@endsection

@section('page-script')
  <script>
    document.querySelectorAll('[data-bs-target="#docModal"]').forEach(btn => {
      btn.addEventListener('click', function() {
        document.getElementById('modalImg').src = this.dataset.url;
      });
    });
    /* ===============================
       Generate API Keys
    ================================ */

    /* ===============================
       Toggle Secret Visibility
    ================================ */
    document.querySelector('.toggle-secret').addEventListener('click', function() {

      const input = document.getElementById('client_secret');
      const icon = this.querySelector('i');

      if (!input.dataset.full) return;

      if (input.value === input.dataset.masked) {
        input.value = input.dataset.full;
        icon.classList.replace('fa-eye', 'fa-eye-slash');
      } else {
        input.value = input.dataset.masked;
        icon.classList.replace('fa-eye-slash', 'fa-eye');
      }
    });
    /* ===============================
       Copy FULL secret always
    ================================ */
    document.querySelectorAll('.copy-btn').forEach(btn => {
      btn.addEventListener('click', function() {

        const input = document.getElementById(this.dataset.copy);
        const value = input.dataset.full || input.value;

        navigator.clipboard.writeText(value);

        this.innerHTML = '<i class="fa fa-check"></i>';
        setTimeout(() => {
          this.innerHTML = '<i class="fa fa-copy"></i>';
        }, 1000);
      });
    });
    /* ===============================
       Validate Password
    ================================ */
    document.getElementById('passwordForm').addEventListener('submit', function(e) {

      let currentPassword = document.getElementById('current_password').value.trim();
      let password = document.getElementById('password').value.trim();
      let confirmPassword = document.getElementById('password_confirmation').value.trim();

      let valid = true;

      // Clear errors
      document.querySelectorAll('small.text-danger').forEach(el => el.textContent = '');

      // Current password
      if (currentPassword === '') {
        document.getElementById('currentPasswordError').textContent = 'Current password is required.';
        valid = false;
      }

      // New password
      if (password.length < 8) {
        document.getElementById('passwordError').textContent = 'New password must be at least 8 characters.';
        valid = false;
      }

      // New password should not match old
      if (currentPassword && password && currentPassword === password) {
        document.getElementById('passwordError').textContent = 'New password cannot be same as current password.';
        valid = false;
      }

      // Confirm password
      if (password !== confirmPassword) {
        document.getElementById('confirmPasswordError').textContent = 'Passwords do not match.';
        valid = false;
      }

      if (!valid) {
        e.preventDefault(); // stop form submit
      }
    });

    /* ===============================
      Combine Whitelisted IPs
      ================================ */
    document.addEventListener('DOMContentLoaded', function() {
      const wrapper = document.getElementById('ip-wrapper');
      const hiddenInput = document.getElementById('ip-hidden');
      const form = wrapper.closest('form');

      // ADD new IP
      wrapper.addEventListener('click', function(e) {
        if (e.target.classList.contains('add-ip')) {
          const addRow = wrapper.querySelector('.ip-add-row');
          const input = addRow.querySelector('.ip-input');
          const value = input.value.trim();
          if (!value) return;
          // Create removable row
          const row = document.createElement('div');
          row.className = 'input-group mb-2 ip-row';
          row.innerHTML = `
            <input type="text" class="form-control ip-input bg-light-gray" readonly value="${value}">
            <button type="button" class="btn btn-danger remove-ip">−</button>
          `;
          wrapper.appendChild(row);
          // Clear add input
          input.value = '';
        }

        // REMOVE IP
        if (e.target.classList.contains('remove-ip')) {
          e.target.closest('.ip-row').remove();
        }
      });

      // BEFORE SUBMIT → build comma-separated IPs
      form.addEventListener('submit', function() {
        const ips = [...wrapper.querySelectorAll('.ip-row:not(.ip-add-row) .ip-input')]
          .map(i => i.value.trim())
          .filter(Boolean);
        hiddenInput.value = ips.join(',');
      });
    });
  </script>
@endsection

@section('content')
  @php
    use Illuminate\Support\Facades\Crypt;
    use App\Helpers\Helpers;

    $company = $user->companyDetail;
    $services = $user->services;
    $api = $user->apiKey;
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

    $clientKey = $api && $api->client_key ? Crypt::decryptString($api->client_key) : '';

    $clientSecret = $api && $api->client_secret ? Crypt::decryptString($api->client_secret) : '';

    $maskedSecret = $clientSecret ? substr($clientSecret, 0, 5) . '..............' : '';
  @endphp

  <!-- ================= HEADER ================= -->
  <div class="row">
    <div class="col-12">
      <div class="card mb-6">
        <div class="user-profile-header-banner">
          <img src="{{ asset('assets/img/pages/profile-banner.png') }}" class="rounded-top">
        </div>

        <div class="user-profile-header d-flex flex-column flex-lg-row text-center text-lg-start mb-5">
          <div class="flex-shrink-0 mt-n2 mx-auto mx-lg-0">
            <img src="{{ asset('assets/img/avatars/1.png') }}" class="rounded user-profile-img">
          </div>

          <div class="flex-grow-1 mt-3 mt-lg-5">
            <div class="d-flex justify-content-between flex-column flex-md-row mx-5 gap-4">
              <div>
                <h4 class="mb-1 mt-2">{{ $user->company_name }}</h4>
                <ul class="list-inline d-flex gap-4 flex-wrap">
                  <li><i class="fa fa-user"></i> UID: {{ $user->uid }}</li>
                  <li><i class="fa fa-envelope"></i> {{ $user->email }}</li>
                  <li><i class="fa fa-phone"></i> {{ $user->mobile_number }}</li>
                </ul>
              </div>

              <span class="badge bg-label-success align-self-start">
                {{ $user->active ? 'Active' : 'Inactive' }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ================= TABS ================= -->
  <ul class="nav nav-pills mb-4">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#profile">Profile</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#company">Company Details</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#director">Director Info</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#services">Services</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#api">Developer Option</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#documents">Documents</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#passwordChange">Change Password</a></li>
  </ul>

  <div class="tab-content">

    <!-- ================= PROFILE ================= -->
    <div class="tab-pane fade show active" id="profile">
      <table class="table">
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
          <td>{!! $user->active == 'A'
              ? '<span class="badge bg-label-success">Active</span>'
              : '<span class="badge bg-label-danger">Blocked</span>' !!}</td>
        </tr>
      </table>
    </div>

    <!-- ================= COMPANY ================= -->
    <div class="tab-pane fade" id="company">
      <table class="table">
        <tr>
          <th>Company Type</th>
          <td>{{ str_replace('_', ' ', $company->company_type) }}</td>
        </tr>
        <tr>
          <th>GST</th>
          <td>{{ $decrypt($company->gst_no) }}</td>
        </tr>
        <tr>
          <th>CIN</th>
          <td>{{ $decrypt($company->cin) }}</td>
        </tr>
        <tr>
          <th>PAN</th>
          <td>
            @php
              $companyPan = $decrypt($company->pan);
              $masked = str_repeat('*', max(0, strlen($companyPan) - 4)) . substr($companyPan, -4);
            @endphp
            {{ $masked }}
          </td>
        </tr>
        <tr>
          <th>Udyam</th>
          <td>{{ $decrypt($company->udhyam_number) }}</td>
        </tr>
        <tr>
          <th>Address</th>
          <td>{{ $company->address }}</td>
        </tr>
      </table>
    </div>

    <!-- ================= Director Info ================= -->
    <div class="tab-pane fade" id="director">
      <table class="table">
        <tr>
          <th>Director Name</th>
          <td>{{ $company->director_name }}</td>
        </tr>
        <tr>
          <th>Director Email</th>
          <td>{{ $company->director_email }}</td>
        </tr>
        <tr>
          <th>Director Mobile</th>
          <td>{{ $company->director_mobile }}</td>
        </tr>
        <tr>
          <th>Aadhar</th>
          <td>
            @php
              $aadhaar = $decrypt($company->director_aadhar_no);
              $masked = str_repeat('*', max(0, strlen($aadhaar) - 4)) . substr($aadhaar, -4);
            @endphp
            {{ $masked }}
          </td>
        </tr>
        <tr>
          <th>PAN</th>
          <td>
            @php
              $pan = $decrypt($company->director_pan_no);
              $masked = str_repeat('*', max(0, strlen($pan) - 4)) . substr($pan, -4);
            @endphp
            {{ $masked }}
          </td>
        </tr>
      </table>
    </div>

    <!-- ================= SERVICES ================= -->
    <div class="tab-pane fade" id="services">
      <table class="table">
        <tr>
          <th>Payout Status</th>
          <td>{!! $services->payout_status == 'A'
              ? '<span class="badge bg-label-success">Active</span>'
              : '<span class="badge bg-label-danger">Blocked</span>' !!}</td>
        </tr>
        <tr>
          <th>Payin Status</th>
          <td>{!! $services->payin_status == 'A'
              ? '<span class="badge bg-label-success">Active</span>'
              : '<span class="badge bg-label-danger">Blocked</span>' !!}</td>
        </tr>
        <tr>
          <th>Payout Min Ticket</th>
          <td>{{ $services->minimum_transaction }}</td>
        </tr>
        <tr>
          <th>Payout Max Ticket</th>
          <td>{{ $services->maximum_transaction }}</td>
        </tr>
        <tr>
          <th>Payin Min Ticket</th>
          <td>{{ $services->payin_minimum_transaction }}</td>
        </tr>
        <tr>
          <th>Payin Max Ticket</th>
          <td>{{ $services->payin_maximum_transaction }}</td>
        </tr>
        <tr>
          <th>Payout Charges</th>
          <td>{{ $services->virtual_charges }}</td>
        </tr>
      </table>
    </div>

    <!-- ================= API ================= -->
    <div class="tab-pane fade" id="api">
      <div class="row g-4">
        {{-- ===== API KEY GENERATION ===== --}}
        <div class="col-12">
          <table class="table table-bordered align-middle mb-0">
            <tbody>

              {{-- Client Key --}}
              <tr>
                <td>Client Key</td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <input type="text" readonly id="client_key" class="form-control" value="{{ $clientKey }}"
                      data-full="{{ $clientKey }}">
                    <button type="button" class="btn btn-outline-secondary btn-sm copy-btn" data-copy="client_key">
                      <i class="fa fa-copy"></i>
                    </button>
                  </div>
                </td>
              </tr>

              {{-- Client Secret --}}
              <tr>
                <td>Client Secret</td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <input type="text" readonly id="client_secret" class="form-control" value="{{ $maskedSecret }}"
                      data-full="{{ $clientSecret }}" data-masked="{{ $maskedSecret }}">
                    <button type="button" class="btn btn-outline-secondary btn-sm toggle-secret">
                      <i class="fa fa-eye"></i>
                    </button>

                    <button type="button" class="btn btn-outline-secondary btn-sm copy-btn" data-copy="client_secret">
                      <i class="fa fa-copy"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <form method="POST" action="{{ route('update.api') }}">
        @csrf
        <div class="row g-4">
          {{-- ===== WEBHOOKS ===== --}}
          <div class="col-md-6">
            <label class="form-label">Payin Webhook URL</label>
            <input type="url" name="payin_webhooks"
              value="{{ old('payin_webhooks', $user->apiKey->payin_webhooks ?? '') }}" class="form-control">
            @error('payin_webhooks')
              <span class="text-danger">{{ $message }}</span>
            @enderror
          </div>

          <div class="col-md-6">
            <label class="form-label">Payout Webhook URL</label>
            <input type="url" name="payout_webhooks"
              value="{{ old('payout_webhooks', $user->apiKey->payout_webhooks ?? '') }}" class="form-control">
            @error('payout_webhooks')
              <span class="text-danger">{{ $message }}</span>
            @enderror
          </div>

          {{-- ===== WHITELISTED IPS ===== --}}
          <div class="col-md-12">
            <label class="form-label">Whitelisted IPs</label>

            @php
              $ips = old('ip', $user->apiKey->ip ?? '');
              $ips = $ips ? explode(',', $ips) : [];
            @endphp

            <div id="ip-wrapper">
              {{-- FIRST ROW (always blank + add button) --}}
              <div class="input-group mb-2 ip-row ip-add-row">
                <input type="text" class="form-control ip-input" placeholder="e.g. 1.1.1.1">
                <button type="button" class="btn btn-success add-ip">+</button>
              </div>

              {{-- EXISTING IPS --}}
              @foreach ($ips as $ip)
                <div class="input-group mb-2 ip-row">
                  <input type="text" class="form-control ip-input bg-light-gray" readonly
                    value="{{ trim($ip) }}">
                  <button type="button" class="btn btn-danger remove-ip">−</button>
                </div>
              @endforeach
            </div>

            {{-- Hidden field --}}
            <input type="hidden" name="ip" id="ip-hidden">

            @error('ip')
              <span class="text-danger">{{ $message }}</span>
            @enderror
          </div>

          <div class="col-12 text-end">
            <button class="btn btn-success">Save</button>
          </div>
        </div>
      </form>
    </div>

    <!-- ================= change password ================= -->
    <div class="tab-pane fade" id="passwordChange">
      <form method="POST" action="{{ route('update.password') }}" id="passwordForm">
        @csrf
        <div class="row g-4">
          <div class="col-12">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" id="current_password"
              placeholder="Enter your current password" class="form-control">
            <small class="text-danger" id="currentPasswordError"></small>
            @error('current_password')
              <span class="text-danger">{{ $message }}</span>
            @enderror
          </div>
          <div class="col-12">
            <label class="form-label">New Password</label>
            <input type="password" name="password" id="password" placeholder="Enter a new password"
              class="form-control">
            <small class="text-danger" id="passwordError"></small>
            @error('password')
              <span class="text-danger">{{ $message }}</span>
            @enderror
          </div>
          <div class="col-12">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="password_confirmation" id="password_confirmation"
              placeholder="Re-enter the new password" class="form-control">
            <small class="text-danger" id="confirmPasswordError"></small>
            @error('password_confirmation')
              <span class="text-danger">{{ $message }}</span>
            @enderror
          </div>
          <div class="col-12 text-end">
            <button type="submit" class="btn btn-success">Save</button>
          </div>
        </div>
      </form>
    </div>

    <!-- ================= DOCUMENTS ================= -->
    <div class="tab-pane fade" id="documents">
      <div class="card">
        <div class="card-body">
          @foreach ([
          'GST' => $company->gst_image,
          'PAN' => $company->pan_image,
          'CIN' => $company->cin_image,
          'Udhyam' => $company->udhyam_image,
          'Director Aadhaar' => $company->director_aadhar_image,
          'Director PAN' => $company->director_pan_image,
      ] as $label => $img)
            @if (!empty($img))
              <button class="btn btn-sm btn-primary mb-2" data-bs-toggle="modal" data-bs-target="#docModal"
                data-url="{{ route('profile.doc', base64_encode(str_replace('public/', '', $img))) }}">
                View {{ $label }}
              </button>
            @endif
          @endforeach
        </div>
      </div>
    </div>
  </div>

  <!-- ================= MODAL ================= -->
  <div class="modal fade" id="docModal">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5>Document Preview</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body text-center">
          <img id="modalImg" class="img-fluid">
        </div>
      </div>
    </div>
  </div>
@endsection
