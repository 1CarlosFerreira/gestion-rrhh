<?php

namespace App\Providers;

use App\Models\CalidadContractual;
use App\Models\PersonaUnidadVinculo;
use App\Models\Tramite;
use App\Models\UnidadOrganizacional;
use App\Models\UnidadResponsable;
use App\Models\UserUnidadAcceso;
use App\Policies\CalidadContractualPolicy;
use App\Policies\PersonaUnidadVinculoPolicy;
use App\Policies\TramitePolicy;
use App\Policies\TramiteReemplazoPolicy;
use App\Policies\UnidadOrganizacionalPolicy;
use App\Policies\UnidadResponsablePolicy;
use App\Policies\UserUnidadAccesoPolicy;
use App\Services\Reemplazos\ReemplazoTransitionGuard;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([ReemplazoTransitionGuard::class], 'tramite.transition.guards');
    }

    public function boot(): void
    {
        Gate::policy(Tramite::class, TramitePolicy::class);
        Gate::define('crear-reemplazo', TramiteReemplazoPolicy::class.'@create');
        Gate::define('ver-reemplazo', TramiteReemplazoPolicy::class.'@view');
        Gate::define('editar-reemplazo', TramiteReemplazoPolicy::class.'@update');
        Gate::define('revisar-reemplazo', TramiteReemplazoPolicy::class.'@review');
        Gate::define('generar-documento-reemplazo', TramiteReemplazoPolicy::class.'@generateDocument');
        Gate::policy(CalidadContractual::class, CalidadContractualPolicy::class);
        Gate::policy(PersonaUnidadVinculo::class, PersonaUnidadVinculoPolicy::class);
        Gate::policy(UnidadOrganizacional::class, UnidadOrganizacionalPolicy::class);
        Gate::policy(UnidadResponsable::class, UnidadResponsablePolicy::class);
        Gate::policy(UserUnidadAcceso::class, UserUnidadAccesoPolicy::class);
    }
}
