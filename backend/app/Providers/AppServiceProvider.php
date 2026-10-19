<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        // Absolute URLs (redirects, asset links) must be built from APP_URL:
        // the distro's nginx fastcgi_params deliberately passes HTTP_HOST as
        // "$host", which drops the client port, so request-derived roots
        // would lose ":8000" in local dev and break the admin panel assets.
        URL::forceRootUrl(config('app.url'));

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(6)->by(Str::lower((string) $request->input('email')).'|'.$request->ip());
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
