<?php

namespace App\Providers;

use App\Models\Tramite;
use App\Policies\TramitePolicy;
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
    }
}
