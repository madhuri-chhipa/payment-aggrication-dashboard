<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Routing\Route;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;

class MenuServiceProvider extends ServiceProvider
{
  /**
   * Register services.
   */
  public function register(): void
  {
    //
  }

  /**
   * Bootstrap services.
   */
  public function boot(): void
  {
    View::composer('*', function ($view) {
      $verticalAdminMenuJson = file_get_contents(base_path('resources/menu/adminMenu.json'));
      $verticalUserMenuJson = file_get_contents(base_path('resources/menu/userMenu.json'));

      if (auth()->guard('admin')->check()) {
        $verticalMenuData = json_decode($verticalAdminMenuJson);
      } elseif (auth()->guard('user')->check()) {
        $verticalMenuData = json_decode($verticalUserMenuJson);
      } else {
        $verticalMenuData = json_decode($verticalUserMenuJson);
      }

      $horizontalMenuJson = file_get_contents(base_path('resources/menu/horizontalMenu.json'));
      $horizontalMenuData = json_decode($horizontalMenuJson);
      $view->with('menuData', [
        $verticalMenuData,     // index 0 → vertical
        $horizontalMenuData    // index 1 → horizontal
      ]);
    });
    // Share all menuData to all the views
    // $this->app->make('view')->share('menuData', [$verticalMenuData, $horizontalMenuData]);
  }
}