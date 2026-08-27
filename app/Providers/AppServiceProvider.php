<?php

namespace App\Providers;

use App\Actions\DocDigital\DocDigitalTransitionGuard;
use App\Actions\HorasExtraordinarias\HorasExtraTransitionGuard;
use App\Actions\Reemplazos\ReemplazoTransitionGuard;
use App\Models\Persona;
use App\Models\Tramite;
use App\Models\UnidadServicio;
use App\Policies\PersonaPolicy;
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
        Gate::policy(Persona::class, PersonaPolicy::class);
        $this->app->singleton(ReemplazoTransitionGuard::class);
        $this->app->tag(ReemplazoTransitionGuard::class, 'tramite.transition.guards');
        $this->app->singleton(HorasExtraTransitionGuard::class);
        $this->app->tag(HorasExtraTransitionGuard::class, 'tramite.transition.guards');
        $this->app->singleton(DocDigitalTransitionGuard::class);
        $this->app->tag(DocDigitalTransitionGuard::class, 'tramite.transition.guards');
    }
}
