@php
  $customizerHidden = 'customizer-hide';
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Two Steps Verifications Basic - Pages')

@section('vendor-style')
  @vite(['resources/assets/vendor/libs/@form-validation/form-validation.scss'])
@endsection

@section('page-style')
  @vite(['resources/assets/vendor/scss/pages/page-auth.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/cleave-zen/cleave-zen.js', 'resources/assets/vendor/libs/@form-validation/popular.js', 'resources/assets/vendor/libs/@form-validation/bootstrap5.js', 'resources/assets/vendor/libs/@form-validation/auto-focus.js'])
@endsection

@section('page-script')
  @vite(['resources/assets/js/pages-auth.js', 'resources/assets/js/pages-auth-two-steps.js'])
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const form = document.getElementById('twoStepsForm');
      let locationFetched = false;
      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
          function(position) {
            document.getElementById('latitude').value = position.coords.latitude;
            document.getElementById('longitude').value = position.coords.longitude;
            locationFetched = true;
          },
          function() {
            locationFetched = true; // allow submit even if denied
          }
        );
      } else {
        locationFetched = true;
      }

      form.addEventListener('submit', function(e) {
        if (!locationFetched) {
          e.preventDefault();
          alert('Please wait, detecting your location...');
        }
      });
    });
  </script>
@endsection

@section('content')
  <div class="authentication-wrapper authentication-basic px-6">
    <div class="authentication-inner py-6">
      <!--  Two Steps Verification -->
      <div class="card">
        <div class="card-body">
          <!-- Logo -->
          <div class="app-brand justify-content-center mb-6">
            <a href="{{ url('/') }}" class="app-brand-link">
              <span class="app-brand-logo demo">@include('_partials.macros')</span>
            </a>
          </div>
          <!-- /Logo -->
          <h4 class="mb-1">Two Step Verification 💬</h4>
          <p class="text-start mb-6">
            We sent a verification code to your email/mobile. Enter the code from the email/mobile in the field below.
          </p>
          <p class="mb-0">Type your 4 digit security code</p>
          <form id="twoStepsForm" action="{{ route('admin.auth.otp-submit') }}" method="POST">
            @csrf
            <div class="mb-6 form-control-validation">
              <div class="auth-input-wrapper d-flex align-items-center justify-content-center numeral-mask-wrapper">
                <input type="tel" class="form-control auth-input h-px-50 text-center numeral-mask mx-sm-1 my-2"
                  maxlength="1" />
                <input type="tel" class="form-control auth-input h-px-50 text-center numeral-mask mx-sm-1 my-2"
                  maxlength="1" />
                <input type="tel" class="form-control auth-input h-px-50 text-center numeral-mask mx-sm-1 my-2"
                  maxlength="1" />
                <input type="tel" class="form-control auth-input h-px-50 text-center numeral-mask mx-sm-1 my-2"
                  maxlength="1" />
              </div>
              <!-- Create a hidden field which is combined by 3 fields above -->
              <input type="hidden" name="otp" />
              <input type="hidden" name="latitude" id="latitude">
              <input type="hidden" name="longitude" id="longitude">
            </div>
            <button class="btn btn-primary d-grid w-100 mb-6" type="submit">Verify my account</button>
            <div class="text-center">
              Didn't get the code?
              <a href="javascript:void(0);"> Resend </a>
            </div>
          </form>
        </div>
      </div>
      <!-- / Two Steps Verification -->
    </div>
  </div>
@endsection
