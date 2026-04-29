@php
  $customizerHidden = 'customizer-hide';
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Register')

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

@section('page-script')
  @vite(['resources/assets/js/form-wizard-icons.js'])
  @vite('resources/assets/js/register.js')
@endsection

@section('content')
  <div class="container-xxl">
    <div class="authentication-wrapper authentication-basic container-p-y">
      <div class="authentication-inner py-6">
        <!-- Register Card -->
        <div class="bs-stepper wizard-icons wizard-icons-example mt-2" id="wizard-icons">
          <!-- Logo -->
          <div class="app-brand justify-content-center pt-5">
            <a href="{{ url('/') }}" class="app-brand-link">
              <span class="app-brand-logo demo">@include('_partials.macros')</span>
            </a>
          </div>
          <!-- <h5 class="mb-1 pt-3 text-center">Register here 🚀</h5> -->
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
          </div>
          <div class="bs-stepper-content">
            <form id="registerForm" method="POST" action="{{ route('user-register.store') }}"
              enctype="multipart/form-data" novalidate>
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
                    <input type="text" name="company_name" value="{{ old('company_name') }}"
                      placeholder="Enter Company Name" class="form-control">
                    @error('company_name')
                      <span class="text-danger">{{ $message }}</span>
                    @enderror
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" placeholder="Enter Email Address"
                      class="form-control">
                    @error('email')
                      <span class="text-danger">{{ $message }}</span>
                    @enderror
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Mobile Number</label>
                    <input type="text" name="mobile_number" value="{{ old('mobile_number') }}"
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
                    <button class="btn btn-label-secondary btn-prev" type="button">
                      <i class="icon-base ti tabler-arrow-left icon-xs me-sm-2"></i>
                      <span class="align-middle">Previous</span>
                    </button>
                    <button class="btn btn-primary btn-next" type="button"><span
                        class="align-middle me-sm-2">Next</span>
                      <i class="icon-base ti tabler-arrow-right icon-xs"></i></button>
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
                      <option value="private_limited" {{ old('company_type') == 'private_limited' ? 'selected' : '' }}>
                        Private Limited</option>
                      <option value="one_person_company"
                        {{ old('company_type') == 'one_person_company' ? 'selected' : '' }}>One Person Company</option>
                      <option value="limited_liability_partnership"
                        {{ old('company_type') == 'limited_liability_partnership' ? 'selected' : '' }}>Limited Liability
                        Partnership</option>
                      <option value="public_limited" {{ old('company_type') == 'public_limited' ? 'selected' : '' }}>
                        Public Limited</option>
                    </select>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">GST Number (Optional)</label>
                    <input type="text" name="gst_no" value="{{ old('gst_no') }}" placeholder="Enter GST Number"
                      class="form-control">
                    @error('gst_no')
                      <span class="text-danger">{{ $message }}</span>
                    @enderror
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">GST Certificate</label>
                    <input type="file" name="gst_image" class="form-control">
                    @error('gst_image')
                      <span class="text-danger">{{ $message }}</span>
                    @enderror
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">CIN (Optional)</label>
                    <input type="text" name="cin" placeholder="Enter CIN" class="form-control"
                      value="{{ old('cin') }}">
                    @error('cin')
                      <span class="text-danger">{{ $message }}</span>
                    @enderror
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">CIN Certificate</label>
                    <input type="file" name="cin_image" class="form-control">
                    @error('cin_image')
                      <span class="text-danger">{{ $message }}</span>
                    @enderror
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">PAN (Optional)</label>
                    <input type="text" name="pan" placeholder="Enter PAN" class="form-control"
                      value="{{ old('pan') }}">
                    @error('pan')
                      <span class="text-danger">{{ $message }}</span>
                    @enderror
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">PAN Certificate</label>
                    <input type="file" name="pan_image" class="form-control">
                    @error('pan_image')
                      <span class="text-danger">{{ $message }}</span>
                    @enderror
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Udhyam Number (Optional)</label>
                    <input type="text" name="udhyam_number" placeholder="Enter GST Number" class="form-control"
                      value="{{ old('udhyam_number') }}">
                    @error('udhyam_number')
                      <span class="text-danger">{{ $message }}</span>
                    @enderror
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Udhyam Certificate</label>
                    <input type="file" name="udhyam_image" class="form-control">
                    @error('udhyam_image')
                      <span class="text-danger">{{ $message }}</span>
                    @enderror
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">MOA Certificate</label>
                    <input type="file" name="moa_image" class="form-control">
                    @error('moa_image')
                      <span class="text-danger">{{ $message }}</span>
                    @enderror
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">BR Certificate</label>
                    <input type="file" name="br_image" class="form-control">
                    @error('br_image')
                      <span class="text-danger">{{ $message }}</span>
                    @enderror
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Company Address</label>
                    <textarea name="address" placeholder="Enter Company Address" class="form-control">{{ old('address') }}</textarea>
                    @error('address')
                      <span class="text-danger">{{ $message }}</span>
                    @enderror
                  </div>
                  <div class="col-12 d-flex justify-content-between">
                    <button class="btn btn-label-secondary btn-prev" type="button">
                      <i class="icon-base ti tabler-arrow-left icon-xs me-sm-2"></i>
                      <span class="align-middle">Previous</span>
                    </button>
                    <button class="btn btn-primary btn-next" type="button"><span
                        class="align-middle me-sm-2">Next</span>
                      <i class="icon-base ti tabler-arrow-right icon-xs"></i></button>
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
                    <input type="text" name="director_name" value="{{ old('director_name') }}"
                      placeholder="Enter Director Name" class="form-control">
                    @error('director_name')
                      <span class="text-danger">{{ $message }}</span>
                    @enderror
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Director Email</label>
                    <input type="email" name="director_email" value="{{ old('director_email') }}"
                      placeholder="Enter Director Email" class="form-control">
                    @error('director_email')
                      <span class="text-danger">{{ $message }}</span>
                    @enderror
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Director Mobile Number</label>
                    <input type="text" name="director_mobile" value="{{ old('director_mobile') }}"
                      placeholder="Enter Director Mobile Number" class="form-control">
                    @error('director_mobile')
                      <span class="text-danger">{{ $message }}</span>
                    @enderror
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Aadhaar Number</label>
                    <input type="text" name="director_aadhar_no" value="{{ old('director_aadhar_no') }}"
                      placeholder="Enter Aadhaar Number" class="form-control">
                    @error('director_aadhar_no')
                      <span class="text-danger">{{ $message }}</span>
                    @enderror
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Aadhaar Image</label>
                    <input type="file" name="director_aadhar_image" class="form-control">
                    @error('director_aadhar_image')
                      <span class="text-danger">{{ $message }}</span>
                    @enderror
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">PAN Number</label>
                    <input type="text" name="director_pan_no" value="{{ old('director_pan_no') }}"
                      placeholder="Enter PAN Number" class="form-control">
                    @error('director_pan_no')
                      <span class="text-danger">{{ $message }}</span>
                    @enderror
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">PAN Image</label>
                    <input type="file" name="director_pan_image" class="form-control">
                    @error('director_pan_image')
                      <span class="text-danger">{{ $message }}</span>
                    @enderror
                  </div>
                  <div class="col-12 d-flex justify-content-between">
                    <button class="btn btn-label-secondary btn-prev" type="button">
                      <i class="icon-base ti tabler-arrow-left icon-xs me-sm-2"></i>
                      <span class="align-middle">Previous</span>
                    </button>
                    <button class="btn btn-primary" type="submit"><span class="align-middle me-sm-2">Submit</span> <i
                        class="icon-base ti tabler-arrow-right icon-xs"></i></button>
                  </div>
                </div>
              </div>
            </form>
          </div>
        </div>
        <!-- Register Card -->
      </div>
    </div>
  </div>
@endsection
