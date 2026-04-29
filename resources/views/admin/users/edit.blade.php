@extends('layouts/layoutMaster')

@section('title', 'User Edit')
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

    .bg-light-gray {
      background-color: #eeeeef;
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
    document.addEventListener('DOMContentLoaded', function() {

      const virtualType = document.querySelector('select[name="virtual_type"]');
      const percentageFields = document.querySelectorAll('.virtual-percentage');
      const flatFields = document.querySelectorAll('.virtual-flat');

      function toggleVirtualFields() {
        if (virtualType.value === 'percentage') {
          percentageFields.forEach(el => el.style.display = 'block');
          flatFields.forEach(el => el.style.display = 'none');
        } else {
          percentageFields.forEach(el => el.style.display = 'none');
          flatFields.forEach(el => el.style.display = 'block');
        }
      }

      // On change
      virtualType.addEventListener('change', toggleVirtualFields);

      // On page load (important for edit + old values)
      toggleVirtualFields();
    });
    document.addEventListener('DOMContentLoaded', function() {

      const walletType = document.querySelector('select[name="wallet_type"]');
      const payoutWalletFields = document.querySelectorAll('.payout-wallet');
      const virtualWalletFields = document.querySelectorAll('.virtual-wallet');

      function togglewalletTypeFields() {
        if (walletType.value === 'payout_wallet') {
          payoutWalletFields.forEach(el => el.style.display = 'block');
          virtualWalletFields.forEach(el => el.style.display = 'none');
        } else {
          payoutWalletFields.forEach(el => el.style.display = 'none');
          virtualWalletFields.forEach(el => el.style.display = 'block');
        }
      }

      // On change
      walletType.addEventListener('change', togglewalletTypeFields);

      // On page load (important for edit + old values)
      togglewalletTypeFields();
    });

    // update wallet
    async function updateWallet(button, type, action) {
      const amountInput = document.getElementById(type + '_amount');
      const amount = amountInput.value;

      if (!amount || amount <= 0) {
        toastr.error('Please enter a valid amount');
        return;
      }

      // ✅ Vuexy modal for OTP + optional description
      const modalResult = await openWalletOtpModal();
      if (!modalResult) {
        toastr.info('Transaction cancelled');
        return;
      }

      // Static OTP check (front-end)
      if (modalResult.otp !== '151515') {
        toastr.error('Invalid OTP');
        return;
      }

      // Disable all wallet buttons
      document.querySelectorAll('.wallet-btn').forEach(btn => btn.disabled = true);

      // Show loader on clicked button
      button.querySelector('.btn-text').classList.add('d-none');
      button.querySelector('.spinner-border').classList.remove('d-none');

      fetch("{{ route('admin.user.update.wallet', $user->id) }}", {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            wallet_type: type,
            action: action,
            amount: amount,
            otp: modalResult.otp,
            description: modalResult.description
          })
        })
        .then(res => res.json())
        .then(res => {
          if (res.success) {
            toastr.success(res.message);
            setTimeout(() => location.reload(), 800);
          } else {
            toastr.error(res.message);
          }
        })
        .catch(() => toastr.error('Something went wrong'))
        .finally(() => {
          document.querySelectorAll('.wallet-btn').forEach(btn => {
            btn.disabled = false;
            btn.querySelector('.btn-text').classList.remove('d-none');
            btn.querySelector('.spinner-border').classList.add('d-none');
          });
        });
    }


    /* ===============================
       Generate API Keys
    ================================ */
    document.getElementById('generateApiKey').addEventListener('click', function() {

      const url = this.dataset.url;

      fetch(url, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
          }
        })
        .then(res => res.json())
        .then(data => {

          if (!data.success) {
            toastr.error(data.message || 'Something went wrong');
            return;
          }

          /* Client key → FULL */
          const clientKeyInput = document.getElementById('client_key');
          clientKeyInput.value = data.client_key;
          clientKeyInput.dataset.full = data.client_key;

          /* Secret key → gdsjf...... */
          const secretInput = document.getElementById('client_secret');
          const masked = data.client_secret.substring(0, 5) + '......';

          secretInput.value = masked;
          secretInput.dataset.full = data.client_secret;
          secretInput.dataset.masked = masked;
          secretInput.type = 'text'; // keep text, masking is manual

          toastr.success(data.message);
        })
        .catch(() => {
          toastr.error('Server error while generating API keys');
        });
    });

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

    function openWalletOtpModal() {
      return new Promise((resolve) => {
        const modalEl = document.getElementById('walletOtpModal');
        const otpEl = document.getElementById('walletOtpInput');
        const descEl = document.getElementById('walletDescInput');
        const btnEl = document.getElementById('walletOtpConfirmBtn');
        const errEl = document.getElementById('walletOtpError');

        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

        // reset fields
        otpEl.value = '';
        descEl.value = '';
        otpEl.classList.remove('is-invalid');
        errEl.textContent = 'OTP is required';

        let resolved = false;

        const cleanup = () => {
          btnEl.removeEventListener('click', onConfirm);
          modalEl.removeEventListener('hidden.bs.modal', onHidden);
        };

        const onHidden = () => {
          if (!resolved) resolve(null); // cancelled/closed
          cleanup();
        };

        const onConfirm = () => {
          const otp = (otpEl.value || '').trim();
          const description = (descEl.value || '').trim();

          if (!otp) {
            otpEl.classList.add('is-invalid');
            errEl.textContent = 'OTP is required';
            return;
          }

          otpEl.classList.remove('is-invalid');

          resolved = true;
          modal.hide();
          cleanup();

          resolve({
            otp,
            description: description ? description : null
          });
        };

        btnEl.addEventListener('click', onConfirm);
        modalEl.addEventListener('hidden.bs.modal', onHidden);

        modal.show();

        // focus OTP after open
        setTimeout(() => otpEl.focus(), 200);
      });
    }
  </script>


  @vite(['resources/assets/js/form-wizard-icons.js'])
