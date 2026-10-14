<?php

namespace App\Providers;

use App\Models\AccountRequest;
use App\Models\NotificacionFalta;
use App\Models\Solicitud;
use App\Models\User;
use App\Policies\AccountRequestPolicy;
use App\Policies\NotificacionFaltaPolicy;
use App\Policies\SolicitudPolicy;
use App\Policies\UserPolicy;
use App\Services\AppNotificationService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Azure\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::defaultView('pagination.sava');
        Paginator::defaultSimpleView('pagination.sava-simple');

        RateLimiter::for('solicitar-cuenta', function (Request $request) {
            $maxAttempts = app()->runningUnitTests() ? 60 : 5;

            return Limit::perMinute($maxAttempts, 10)->by($request->ip());
        });

        Gate::policy(Solicitud::class, SolicitudPolicy::class);
        Gate::policy(NotificacionFalta::class, NotificacionFaltaPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(AccountRequest::class, AccountRequestPolicy::class);

        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('azure', Provider::class);
        });

        View::composer('layouts.app', function ($view): void {
            $user = auth()->user();
            if ($user === null) {
                return;
            }
            $service = app(AppNotificationService::class);
            $view->with([
                'appNotificacionesRecientes' => $service->recientesPara($user),
                'appNotificacionesNoLeidas' => $service->contarNoLeidas($user),
            ]);
        });
    }
}
