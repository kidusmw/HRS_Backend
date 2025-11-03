<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

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
        // Customize password reset URL to point to the frontend SPA
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            $frontend = rtrim(env('FRONTEND_BASE_URL', 'http://localhost:5173'), '/');
            // The SPA route will read token and email from query params
            return $frontend . '/reset-password?token=' . urlencode($token) . '&email=' . urlencode($notifiable->getEmailForPasswordReset());
        });

        // Register Gates
        Gate::define('isSuperAdmin', function (User $user) {
            return $user->isSuperAdmin();
        });

        Gate::define('isAdmin', function (User $user) {
            return $user->isAdmin();
        });

        Gate::define('isActive', function (User $user) {
            return $user->active === true;
        });
    }
}