@endsection

@section('content')
  @php
    use Illuminate\Support\Facades\Crypt;
    use App\Helpers\Helpers;
    $gstNo =
        $user->companyDetail && $user->companyDetail->gst_no ? Crypt::decryptString($user->companyDetail->gst_no) : '';
    $cin = $user->companyDetail && $user->companyDetail->cin ? Crypt::decryptString($user->companyDetail->cin) : '';
    $pan = $user->companyDetail && $user->companyDetail->pan ? Crypt::decryptString($user->companyDetail->pan) : '';
    $udhyamNumber =
        $user->companyDetail && $user->companyDetail->udhyam_number
            ? Crypt::decryptString($user->companyDetail->udhyam_number)
            : '';
    $directorAadharNo =
        $user->companyDetail && $user->companyDetail->director_aadhar_no
            ? Crypt::decryptString($user->companyDetail->director_aadhar_no)
            : '';
    $directorPanNo =
        $user->companyDetail && $user->companyDetail->director_pan_no
            ? Crypt::decryptString($user->companyDetail->director_pan_no)
            : '';
    $directorAadharImage =
        $user->companyDetail && $user->companyDetail->director_aadhar_image
            ? Helpers::decryptDocument($user->companyDetail->director_aadhar_image)
            : '';
    $directorPanImage =
        $user->companyDetail && $user->companyDetail->director_pan_image
            ? Helpers::decryptDocument($user->companyDetail->director_pan_image)
            : '';
    $gstImage =
        $user->companyDetail && $user->companyDetail->gst_image
            ? Helpers::decryptDocument($user->companyDetail->gst_image)
            : '';
    $cinImage =
        $user->companyDetail && $user->companyDetail->cin_image
            ? Helpers::decryptDocument($user->companyDetail->cin_image)
            : '';
    $panImage =
        $user->companyDetail && $user->companyDetail->pan_image
            ? Helpers::decryptDocument($user->companyDetail->pan_image)
            : '';
    $udhyamImage =
        $user->companyDetail && $user->companyDetail->udhyam_image
            ? Helpers::decryptDocument($user->companyDetail->udhyam_image)
            : '';
    $moaImage =
        $user->companyDetail && $user->companyDetail->moa_image
            ? Helpers::decryptDocument($user->companyDetail->moa_image)
            : '';
    $brImage =
        $user->companyDetail && $user->companyDetail->br_image
            ? Helpers::decryptDocument($user->companyDetail->br_image)
            : '';

    $clientKey = $user->apiKey && $user->apiKey->client_key ? Crypt::decryptString($user->apiKey->client_key) : '';

    $clientSecret =
        $user->apiKey && $user->apiKey->client_secret ? Crypt::decryptString($user->apiKey->client_secret) : '';

    $maskedSecret = $clientSecret ? substr($clientSecret, 0, 5) . '..............' : '';
  @endphp
  <!-- ================= HEADER ================= -->
  <div class="row">
    <div class="col-12">
      <h5>User Edit</h5>
    </div>

    <!-- Default Icons Wizard -->
    <div class="col-12 mb-6">
      <div class="bs-stepper wizard-icons wizard-icons-example mt-2" id="wizard-icons">
        <div class="bs-stepper-header">
          <div class="step" data-target="#basic-details">
            <button type="button" class="step-trigger px-0">
              <span class="bs-stepper-icon">
                <svg viewBox="0 0 54 54">
                  <use xlink:href="{{ asset('assets/svg/icons/form-wizard-account.svg#wizardAccount') }}">
                  </use>
                </svg>
              </span>
              <span class="bs-stepper-label">Basic Details</span>
            </button>
          </div>
          <div class="line">
            <i class="icon-base ti tabler-chevron-right"></i>
          </div>
          <div class="step" data-target="#company-info">
            <button type="button" class="step-trigger px-0">
              <span class="bs-stepper-icon">
                <svg viewBox="0 0 58 54">
                  <use xlink:href="{{ asset('assets/svg/icons/wizard-checkout-payment.svg') }}">
                  </use>
                </svg>
              </span>
              <span class="bs-stepper-label">Company Info</span>
            </button>
          </div>
          <div class="line">
            <i class="icon-base ti tabler-chevron-right"></i>
          </div>
          <div class="step" data-target="#director-info">
            <button type="button" class="step-trigger px-0">
              <span class="bs-stepper-icon">
                <svg viewBox="0 0 58 50">
                  <use xlink:href="{{ asset('assets/svg/icons/form-wizard-personal.svg#wizardPersonal') }}">
                  </use>
                </svg>
              </span>
              <span class="bs-stepper-label">Director Info</span>
            </button>
          </div>
          <div class="line">
            <i class="icon-base ti tabler-chevron-right"></i>
          </div>
          <div class="step" data-target="#user-services">
            <button type="button" class="step-trigger px-0">
              <span class="bs-stepper-icon">
                <svg viewBox="0 0 54 54">
                  <use xlink:href="{{ asset('assets/svg/icons/form-wizard-social-link.svg#wizardSocialLink') }}">
                  </use>
                </svg>
              </span>
              <span class="bs-stepper-label">User Services</span>
            </button>
          </div>
          <div class="line">
            <i class="icon-base ti tabler-chevron-right"></i>
          </div>
          <div class="step" data-target="#user_wallet">
            <button type="button" class="step-trigger px-0">
              <span class="bs-stepper-icon">
                <svg viewBox="0 0 54 54">
                  <use xlink:href="{{ asset('assets/svg/icons/form-wizard-address.svg#wizardAddress') }}">
                  </use>
                </svg>
              </span>
              <span class="bs-stepper-label">User Wallet</span>
            </button>
          </div>
          <div class="line">
            <i class="icon-base ti tabler-chevron-right"></i>
          </div>
          <div class="step" data-target="#user_api_keys">
            <button type="button" class="step-trigger px-0 ">
              <span class="bs-stepper-icon">
                <svg viewBox="0 0 54 54">
                  <use xlink:href="{{ asset('assets/svg/icons/form-wizard-submit.svg#wizardSubmit') }}">
                  </use>
                </svg>
              </span>
              <span class="bs-stepper-label">Developer Options</span>
            </button>
          </div>
        </div>
        <div class="bs-stepper-content">
          <form method="POST" action="{{ route('admin.user.update.basic', $user->id) }}">
            @csrf
            <!-- Basic Details -->
            <div id="basic-details" class="content">
              <div class="content-header mb-4">
                <h6 class="mb-0">Basic Details</h6>
                <small>Basic login & contact info</small>
              </div>

              <div class="row g-4">
                <div class="col-md-6">
                  <label class="form-label">Company Name</label>
                  <input type="text" name="company_name" value="{{ old('company_name', $user->company_name) }}"
                    placeholder="Enter Company Name" class="form-control">
                  @error('company_name')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label">Email</label>
                  <input type="email" name="email" value="{{ old('email', $user->email) }}"
                    placeholder="Enter Email Address" class="form-control">
                  @error('email')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label">Mobile Number</label>
                  <input type="text" name="mobile_number" value="{{ old('mobile_number', $user->mobile_number) }}"
                    placeholder="Enter Mobile Number" class="form-control" maxlength="10">
                  @error('mobile_number')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <div class="form-password-toggle">
                    <label class="form-label" for="basic-default-password12">Password</label>
                    <div class="input-group">
                      <input type="password" class="form-control" value="{{ old('password') }}" name="password"
                        placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                        aria-describedby="basic-default-password2" />
                      <span id="basic-default-password2" class="input-group-text cursor-pointer"><i
                          class="icon-base ti tabler-eye-off"></i></span>
                    </div>
                    @error('password')
                      <span class="text-danger">{{ $message }}</span>
                    @enderror
                  </div>
                </div>

                <div class="col-md-6">
                  <div class="form-password-toggle">
                    <label class="form-label" for="basic-default-password12">Confirm Password</label>
                    <div class="input-group">
                      <input type="password" class="form-control" value="{{ old('password_confirmation') }}"
                        name="password_confirmation"
                        placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                        aria-describedby="basic-default-password2" />
                      <span id="basic-default-password2" class="input-group-text cursor-pointer"><i
                          class="icon-base ti tabler-eye-off"></i></span>
                    </div>
                    @error('password_confirmation')
                      <span class="text-danger">{{ $message }}</span>
                    @enderror
                  </div>
                </div>
                <div class="col-12 d-flex justify-content-between">
                  <div class="col-12 d-flex justify-content-between">
                    <button class="btn btn-primary ms-auto">Save</button>
                  </div>
                </div>
              </div>
            </div>
          </form>
          <form method="POST" action="{{ route('admin.user.update.company', $user->id) }}"
            enctype="multipart/form-data">
            @csrf
            <!-- Company Info -->
            <div id="company-info" class="content">
              <div class="content-header mb-4">
                <h6 class="mb-0">Company Details</h6>
                <small>Business information</small>
              </div>

              <div class="row g-4">
                <div class="col-md-6">
                  <label class="form-label">Company Type</label>
                  <select name="company_type" class="form-select">
                    <option value="private_limited"
                      {{ old('company_type', $user->companyDetail->company_type) == 'private_limited' ? 'selected' : '' }}>
                      Private Limited</option>
                    <option value="one_person_company"
                      {{ old('company_type', $user->companyDetail->company_type) == 'one_person_company' ? 'selected' : '' }}>
                      One Person Company</option>
                    <option value="limited_liability_partnership"
                      {{ old('company_type', $user->companyDetail->company_type) == 'limited_liability_partnership' ? 'selected' : '' }}>
                      Limited Liability Partnership</option>
                    <option value="public_limited"
                      {{ old('company_type', $user->companyDetail->company_type) == 'public_limited' ? 'selected' : '' }}>
                      Public Limited</option>
                  </select>
                </div>

                <div class="col-md-6">
                  <label class="form-label">GST Number (Optional)</label>
                  <input type="text" name="gst_no" value="{{ old('gst_no', $gstNo) }}"
                    placeholder="Enter GST Number" class="form-control">
                  @error('gst_no')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label">GST Certificate</label>
                  <input type="file" name="gst_image" class="form-control">
                  @if ($gstImage)
                    <img src="{{ $gstImage }}" alt="GST Image" style="max-width: 100px; max-height: 100px;">
                  @endif
                  @error('gst_image')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label">CIN (Optional)</label>
                  <input type="text" name="cin" placeholder="Enter CIN" class="form-control"
                    value="{{ old('cin', $cin) }}">
                  @error('cin')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label">CIN Certificate</label>
                  <input type="file" name="cin_image" class="form-control">
                  @if ($cinImage)
                    <img src="{{ $cinImage }}" alt="CIN Image" style="max-width: 100px; max-height: 100px;">
                  @endif
                  @error('cin_image')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label">PAN (Optional)</label>
                  <input type="text" name="pan" placeholder="Enter PAN" class="form-control"
                    value="{{ old('pan', $pan) }}">
                  @error('pan')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label">PAN Certificate</label>
                  <input type="file" name="pan_image" class="form-control">
                  @if ($panImage)
                    <img src="{{ $panImage }}" alt="PAN Image" style="max-width: 100px; max-height: 100px;">
                  @endif
                  @error('pan_image')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label">Udhyam Number (Optional)</label>
                  <input type="text" name="udhyam_number" placeholder="Enter Udhyam Number" class="form-control"
                    value="{{ old('udhyam_number', $udhyamNumber) }}">
                  @error('udhyam_number')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label">Udhyam Certificate</label>
                  <input type="file" name="udhyam_image" class="form-control">
                  @if ($udhyamImage)
                    <img src="{{ $udhyamImage }}" alt="Udhyam Image" style="max-width: 100px; max-height: 100px;">
                  @endif
                  @error('udhyam_image')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label">MOA Certificate</label>
                  <input type="file" name="moa_image" class="form-control">
                  @if ($moaImage)
                    <img src="{{ $moaImage }}" alt="MOA Image" style="max-width: 100px; max-height: 100px;">
                  @endif
                  @error('moa_image')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label">BR Certificate</label>
                  <input type="file" name="br_image" class="form-control">
                  @if ($brImage)
                    <img src="{{ $brImage }}" alt="BR Image" style="max-width: 100px; max-height: 100px;">
                  @endif
                  @error('br_image')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label">Company Address</label>
                  <textarea name="address" placeholder="Enter Company Address" class="form-control">{{ old('address', $user->companyDetail->address) }}</textarea>
                  @error('address')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>
                <div class="col-12 d-flex justify-content-between">
                  <div class="col-12 d-flex justify-content-between">
                    <button class="btn btn-primary ms-auto">Save</button>
                  </div>
                </div>
              </div>
            </div>
          </form>
          <form method="POST" action="{{ route('admin.user.update.director', $user->id) }}"
            enctype="multipart/form-data">
            @csrf
            <!-- Director Details -->
            <div id="director-info" class="content">
              <div class="content-header mb-4">
                <h6 class="mb-0">Director Details</h6>
                <small>KYC Information</small>
              </div>

              <div class="row g-4">
                <div class="col-md-6">
                  <label class="form-label">Director Name</label>
                  <input type="text" name="director_name"
                    value="{{ old('director_name', $user->companyDetail->director_name) }}"
                    placeholder="Enter Director Name" class="form-control">
                  @error('director_name')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label">Director Email</label>
                  <input type="email" name="director_email"
                    value="{{ old('director_email', $user->companyDetail->director_email) }}"
                    placeholder="Enter Director Email" class="form-control">
                  @error('director_email')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label">Director Mobile Number</label>
                  <input type="text" name="director_mobile"
                    value="{{ old('director_mobile', $user->companyDetail->director_mobile) }}"
                    placeholder="Enter Director Mobile Number" class="form-control">
                  @error('director_mobile')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label">Aadhaar Number</label>
                  <input type="text" name="director_aadhar_no"
                    value="{{ old('director_aadhar_no', $directorAadharNo) }}" placeholder="Enter Aadhaar Number"
                    class="form-control">
                  @error('director_aadhar_no')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label">Aadhaar Image</label>
                  <input type="file" name="director_aadhar_image" class="form-control">
                  @if ($directorAadharImage)
                    <img src="{{ $directorAadharImage }}" alt="Director Aadhaar Image"
                      style="max-width: 100px; max-height: 100px;">
                  @endif
                  @error('director_aadhar_image')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label">PAN Number</label>
                  <input type="text" name="director_pan_no" value="{{ old('director_pan_no', $directorPanNo) }}"
                    placeholder="Enter PAN Number" class="form-control">
                  @error('director_pan_no')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label">PAN Image</label>
                  <input type="file" name="director_pan_image" class="form-control">
                  @if ($directorPanImage)
                    <img src="{{ $directorPanImage }}" alt="Director PAN Image"
                      style="max-width: 100px; max-height: 100px;">
                  @endif
                  @error('director_pan_image')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>
                <div class="col-12 d-flex justify-content-between">
                  <div class="col-12 d-flex justify-content-between">
                    <button class="btn btn-primary ms-auto">Save</button>
                  </div>
                </div>
              </div>
            </div>
          </form>
          <form method="POST" action="{{ route('admin.user.update.services', $user->id) }}">
            @csrf
            <!-- User Services -->
            <div id="user-services" class="content">
              <div class="content-header mb-4">
                <h6 class="mb-0">Service Configuration</h6>
                <small>Enable services</small>
              </div>
              <div class="row g-4">
                <div class="col-12">
                  <h6 class="fw-bold">Payout Configuration</h6>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Wallet Type</label>
                  <select name="wallet_type" class="form-select">
                    <option value="payout_wallet"
                      {{ old('wallet_type', $user->services->wallet_type) == 'payout_wallet' ? 'selected' : '' }}>
                      Payout Wallet</option>
                    <option value="virtual_wallet"
                      {{ old('wallet_type', $user->services->wallet_type) == 'virtual_wallet' ? 'selected' : '' }}>
                      Virtual Wallet
                    </option>
                  </select>
                  @error('wallet_type')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6 virtual-wallet">
                  <label class="form-label">Virtual Charges</label>
                  <input type="number" name="virtual_charges"
                    value="{{ old('virtual_charges', $user->services->virtual_charges) }}" class="form-control">
                  @error('virtual_charges')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6 virtual-wallet">
                  <label class="form-label">Platform Fee</label>
                  <input type="number" name="platform_fee"
                    value="{{ old('platform_fee', $user->services->platform_fee) }}" class="form-control">
                  @error('platform_fee')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6 payout-wallet">
                  <label class="form-label">Payout Charges</label>
                  <input type="number" name="payout_charges"
                    value="{{ old('payout_charges', $user->services->payout_charges) }}" class="form-control">
                  @error('payout_charges')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label">Virtual Type</label>
                  <select name="virtual_type" class="form-select">
                    <option value="percentage"
                      {{ old('virtual_type', $user->services->virtual_type) == 'percentage' ? 'selected' : '' }}>
                      Percentage</option>
                    <option value="flat_rate"
                      {{ old('virtual_type', $user->services->virtual_type) == 'flat_rate' ? 'selected' : '' }}>Flat Rate
                    </option>
                  </select>
                  @error('virtual_type')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6 virtual-percentage">
                  <label class="form-label">Percentage Flat Charges <small class="text-danger">(up to
                      500)</small></label>
                  <input type="number" name="pflat_charges"
                    value="{{ old('pflat_charges', $user->services->pflat_charges) }}" class="form-control">
                  @error('pflat_charges')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6 virtual-percentage">
                  <label class="form-label">Percentage Flat Charges 2 <small class="text-danger">(500 to
                      1000)</small></label>
                  <input type="number" name="pflat_charges_2"
                    value="{{ old('pflat_charges_2', $user->services->pflat_charges_2) }}" class="form-control">
                  @error('pflat_charges_2')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6 virtual-flat">
                  <label class="form-label">Payout Slab Charges <small class="text-danger">(up to 1000)</small></label>
                  <input type="number" name="pslab_1000" value="{{ old('pslab_1000', $user->services->pslab_1000) }}"
                    class="form-control">
                  @error('pslab_1000')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6 virtual-flat">
                  <label class="form-label">Payout Slab Charges <small class="text-danger">(1000 to
                      25000)</small></label>
                  <input type="number" name="pslab_25000"
                    value="{{ old('pslab_25000', $user->services->pslab_25000) }}" class="form-control">
                  @error('pslab_25000')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6 virtual-flat">
                  <label class="form-label">Payout Slab Charges <small class="text-danger">(25000 to
                      200000)</small></label>
                  <input type="number" name="pslab_200000"
                    value="{{ old('pslab_200000', $user->services->pslab_200000) }}" class="form-control">
                  @error('pslab_200000')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label">Active Payout Pipe</label>
                  <select name="active_payout_api" id="active_payout_api" class="form-control">
                    @foreach (config('constant.ACTIVE_PIPE_TYPE') as $key => $value)
                      <option value="{{ $key }}"
                        {{ old('active_payout_api', $user->services->active_payout_api ?? '') == $key ? 'selected' : '' }}>
                        {{ $value }}
                      </option>
                    @endforeach
                  </select>
                  @error('active_payout_api')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>


                <div class="col-md-6">
                  <label class="form-label">Payout Minimum Transaction</label>
                  <input type="number" name="minimum_transaction"
                    value="{{ old('minimum_transaction', $user->services->minimum_transaction) }}"
                    class="form-control">
                  @error('minimum_transaction')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label">Payout Maximum Transaction</label>
                  <input type="number" name="maximum_transaction"
                    value="{{ old('maximum_transaction', $user->services->maximum_transaction) }}"
                    class="form-control">
                  @error('maximum_transaction')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label">Payout Status</label>
                  <select name="payout_status" class="form-select">
                    <option value="A"
                      {{ old('payout_status', $user->services->payout_status) == 'A' ? 'selected' : '' }}>Active</option>
                    <option value="B"
                      {{ old('payout_status', $user->services->payout_status) == 'B' ? 'selected' : '' }}>Blocked
                    </option>
                  </select>
                  @error('payout_status')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-12 pt-4">
                  <h6 class="fw-bold">Payin Configuration</h6>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Payin Charges</label>
                  <input type="number" name="payin_charges"
                    value="{{ old('payin_charges', $user->services->payin_charges) }}" class="form-control">
                  @error('payin_charges')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label">Active Payin Pipe</label>
                  <select name="active_payin_api" id="active_payin_api" class="form-control">
                    @foreach (config('constant.ACTIVE_PIPE_TYPE') as $key => $value)
                      <option value="{{ $key }}"
                        {{ old('active_payin_api', $user->services->active_payin_api ?? '') == $key ? 'selected' : '' }}>
                        {{ $value }}
                      </option>
                    @endforeach
                  </select>
                  @error('active_payin_api')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label">Payin Minimum Transaction</label>
                  <input type="number" name="payin_minimum_transaction"
                    value="{{ old('payin_minimum_transaction', $user->services->payin_minimum_transaction) }}"
                    class="form-control">
                  @error('payin_minimum_transaction')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label">Payin Maximum Transaction</label>
                  <input type="number" name="payin_maximum_transaction"
                    value="{{ old('payin_maximum_transaction', $user->services->payin_maximum_transaction) }}"
                    class="form-control">
                  @error('payin_maximum_transaction')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label">Payin Status</label>
                  <select name="payin_status" class="form-select">
                    <option value="A"
                      {{ old('payin_status', $user->services->payin_status) == 'A' ? 'selected' : '' }}>Active</option>
                    <option value="B"
                      {{ old('payin_status', $user->services->payin_status) == 'B' ? 'selected' : '' }}>Blocked</option>
                  </select>
                  @error('payin_status')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label">Payout Service Enable</label>
                  <select name="payout_service_enable" class="form-select">
                    <option value="A"
                      {{ old('payout_service_enable', $user->services->payout_service_enable) == 'A' ? 'selected' : '' }}>
                      Yes</option>
                    <option value="B"
                      {{ old('payout_service_enable', $user->services->payout_service_enable) == 'B' ? 'selected' : '' }}>
                      No</option>
                  </select>
                  @error('payout_service_enable') 
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label">Load Money Service Enable</label>
                  <select name="load_money_service_enable" class="form-select">
                    <option value="A"
                      {{ old('load_money_service_enable', $user->services->load_money_service_enable) == 'A' ? 'selected' : '' }}>
                      Yes</option>
                    <option value="B"
                      {{ old('load_money_service_enable', $user->services->load_money_service_enable) == 'B' ? 'selected' : '' }}>
                      No</option>
                  </select>
                  @error('load_money_service_enable')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label">Bill Payment Service Enable</label>
                  <select name="bill_payment_service_enable" class="form-select">
                    <option value="A"
                      {{ old('bill_payment_service_enable', $user->services->bill_payment_service_enable) == 'A' ? 'selected' : '' }}>
                      Yes</option>
                    <option value="B"
                      {{ old('bill_payment_service_enable', $user->services->bill_payment_service_enable) == 'B' ? 'selected' : '' }}>
                      No</option>
                  </select>
                  @error('bill_payment_service_enable')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label">BBPS Charges</label>
                  <input type="number" name="bbps_charges"
                    value="{{ old('bbps_charges', $user->services->bbps_charges) }}" class="form-control">
                  @error('bbps_charges')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>

                <div class="col-12 d-flex justify-content-between">
                  <div class="col-12 d-flex justify-content-between">
                    <button class="btn btn-primary ms-auto">Save</button>
                  </div>
                </div>
              </div>
            </div>
          </form>
          <!-- User API Keys -->
          <div id="user_wallet" class="content">
            <div class="content-header mb-4">
              <h6 class="mb-0">Wallet</h6>
            </div>

            <div class="row g-4">
              <div class="col-md-6">
                <div class="card shadow-sm">
                  <div class="card-header">Freeze Balance</div>

                  <div class="card-body">
                    <p class="text-end text-muted">
                      Current: ₹{{ number_format($user->freeze_balance, 2) }}
                    </p>

                    <input type="number" class="form-control mb-3" id="freeze_amount"
                      placeholder="Enter Freeze Balance">

                    <div class="d-flex gap-2">
                      <button class="btn btn-success w-50 wallet-btn" onclick="updateWallet(this,'freeze','add')">
                        <span class="btn-text">ADD FREEZE</span>
                        <span class="spinner-border spinner-border-sm d-none"></span>
                      </button>

                      <button class="btn btn-warning w-50 wallet-btn" onclick="updateWallet(this,'freeze','release')">
                        <span class="btn-text">RELEASE</span>
                        <span class="spinner-border spinner-border-sm d-none"></span>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="card shadow-sm">
                  <div class="card-header">Reserve Balance</div>
                  <div class="card-body">
                    <p class="text-end text-muted">
                      Current: ₹{{ number_format($user->reserve_balance, 2) }}
                    </p>

                    <input type="number" class="form-control mb-3" id="reserve_amount"
                      placeholder="Enter Reserve Balance">

                    <div class="d-flex gap-2">
                      <button class="btn btn-success w-50 wallet-btn" onclick="updateWallet(this,'reserve','add')">
                        <span class="btn-text">ADD FREEZE</span>
                        <span class="spinner-border spinner-border-sm d-none"></span>
                      </button>
                      <button class="btn btn-warning w-50 wallet-btn" onclick="updateWallet(this,'reserve','release')">
                        <span class="btn-text">RELEASE</span>
                        <span class="spinner-border spinner-border-sm d-none"></span>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="card shadow-sm">
                  <div class="card-header">Payout Wallet</div>

                  <div class="card-body">
                    <p class="text-end text-muted">
                      Current: ₹{{ number_format($user->payout_balance, 2) }}
                    </p>

                    <input type="number" class="form-control mb-3" id="payout_amount" placeholder="Enter Amount">

                    <div class="d-flex gap-2">
                      <button class="btn btn-primary w-50 wallet-btn" onclick="updateWallet(this,'payout','credit')">
                        <span class="btn-text">CREDIT</span>
                        <span class="spinner-border spinner-border-sm d-none"></span>
                      </button>

                      <button class="btn btn-danger w-50 wallet-btn" onclick="updateWallet(this,'payout','debit')">
                        <span class="btn-text">DEBIT</span>
                        <span class="spinner-border spinner-border-sm d-none"></span>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="card shadow-sm">
                  <div class="card-header">Payin Wallet</div>

                  <div class="card-body">
                    <p class="text-end text-muted">
                      Current: ₹{{ number_format($user->payin_balance, 2) }}
                    </p>

                    <input type="number" class="form-control mb-3" id="payin_amount" placeholder="Enter Amount">

                    <div class="d-flex gap-2">
                      <button class="btn btn-primary w-50 wallet-btn" onclick="updateWallet(this,'payin','credit')">
                        <span class="btn-text">CREDIT</span>
                        <span class="spinner-border spinner-border-sm d-none"></span>
                      </button>

                      <button class="btn btn-danger w-50 wallet-btn" onclick="updateWallet(this,'payin','debit')">
                        <span class="btn-text">DEBIT</span>
                        <span class="spinner-border spinner-border-sm d-none"></span>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="card shadow-sm">
                  <div class="card-header">Virtual Wallet</div>

                  <div class="card-body">
                    <p class="text-end text-muted">
                      Current: ₹{{ number_format($user->virtual_balance, 2) }}
                    </p>

                    <input type="number" class="form-control mb-3" id="virtual_amount" placeholder="Enter Amount">

                    <div class="d-flex gap-2">
                      <button class="btn btn-primary w-50 wallet-btn" onclick="updateWallet(this,'virtual','credit')">
                        <span class="btn-text">CREDIT</span>
                        <span class="spinner-border spinner-border-sm d-none"></span>
                      </button>

                      <button class="btn btn-danger w-50 wallet-btn" onclick="updateWallet(this,'virtual','debit')">
                        <span class="btn-text">DEBIT</span>
                        <span class="spinner-border spinner-border-sm d-none"></span>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- User API Keys -->
          <div id="user_api_keys" class="content">
            <h6 class="mb-3 fw-bold">API Keys</h6>
            <div class="row g-4">
              {{-- ===== API KEY GENERATION ===== --}}
              <div class="col-12">
                <table class="table table-bordered align-middle mb-0">
                  <tbody>
                    {{-- Generate Button --}}
                    <tr>
                      <td style="width: 30%">Generate API Keys</td>
                      <td>
                        <button type="button" class="btn btn-primary" id="generateApiKey"
                          data-url="{{ route('admin.user.api.generate', $user->id) }}">
                          Generate API Key
                        </button>
                      </td>
                    </tr>

                    {{-- Client Key --}}
                    <tr>
                      <td>Client Key</td>
                      <td>
                        <div class="d-flex align-items-center gap-2">
                          <div class="input-group">
                            <input type="text" readonly id="client_key" class="form-control"
                              value="{{ $clientKey }}" data-full="{{ $clientKey }}">
                            <button type="button" class="btn btn-outline-secondary btn-sm copy-btn"
                              data-copy="client_key">
                              <i class="fa fa-copy"></i>
                            </button>
                          </div>
                        </div>
                      </td>
                    </tr>

                    {{-- Client Secret --}}
                    <tr>
                      <td>Client Secret</td>
                      <td>
                        <div class="d-flex align-items-center gap-2">
                          <div class="input-group">
                            <input type="text" readonly id="client_secret" class="form-control"
                              value="{{ $maskedSecret }}" data-full="{{ $clientSecret }}"
                              data-masked="{{ $maskedSecret }}">
                            <button type="button" class="btn btn-outline-secondary btn-sm toggle-secret">
                              <i class="fa fa-eye"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm copy-btn"
                              data-copy="client_secret">
                              <i class="fa fa-copy"></i>
                            </button>
                          </div>
                        </div>
                      </td>
                    </tr>

                  </tbody>
                </table>
              </div>
            </div>
            <form method="POST" action="{{ route('admin.user.update.api', $user->id) }}">
              @csrf
              <hr>
              <h6 class="mb-3 fw-bold">Webhook Configuration</h6>
              <div class="row mb-3 align-items-center">
                <label class="col-md-4 col-form-label">Payin Webhook URL</label>
                <div class="col-md-8">
                  <input type="url" name="payin_webhooks" placeholder="Enter Payin Webhook URL"
                    value="{{ old('payin_webhooks', $user->apiKey->payin_webhooks ?? '') }}" class="form-control">
                  @error('payin_webhooks')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>
              </div>

              <div class="row mb-3 align-items-center">
                <label class="col-md-4 col-form-label">Payout Webhook URL</label>
                <div class="col-md-8">
                  <input type="url" name="payout_webhooks" placeholder="Enter Payout Webhook URL"
                    value="{{ old('payout_webhooks', $user->apiKey->payout_webhooks ?? '') }}" class="form-control">
                  @error('payout_webhooks')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>
              </div>
              <hr>
              <div class="row">
                <div class="col-md-4">
                  <h6 class="mb-3 fw-bold">Whitelisted IPs</h6>
                </div>
                <div class="col-md-8">
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
              </div>
              <hr>
              <h6 class="mb-3 fw-bold">Bulkpe Configuration</h6>
              <div class="row mb-3 align-items-center">
                <label class="col-md-4 col-form-label">Auth Token</label>
                <div class="col-md-8">
                  <div class="input-group">
                    <input type="text" name="bulkpe_auth_token" placeholder="Enter Auth Token"
                      value="{{ old('bulkpe_auth_token', $user->apiKey->bulkpe_auth_token ?? '') }}"
                      class="form-control" id="bulkpe_auth_token" data-full="{{ $user->apiKey->bulkpe_auth_token }}">
                    <button type="button" class="btn btn-outline-secondary btn-sm copy-btn"
                      data-copy="bulkpe_auth_token">
                      <i class="fa fa-copy"></i>
                    </button>
                  </div>
                  @error('bulkpe_auth_token')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>
              </div>
              <hr>
              <h6 class="mb-3 fw-bold">Razorpay</h6>
              <div class="row mb-3 align-items-center">
                <label class="col-md-4 col-form-label">Account Number</label>
                <div class="col-md-8">
                  <input type="text" name="razorpay_account_number" placeholder="Enter Account Number"
                    value="{{ old('razorpay_account_number', $user->apiKey->razorpay_account_number ?? '') }}"
                    class="form-control">
                  @error('razorpay_account_number')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>
              </div>
              <div class="row mb-3 align-items-center">
                <label class="col-md-4 col-form-label">API Key</label>
                <div class="col-md-8">
                  <div class="input-group">
                    <input type="text" name="razorpay_api_key" placeholder="Enter Razorpay API Key"
                      value="{{ old('razorpay_api_key', $user->apiKey->razorpay_api_key ?? '') }}"
                      class="form-control" id="razorpay_api_key" data-full="{{ $user->apiKey->razorpay_api_key }}">
                    <button type="button" class="btn btn-outline-secondary btn-sm copy-btn"
                      data-copy="razorpay_api_key">
                      <i class="fa fa-copy"></i>
                    </button>
                  </div>
                  @error('razorpay_api_key')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>
              </div>
              <div class="row mb-3 align-items-center">
                <label class="col-md-4 col-form-label">Secret Key</label>
                <div class="col-md-8">
                  <div class="input-group">
                    <input type="text" name="razorpay_secret_key" placeholder="Enter Razorpay Secret Key"
                      value="{{ old('razorpay_secret_key', $user->apiKey->razorpay_secret_key ?? '') }}"
                      class="form-control" id="razorpay_secret_key"
                      data-full="{{ $user->apiKey->razorpay_secret_key }}">
                    <button type="button" class="btn btn-outline-secondary btn-sm copy-btn"
                      data-copy="razorpay_secret_key">
                      <i class="fa fa-copy"></i>
                    </button>
                  </div>
                  @error('razorpay_secret_key')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>
              </div>
              <hr>
              <h6 class="mb-3 fw-bold">Paywize</h6>
              <div class="row mb-3 align-items-center">
                <label class="col-md-4 col-form-label">API Key</label>
                <div class="col-md-8">
                  <div class="input-group">
                    <input type="text" name="paywize_api_key" placeholder="Enter Paywize API Key"
                      value="{{ old('paywize_api_key', $user->apiKey->paywize_api_key ?? '') }}" class="form-control"
                      id="paywize_api_key" data-full="{{ $user->apiKey->paywize_api_key }}">
                    <button type="button" class="btn btn-outline-secondary btn-sm copy-btn"
                      data-copy="paywize_api_key">
                      <i class="fa fa-copy"></i>
                    </button>
                  </div>
                  @error('paywize_api_key')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>
              </div>
              <div class="row mb-3 align-items-center">
                <label class="col-md-4 col-form-label">Secret Key</label>
                <div class="col-md-8">
                  <div class="input-group">
                    <input type="text" name="paywize_secret_key" placeholder="Enter Paywize Secret Key"
                      value="{{ old('paywize_secret_key', $user->apiKey->paywize_secret_key ?? '') }}"
                      class="form-control" id="paywize_secret_key"
                      data-full="{{ $user->apiKey->paywize_secret_key }}">
                    <button type="button" class="btn btn-outline-secondary btn-sm copy-btn"
                      data-copy="paywize_secret_key">
                      <i class="fa fa-copy"></i>
                    </button>
                  </div>
                  @error('paywize_secret_key')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>
              </div>
              <hr>
              <h6 class="mb-3 fw-bold">Buckbox</h6>
              <div class="row mb-3 g-3 align-items-center">
                <div class="col-md-6">
                  <label class="form-label">Merchant ID</label>
                  <input type="text" name="buckbox_merchant_id" placeholder="Enter Merchant ID"
                    value="{{ old('buckbox_merchant_id', $user->apiKey->buckbox_merchant_id ?? '') }}"
                    class="form-control">
                  @error('buckbox_merchant_id')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>
                <div class="col-md-6">
                  <label class="form-label">Merchant Name</label>
                  <input type="text" name="buckbox_merchant_name" placeholder="Enter Merchant Name"
                    value="{{ old('buckbox_merchant_name', $user->apiKey->buckbox_merchant_name ?? '') }}"
                    class="form-control">
                  @error('buckbox_merchant_name')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>
                <div class="col-md-6">
                  <label class="form-label">Merchant Email</label>
                  <input type="text" name="buckbox_merchant_email" placeholder="Enter Merchant Email"
                    value="{{ old('buckbox_merchant_email', $user->apiKey->buckbox_merchant_email ?? '') }}"
                    class="form-control">
                  @error('buckbox_merchant_email')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>
                <div class="col-md-6">
                  <label class="form-label">API Key</label>
                  <div class="input-group">
                    <input type="text" name="buckbox_api_key" placeholder="Enter Buckbox API Key"
                      value="{{ old('buckbox_api_key', $user->apiKey->buckbox_api_key ?? '') }}" class="form-control"
                      id="buckbox_api_key" data-full="{{ $user->apiKey->buckbox_api_key }}">
                    <button type="button" class="btn btn-outline-secondary btn-sm copy-btn"
                      data-copy="buckbox_api_key">
                      <i class="fa fa-copy"></i>
                    </button>
                  </div>
                  @error('buckbox_api_key')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>
                <div class="col-md-6">
                  <label class="form-label">Secret Key</label>
                  <div class="input-group">
                    <input type="text" name="buckbox_secret_key" placeholder="Enter Buckbox Secret Key"
                      value="{{ old('buckbox_secret_key', $user->apiKey->buckbox_secret_key ?? '') }}"
                      class="form-control" id="buckbox_secret_key"
                      data-full="{{ $user->apiKey->buckbox_secret_key }}">
                    <button type="button" class="btn btn-outline-secondary btn-sm copy-btn"
                      data-copy="buckbox_secret_key">
                      <i class="fa fa-copy"></i>
                    </button>
                  </div>
                  @error('buckbox_secret_key')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>
                <div class="col-md-6">
                  <label class="form-label">ENY Key</label>
                  <div class="input-group">
                    <input type="text" name="buckbox_eny_key" placeholder="Enter Buckbox ENY Key"
                      value="{{ old('buckbox_eny_key', $user->apiKey->buckbox_eny_key ?? '') }}" class="form-control"
                      id="buckbox_eny_key" data-full="{{ $user->apiKey->buckbox_eny_key }}">
                    <button type="button" class="btn btn-outline-secondary btn-sm copy-btn"
                      data-copy="buckbox_eny_key">
                      <i class="fa fa-copy"></i>
                    </button>
                  </div>
                  @error('buckbox_eny_key')
                    <span class="text-danger">{{ $message }}</span>
                  @enderror
                </div>
              </div>
              <div class="row">
                <div class="col-12 text-end">
                  <button class="btn btn-success" type="submit">Save</button>
                </div>
              </div>
            </form>
          </div>
          </form>
        </div>
      </div>
    </div>
    <!-- /Default Icons Wizard -->
  </div>
  <div class="modal fade" id="walletOtpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
      <div class="modal-content">

        <div class="modal-header">
          <h5 class="modal-title">Confirm Wallet Update</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">OTP <span class="text-danger">*</span></label>
            <input type="password" class="form-control" id="walletOtpInput" placeholder="Enter OTP"
              inputmode="numeric" maxlength="6" autocomplete="one-time-code" />
            <div class="invalid-feedback" id="walletOtpError">OTP is required</div>
          </div>

          <div class="mb-0">
            <label class="form-label">Description (optional)</label>
            <textarea class="form-control" id="walletDescInput" rows="2" placeholder="Add note (optional)"></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" id="walletOtpConfirmBtn">
            Confirm
          </button>
        </div>

      </div>
    </div>
  </div>

@endsection
