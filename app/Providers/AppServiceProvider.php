<?php

namespace App\Providers;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\StockService::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Model::preventLazyLoading(! $this->app->isProduction() && ! $this->app->runningUnitTests());

        // Every permission name is a Gate ability; super admin passes everything.
        Gate::before(function (User $user, string $ability) {
            if (! $user->is_active) {
                return false;
            }

            return $user->hasPermission($ability) ? true : null;
        });

        Event::listen(Login::class, function (Login $e) {
            $e->user->forceFill(['last_login_at' => now()])->saveQuietly();
            ActivityLogger::log('auth.login', "{$e->user->username} logged in", $e->user, module: 'auth', user: $e->user);
        });
        Event::listen(Logout::class, function (Logout $e) {
            if ($e->user) {
                ActivityLogger::log('auth.logout', "{$e->user->username} logged out", $e->user, module: 'auth', user: $e->user);
            }
        });
        Event::listen(Failed::class, function (Failed $e) {
            $name = $e->credentials['username'] ?? '?';
            ActivityLogger::log('auth.failed', "Failed login attempt for '{$name}'", module: 'auth', user: $e->user);
        });
    }
}
