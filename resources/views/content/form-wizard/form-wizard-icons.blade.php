@extends('layouts/layoutMaster')

@section('title', 'Wizard Icons - Forms')

<!-- Vendor Styles -->
@section('vendor-style')
@vite(['resources/assets/vendor/libs/bs-stepper/bs-stepper.scss', 'resources/assets/vendor/libs/bootstrap-select/bootstrap-select.scss', 'resources/assets/vendor/libs/select2/select2.scss'])
@endsection

<!-- Vendor Scripts -->
@section('vendor-script')
@vite(['resources/assets/vendor/libs/bs-stepper/bs-stepper.js', 'resources/assets/vendor/libs/bootstrap-select/bootstrap-select.js', 'resources/assets/vendor/libs/select2/select2.js'])
@endsection

<!-- Page Scripts -->
@section('page-script')
@vite(['resources/assets/js/form-wizard-icons.js'])
@endsection

@section('content')
<!-- Default -->
<div class="row">
  <div class="col-12">
    <h5>Default</h5>
  </div>

  <!-- Default Icons Wizard -->
  <div class="col-12 mb-6">
    <div class="bs-stepper wizard-icons wizard-icons-example mt-2">
      <div class="bs-stepper-header">
        <div class="step" data-target="#basic-details">
          <button type="button" class="step-trigger">
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
          <button type="button" class="step-trigger">
            <span class="bs-stepper-icon">
              <svg viewBox="0 0 58 54">
                <use xlink:href="{{ asset('assets/svg/icons/form-wizard-personal.svg#wizardPersonal') }}">
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
          <button type="button" class="step-trigger">
            <span class="bs-stepper-icon">
              <svg viewBox="0 0 54 54">
                <use xlink:href="{{ asset('assets/svg/icons/form-wizard-address.svg#wizardAddress') }}">
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
          <button type="button" class="step-trigger">
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
        <div class="step" data-target="#user_api_keys">
          <button type="button" class="step-trigger">
            <span class="bs-stepper-icon">
              <svg viewBox="0 0 54 54">
                <use xlink:href="{{ asset('assets/svg/icons/form-wizard-submit.svg#wizardSubmit') }}">
                </use>
              </svg>
            </span>
            <span class="bs-stepper-label">User API Keys</span>
          </button>
        </div>
      </div>
      <div class="bs-stepper-content">
        <form onSubmit="return false">
          <!-- Basic Details -->
          <div id="basic-details" class="content">
            <div class="content-header mb-4">
              <h6 class="mb-0">Basic Details</h6>
              <small>Basic login & contact info</small>
            </div>

            <div class="row g-4">
              <div class="col-md-6">
                <label class="form-label">Company Name</label>
                <input type="text" name="company_name" placeholder="Enter Company Name" class="form-control" required>
              </div>

              <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" placeholder="Enter Email Address" class="form-control" required>
              </div>

              <div class="col-md-6">
                <label class="form-label">Mobile Number</label>
                <input type="text" name="mobile_number" placeholder="Enter Mobile Number" class="form-control" maxlength="10" required>
              </div>

              <div class="col-md-6">
                <div class="form-password-toggle">
                  <label class="form-label" for="basic-default-password12">Password</label>
                  <div class="input-group">
                    <input type="password" class="form-control" name="password"
                      placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                      aria-describedby="basic-default-password2" />
                    <span id="basic-default-password2" class="input-group-text cursor-pointer"><i
                        class="icon-base ti tabler-eye-off"></i></span>
                  </div>
                </div>
              </div>

              <div class="col-md-6">
                <div class="form-password-toggle">
                  <label class="form-label" for="basic-default-password12">Confirm Password</label>
                  <div class="input-group">
                    <input type="password" class="form-control" name="password_confirmation"
                      placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                      aria-describedby="basic-default-password2" />
                    <span id="basic-default-password2" class="input-group-text cursor-pointer"><i
                        class="icon-base ti tabler-eye-off"></i></span>
                  </div>
                </div>
              </div>

              <div class="col-md-6">
                <label class="form-label">Payout Balance</label>
                <input type="text" name="payout_balance" placeholder="Enter Payout Balance" class="form-control" maxlength="10" required>
              </div>

              <div class="col-md-6">
                <label class="form-label">Payin Balance</label>
                <input type="text" name="payin_balance" placeholder="Enter Payin Balance" class="form-control" maxlength="10" required>
              </div>

              <div class="col-md-6">
                <label class="form-label">Reserve Balance</label>
                <input type="text" name="reserve_balance" placeholder="Enter Reserve Balance" class="form-control" maxlength="10" required>
              </div>

              <div class="col-md-6">
                <label class="form-label">Freeze Balance</label>
                <input type="text" name="freeze_balance" placeholder="Enter Freeze Balance" class="form-control" maxlength="10" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Virtual Balance</label>
                <input type="text" name="virtual_balance" placeholder="Enter Virtual Balance" class="form-control" maxlength="10" required>
              </div>
              <div class="col-12 d-flex justify-content-between">
                <button class="btn btn-label-secondary btn-prev" disabled>
                  <i class="icon-base ti tabler-arrow-left icon-xs me-sm-2"></i>
                  <span class="align-middle d-sm-inline-block d-none">Previous</span>
                </button>
                <button class="btn btn-primary btn-next"><span
                    class="align-middle d-sm-inline-block d-none me-sm-2">Next</span> <i
                    class="icon-base ti tabler-arrow-right icon-xs"></i></button>
              </div>
            </div>
          </div>
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
                  <option value="private_limited">Private Limited</option>
                  <option value="one_person_company">One Person Company</option>
                  <option value="limited_liability_partnership">Limited Liability Partnership</option>
                  <option value="public_limited">Public Limited</option>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label">GST Number (Optional)</label>
                <input type="text" name="gst_no" placeholder="Enter GST Number" class="form-control">
              </div>

              <div class="col-md-6">
                <label class="form-label">GST Certificate</label>
                <input type="file" name="gst_image" class="form-control">
              </div>

              <div class="col-md-6">
                <label class="form-label">CIN (Optional)</label>
                <input type="text" name="cin" placeholder="Enter CIN" class="form-control">
              </div>

              <div class="col-md-6">
                <label class="form-label">CIN Certificate</label>
                <input type="file" name="cin_image" class="form-control">
              </div>

              <div class="col-md-6">
                <label class="form-label">PAN (Optional)</label>
                <input type="text" name="pan" placeholder="Enter PAN" class="form-control">
              </div>

              <div class="col-md-6">
                <label class="form-label">PAN Certificate</label>
                <input type="file" name="pan_image" class="form-control">
              </div>

              <div class="col-md-6">
                <label class="form-label">Udhyam Number (Optional)</label>
                <input type="text" name="udhyam_number" placeholder="Enter GST Number" class="form-control">
              </div>

              <div class="col-md-6">
                <label class="form-label">Udhyam Certificate</label>
                <input type="file" name="udhyam_image" class="form-control">
              </div>

              <div class="col-md-6">
                <label class="form-label">Company Address</label>
                <textarea name="address" placeholder="Enter Company Address" class="form-control"></textarea>
              </div>
              <div class="col-12 d-flex justify-content-between">
                <button class="btn btn-label-secondary btn-prev">
                  <i class="icon-base ti tabler-arrow-left icon-xs me-sm-2"></i>
                  <span class="align-middle d-sm-inline-block d-none">Previous</span>
                </button>
                <button class="btn btn-primary btn-next"><span
                    class="align-middle d-sm-inline-block d-none me-sm-2">Next</span> <i
                    class="icon-base ti tabler-arrow-right icon-xs"></i></button>
              </div>
            </div>
          </div>
          <!-- Director Details -->
          <div id="director-info" class="content">
            <div class="content-header mb-4">
              <h6 class="mb-0">Director Details</h6>
              <small>KYC Information</small>
            </div>

            <div class="row g-4">
              <div class="col-md-6">
                <label class="form-label">Director Name</label>
                <input type="text" name="director_name" placeholder="Enter Director Name" class="form-control" required>
              </div>

              <div class="col-md-6">
                <label class="form-label">Director Email</label>
                <input type="email" name="director_email" placeholder="Enter Director Email" class="form-control" required>
              </div>

              <div class="col-md-6">
                <label class="form-label">Director Mobile Number</label>
                <input type="text" name="director_mobile" placeholder="Enter Director Mobile Number" class="form-control" required>
              </div>

              <div class="col-md-6">
                <label class="form-label">Aadhaar Number</label>
                <input type="text" name="director_aadhar_no" placeholder="Enter Aadhaar Number" class="form-control" required>
              </div>

              <div class="col-md-6">
                <label class="form-label">Aadhaar Image</label>
                <input type="file" name="director_aadhar_image" class="form-control" required>
              </div>

              <div class="col-md-6">
                <label class="form-label">PAN Number</label>
                <input type="text" name="director_pan_no" placeholder="Enter PAN Number" class="form-control" required>
              </div>

              <div class="col-md-6">
                <label class="form-label">PAN Image</label>
                <input type="file" name="director_pan_image" class="form-control" required>
              </div>
              <div class="col-12 d-flex justify-content-between">
                <button class="btn btn-label-secondary btn-prev">
                  <i class="icon-base ti tabler-arrow-left icon-xs me-sm-2"></i>
                  <span class="align-middle d-sm-inline-block d-none">Previous</span>
                </button>
                <button class="btn btn-primary btn-next"><span
                    class="align-middle d-sm-inline-block d-none me-sm-2">Next</span> <i
                    class="icon-base ti tabler-arrow-right icon-xs"></i></button>
              </div>
            </div>
          </div>
          <!-- User Services -->
          <div id="user-services" class="content">
            <div class="content-header mb-4">
              <h6 class="mb-0">Service Configuration</h6>
              <small>Enable required services</small>
            </div>

            <div class="row g-4">
              <div class="col-md-6">
                <label class="form-label">Payout Status</label>
                <select name="payout_status" class="form-select">
                  <option value="B">Blocked</option>
                  <option value="A">Active</option>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label">Payin Status</label>
                <select name="payin_status" class="form-select">
                  <option value="B">Blocked</option>
                  <option value="A">Active</option>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label">Payout Minimum Transaction</label>
                <input type="number" name="minimum_transaction" value="100.00" class="form-control">
              </div>

              <div class="col-md-6">
                <label class="form-label">Payout Maximum Transaction</label>
                <input type="number" name="maximum_transaction" value="49999.00" class="form-control">
              </div>

              <div class="col-md-6">
                <label class="form-label">Payin Minimum Transaction</label>
                <input type="number" name="payin_minimum_transaction" value="100.00" class="form-control">
              </div>

              <div class="col-md-6">
                <label class="form-label">Payin Maximum Transaction</label>
                <input type="number" name="payin_maximum_transaction" value="49999.00" class="form-control">
              </div>

              <div class="col-md-6">
                <label class="form-label">ftransaction</label>
                <select name="ftransaction" class="form-select">
                  <option value="A">Active</option>
                  <option value="B">Blocked</option>
                  <option value="C">Conditional</option>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label">ptransaction</label>
                <select name="ptransaction" class="form-select">
                  <option value="A">Active</option>
                  <option value="B">Blocked</option>
                  <option value="C">Conditional</option>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label">Virtual Charges</label>
                <input type="number" name="virtal_charges" value="1.00" class="form-control">
              </div>

              <div class="col-md-6">
                <label class="form-label">Virtual Type</label>
                <select name="virtual_type" class="form-select">
                  <option value="percentage">Percentage</option>
                  <option value="flat_rate">Flat Rate</option>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label">Payout Slab 1000</label>
                <input type="number" name="pslab_1000" value="5.00" class="form-control">
              </div>

              <div class="col-md-6">
                <label class="form-label">Payout Slab 25000</label>
                <input type="number" name="pslab_25000" value="7.00" class="form-control">
              </div>

              <div class="col-md-6">
                <label class="form-label">Payout Slab 200000</label>
                <input type="number" name="pslab_200000" value="15.00" class="form-control">
              </div>

              <div class="col-md-6">
                <label class="form-label">Payout Slab Percentage</label>
                <input type="number" name="pslab_1000" value="7.00" class="form-control">
              </div>

              <div class="col-md-6">
                <label class="form-label">Payin Charges</label>
                <input type="number" name="payin_charges" value="2.00" class="form-control">
              </div>

              <div class="col-md-6">
                <label class="form-label">Active Payout API</label>
                <input type="text" name="active_payout_api" placeholder="Enter Active Payout API" class="form-control" required>
              </div>

              <div class="col-md-6">
                <label class="form-label">Active Payin API</label>
                <input type="text" name="active_payin_api" placeholder="Enter Active Payin API" class="form-control" required>
              </div>

              <div class="col-12 d-flex justify-content-between">
                <button class="btn btn-label-secondary btn-prev">
                  <i class="icon-base ti tabler-arrow-left icon-xs me-sm-2"></i>
                  <span class="align-middle d-sm-inline-block d-none">Previous</span>
                </button>
                <button class="btn btn-primary btn-next"><span
                    class="align-middle d-sm-inline-block d-none me-sm-2">Next</span> <i
                    class="icon-base ti tabler-arrow-right icon-xs"></i></button>
              </div>
            </div>
          </div>
          <!-- User API Keys -->
          <div id="user_api_keys" class="content">
            <div class="content-header mb-4">
              <h6 class="mb-0">API & Final Submit</h6>
            </div>

            <div class="row g-4">
              <div class="col-md-6">
                <label class="form-label">Client Key</label>
                <input type="text" name="client_key" placeholder="Enter Client Key" class="form-control">
              </div>

              <div class="col-md-6">
                <label class="form-label">Client Secret</label>
                <input type="text" name="client_secret" placeholder="Enter Client Secret" class="form-control">
              </div>

              <div class="col-md-6">
                <label class="form-label">Payin Webhook URL</label>
                <input type="url" name="payin_webhooks" placeholder="Enter Payin Webhook URL" class="form-control">
              </div>

              <div class="col-md-6">
                <label class="form-label">Payout Webhook URL</label>
                <input type="url" name="payout_webhooks" placeholder="Enter Payout Webhook URL" class="form-control">
              </div>

              <div class="col-md-12">
                <label class="form-label">Whitelisted IPs</label>
                <input type="text" name="ip" class="form-control" placeholder="1.1.1.1,2.2.2.2">
              </div>

              <div class="col-12 d-flex justify-content-between">
                <button class="btn btn-label-secondary btn-prev">
                  <i class="icon-base ti tabler-arrow-left icon-xs me-sm-2"></i>
                  <span class="align-middle d-sm-inline-block d-none">Previous</span>
                </button>
                <button class="btn btn-success btn-submit">Submit</button>
              </div>
            </div>
        </form>
      </div>
    </div>
  </div>
  <!-- /Default Icons Wizard -->
</div>
@endsection