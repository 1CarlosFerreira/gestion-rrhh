<?php

namespace App\Providers;

use App\Actions\Reemplazos\ReemplazoTransitionGuard;
use App\Models\Tramite;
use App\Models\UnidadServicio;
use App\Policies\TramitePolicy;
use App\Policies\UnidadServicioPolicy;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(UnidadServicio::class, UnidadServicioPolicy::class);
        Gate::policy(Tramite::class, TramitePolicy::class);
        $this->app->singleton(ReemplazoTransitionGuard::class);
        $this->app->tag(ReemplazoTransitionGuard::class, 'tramite.transition.guards');
    }
}
