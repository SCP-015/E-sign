<?php

namespace App\Providers;

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
        // Force Laravel Passport to use central database
        // OAuth tokens, clients, etc. are ALWAYS in central DB
        if (class_exists(\Laravel\Passport\Passport::class)) {
            \Laravel\Passport\Passport::useClientModel(\Laravel\Passport\Client::class);
            \Laravel\Passport\Passport::useTokenModel(\Laravel\Passport\Token::class);
            \Laravel\Passport\Passport::useRefreshTokenModel(\Laravel\Passport\RefreshToken::class);
            \Laravel\Passport\Passport::useAuthCodeModel(\Laravel\Passport\AuthCode::class);
            
            // Explicitly set connection for all Passport models
            foreach ([
                \Laravel\Passport\Client::class,
                \Laravel\Passport\Token::class,
                \Laravel\Passport\RefreshToken::class,
                \Laravel\Passport\AuthCode::class,
            ] as $model) {
                (new $model)->setConnection('pgsql');
            }
        }
    }
}
