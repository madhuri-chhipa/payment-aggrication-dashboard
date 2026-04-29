@php
  $customizerHidden = 'customizer-hide';
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Reset Password Basic - Pages')

@section('vendor-style')
  @vite(['resources/assets/vendor/libs/@form-validation/form-validation.scss'])
@endsection

@section('page-style')
  @vite(['resources/assets/vendor/scss/pages/page-auth.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/@form-validation/popular.js', 'resources/assets/vendor/libs/@form-validation/bootstrap5.js', 'resources/assets/vendor/libs/@form-validation/auto-focus.js'])
@endsection

@section('page-script')
  @vite(['resources/assets/js/reset-password.js'])
@endsection

@section('content')
  <div class="container-xxl">
    <div class="authentication-wrapper authentication-basic container-p-y">
      <div class="authentication-inner py-6">
        <!-- Reset Password -->
        <div class="card">
          <div class="card-body">
            <!-- Logo -->
            <div class="app-brand justify-content-center mb-6">
              <a href="{{ url('/') }}" class="app-brand-link">
                <span class="app-brand-logo demo">@include('_partials.macros')</span>
              </a>
            </div>
            <!-- /Logo -->
            <h4 class="mb-1">Reset Password 🔒</h4>
            <p class="mb-6"><span class="fw-medium">Your new password must be different from previously used
                passwords</span></p>
            <form id="formAuthentication" method="POST" action="{{ route('admin.reset-password.submit') }}">
              @csrf

              <input type="hidden" name="email" value="{{ session('password_reset_email') }}">

              <div class="fv-row mb-3">
                <label class="form-label">OTP</label>
                <input type="text" name="otp" class="form-control" />
              </div>

              <div class="fv-row mb-3">
                <label class="form-label">New Password</label>
                <div class="input-group input-group-merge">
                  <input type="password" name="password" class="form-control" />
                </div>
              </div>

              <div class="fv-row mb-3">
                <label class="form-label">Confirm Password</label>
                <div class="input-group input-group-merge">
                  <input type="password" name="password_confirmation" class="form-control" />
                </div>
              </div>

              <button class="btn btn-primary w-100">
                Reset password
              </button>
            </form>
          </div>
        </div>
        <!-- /Reset Password -->
      </div>
    </div>
  </div>
@endsection
