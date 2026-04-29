<!DOCTYPE html>
@php
  use Illuminate\Support\Str;
  use App\Helpers\Helpers;

  $menuFixed =
      $configData['layout'] === 'vertical'
          ? $menuFixed ?? ''
          : ($configData['layout'] === 'front'
              ? ''
              : $configData['headerType']);

  $navbarType =
      $configData['layout'] === 'vertical'
          ? $configData['navbarType']
          : ($configData['layout'] === 'front'
              ? 'layout-navbar-fixed'
              : '');

  $isFront = ($isFront ?? '') == true ? 'Front' : '';
  $contentLayout = isset($container) ? ($container === 'container-xxl' ? 'layout-compact' : 'layout-wide') : '';

  // Admin skin + semi dark only for admin layouts
  $isAdminLayout = !Str::contains($configData['layout'] ?? '', 'front');
  $skinName = $isAdminLayout ? $configData['skinName'] ?? 'default' : 'default';
  $semiDarkEnabled = $isAdminLayout && filter_var($configData['semiDark'] ?? false, FILTER_VALIDATE_BOOLEAN);

  // Primary color css
  $primaryColorCSS = '';
  if (!empty($configData['color'])) {
      $primaryColorCSS = Helpers::generatePrimaryColorCSS($configData['color']);
  }
@endphp

<html lang="{{ session()->get('locale') ?? app()->getLocale() }}"
  class="{{ $navbarType ?? '' }} {{ $contentLayout ?? '' }} {{ $menuFixed ?? '' }} {{ $menuCollapsed ?? '' }} {{ $footerFixed ?? '' }} {{ $customizerHidden ?? '' }}"
  dir="{{ $configData['textDirection'] }}" data-skin="{{ $skinName }}" data-assets-path="{{ asset('/assets') . '/' }}"
  data-base-url="{{ url('/') }}" data-framework="laravel" data-template="{{ $configData['layout'] }}-menu-template"
  data-bs-theme="{{ $configData['theme'] }}" @if ($isAdminLayout && $semiDarkEnabled) data-semidark-menu="true" @endif>

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>
    @yield('title') |
    {{-- {{ config('variables.templateName') ?: 'TemplateName' }} --}}
    {{-- - --}}
    {{ config('variables.templateSuffix') ?: 'TemplateSuffix' }}
  </title>

  <meta name="description" content="{{ config('variables.templateDescription') ?: '' }}" />
  <meta name="keywords" content="{{ config('variables.templateKeyword') ?: '' }}" />
  <meta property="og:title" content="{{ config('variables.ogTitle') ?: '' }}" />
  <meta property="og:type" content="{{ config('variables.ogType') ?: '' }}" />
  <meta property="og:url" content="{{ config('variables.productPage') ?: '' }}" />
  <meta property="og:image" content="{{ config('variables.ogImage') ?: '' }}" />
  <meta property="og:description" content="{{ config('variables.templateDescription') ?: '' }}" />
  <meta property="og:site_name" content="{{ config('variables.creatorName') ?: '' }}" />
  <meta name="robots" content="noindex, nofollow" />

  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <link rel="canonical" href="{{ config('variables.productPage') ?: '' }}" />
  <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/logo/finsova-logo-fav.png') }}" />

  {{-- Vendor CSS --}}
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

  {{-- DataTables CSS --}}
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

  {{-- Theme Styles --}}
  @include('layouts/sections/styles' . $isFront)

  <style>
    #toast-container>.toast-success {
      background-color: #28a745 !important;
      color: #fff !important;
    }

    #toast-container>.toast-error {
      background-color: #dc3545 !important;
      color: #fff !important;
    }

    #toast-container>.toast-info {
      background-color: #17a2b8 !important;
      color: #fff !important;
    }

    #toast-container>.toast-warning {
      background-color: #ffc107 !important;
      color: #000 !important;
    }

    .user-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: #fff;
      color: #7367f0;
      font-size: 25px;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 15px;
      box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
    }
  </style>

  @if (
      $primaryColorCSS &&
          (config('custom.custom.primaryColor') ||
              isset($_COOKIE['admin-primaryColor']) ||
              isset($_COOKIE['front-primaryColor'])))
    <style id="primary-color-style">
      {!! $primaryColorCSS !!}
    </style>
  @endif

  {{-- Theme Script Includes (customizer/helper/config) --}}
  @include('layouts/sections/scriptsIncludes' . $isFront)
</head>

<body>
  @include('layouts/sections/toastr')

  {{-- Layout Content --}}
  @yield('layoutContent')

  {{-- Theme Scripts --}}
  @include('layouts/sections/scripts' . $isFront)

  {{-- jQuery (load ONCE before plugins; if your theme already includes jQuery, remove this CDN line) --}}
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

  {{-- DataTables Buttons --}}
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>

  {{-- Export dependencies (load before html5 buttons) --}}
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

  {{-- Buttons exporters --}}
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
  {{-- jQuery Validation --}}
  <script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/additional-methods.min.js"></script>

  {{-- Toastr + SweetAlert --}}
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  {{-- DataTables CORE (must be before Buttons) --}}


  @yield('customJs')
</body>

</html>
