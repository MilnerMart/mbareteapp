<?php

namespace App\Providers;

use App\Support\ThemePreference;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // el layout (y el menu que incluye) recibe el tema ya resuelto
        View::composer('layouts.layout', fn($view) => $view->with('themeView', ThemePreference::buildView()));
    }
}
