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
use App\Policies\UnidadOrganizacionalPolicy;
use App\Policies\UnidadResponsablePolicy;
use App\Policies\UserUnidadAccesoPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Tramite::class, TramitePolicy::class);
        Gate::policy(CalidadContractual::class, CalidadContractualPolicy::class);
        Gate::policy(PersonaUnidadVinculo::class, PersonaUnidadVinculoPolicy::class);
        Gate::policy(UnidadOrganizacional::class, UnidadOrganizacionalPolicy::class);
        Gate::policy(UnidadResponsable::class, UnidadResponsablePolicy::class);
        Gate::policy(UserUnidadAcceso::class, UserUnidadAccesoPolicy::class);
    }
}
