<?php

namespace App\Providers;

use App\Models\Tramite;
use App\Models\UnidadOrganizacional;
use App\Models\UnidadResponsable;
use App\Models\UserUnidadAcceso;
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
        Gate::policy(UnidadOrganizacional::class, UnidadOrganizacionalPolicy::class);
        Gate::policy(UnidadResponsable::class, UnidadResponsablePolicy::class);
        Gate::policy(UserUnidadAcceso::class, UserUnidadAccesoPolicy::class);
    }
}
