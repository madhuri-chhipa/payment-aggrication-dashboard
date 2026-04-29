@php
  use Illuminate\Support\Facades\Route;
  use Illuminate\Support\Str;
@endphp

<ul class="menu-sub">
  @if (isset($menu))
    @foreach ($menu as $submenu)
      {{-- active menu method --}}
      @php
        $activeClass = null;
        $active = $configData['layout'] === 'vertical' ? 'active open' : 'active';
        $currentRouteName = Route::currentRouteName();

        if ($currentRouteName === $submenu->slug) {
            $activeClass = 'active';
        } elseif (isset($submenu->submenus)) {
            if (isset($submenu->submenus) && is_array($submenu->submenus)) {
                foreach ($submenu->submenus as $subSlug) {
                    $subSlug = trim($subSlug);
                    if (Str::startsWith($currentRouteName, $subSlug)) {
                        $activeClass = 'active open';
                        // break;
                    }
                }
            }
            if (gettype($submenu->slug) === 'array') {
                foreach ($submenu->slug as $slug) {
                    if (str_contains($currentRouteName, $slug) and strpos($currentRouteName, $slug) === 0) {
                        $activeClass = $active;
                    }
                }
            } else {
                if (
                    str_contains($currentRouteName, $submenu->slug) and
                    strpos($currentRouteName, $submenu->slug) === 0
                ) {
                    $activeClass = $active;
                }
            }
        }
      @endphp

      <li class="menu-item {{ $activeClass }}">
        <a href="{{ isset($submenu->url) ? url($submenu->url) : 'javascript:void(0)' }}"
          class="{{ isset($submenu->submenu) ? 'menu-link menu-toggle' : 'menu-link' }}"
          style="{{ $submenu->slug === 'service.bill-payment' ? 'padding-inline-start:2rem;' : 'padding-inline-start:2.625rem;' }}"
          @if (isset($submenu->target) and !empty($submenu->target)) target="_blank" @endif>
          @if ($submenu->slug === 'service.bill-payment')
            <img
              src="{{ $activeClass ? asset('assets/img/logo/bbpsWhiteMnemonic.png') : asset('assets/img/logo/bbpsMnemonic.png') }}"
              style="width:38px; height:42px;">
          @elseif (isset($submenu->icon))
            <i class="{{ $submenu->icon }}"></i>
          @endif
          <div>{{ isset($submenu->name) ? __($submenu->name) : '' }}</div>
          @isset($submenu->badge)
            <div class="badge bg-{{ $submenu->badge[0] }} rounded-pill ms-auto">{{ $submenu->badge[1] }}</div>
          @endisset
        </a>

        {{-- submenu --}}
        @if (isset($submenu->submenu))
          @include('layouts.sections.menu.submenu', ['menu' => $submenu->submenu])
        @endif
      </li>
    @endforeach
  @endif
</ul>
