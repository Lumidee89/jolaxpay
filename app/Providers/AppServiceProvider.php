<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
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
        // Mobile customers are not authenticated in a browser when they
        // tap the email, so use a temporary signed API link rather than
        // Breeze's session-authenticated web verification route.
        VerifyEmail::createUrlUsing(fn ($user) => URL::temporarySignedRoute(
            'api.verification.verify',
            now()->addHour(),
            ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())],
        ));

        Vite::prefetch(concurrency: 3);
    }
}
