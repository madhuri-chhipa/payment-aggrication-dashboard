@php
  use Illuminate\Support\Facades\Route;
  $configData = Helper::appClasses();
@endphp

<aside id="layout-menu" class="layout-menu menu-vertical menu"
  @foreach ($configData['menuAttributes'] as $attribute => $value)
    {{ $attribute }}="{{ $value }}" @endforeach>
  @if (!isset($navbarFull))
    <div class="app-brand demo">
      <a href="{{ url('/') }}" class="app-brand-link">
        <span class="app-brand-logo demo">
          @include('_partials.macros')
        </span>
        <span class="app-brand-text demo menu-text fw-bold ms-3">
          {{ config('variables.templateName') }}
        </span>
      </a>

      <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
        <i class="icon-base ti menu-toggle-icon d-none d-xl-block"></i>
        <i class="icon-base ti tabler-x d-block d-xl-none"></i>
      </a>
    </div>
  @endif

  <div class="menu-inner-shadow"></div>

  <ul class="menu-inner py-1">
    @foreach ($menuData[0]->menu as $menu)
      @if (isset($menu->menuHeader))
        <li class="menu-header small">
          <span class="menu-header-text">{{ __($menu->menuHeader) }}</span>
        </li>
      @else
        @php
          $activeClass = null;
          $currentRouteName = Route::currentRouteName();

          if ($currentRouteName === $menu->slug) {
              $activeClass = 'active';
          } elseif (isset($menu->submenu)) {
              if (gettype($menu->slug) === 'array') {
                  foreach ($menu->slug as $slug) {
                      if (str_contains($currentRouteName, $slug) && strpos($currentRouteName, $slug) === 0) {
                          $activeClass = 'active open';
                      }
                  }
              } else {
                  if (str_contains($currentRouteName, $menu->slug) && strpos($currentRouteName, $menu->slug) === 0) {
                      $activeClass = 'active open';
                  }
              }
          }
        @endphp

        <li class="menu-item {{ $activeClass }}">
          <a href="{{ isset($menu->url) ? url($menu->url) : 'javascript:void(0);' }}"
            class="{{ isset($menu->submenu) ? 'menu-link menu-toggle' : 'menu-link' }}"
            @if (isset($menu->target) && !empty($menu->target)) target="_blank" @endif>
            @if (isset($menu->icon_image) && !empty($menu->icon_image))
              <img src="{{ asset($menu->icon_image) }}" alt="{{ $menu->name ?? 'menu-icon' }}"
                class="menu-icon-img me-2">
            @elseif (isset($menu->icon) && !empty($menu->icon))
              <i class="{{ $menu->icon }}"></i>
            @endif

            <div>{{ isset($menu->name) ? __($menu->name) : '' }}</div>

            @isset($menu->badge)
              <div class="badge bg-{{ $menu->badge[0] }} rounded-pill ms-auto">
                {{ $menu->badge[1] }}
              </div>
            @endisset
          </a>

          @isset($menu->submenu)
            @include('layouts.sections.menu.submenu', ['menu' => $menu->submenu])
          @endisset
        </li>
      @endif
    @endforeach
  </ul>
</aside>

<style>
  .menu-icon-img {
    width: 18px;
    height: 18px;
    min-width: 18px;
    object-fit: contain;
    display: inline-block;
    vertical-align: middle;
  }
</style>
