<?php

use App\Http\Controllers\Admin\CalidadContractualController;
use App\Http\Controllers\Admin\DotacionController;
use App\Http\Controllers\Admin\EstructuraOrganizacionalController;
use App\Http\Controllers\Admin\TipoUnidadOrganizacionalController;
use App\Http\Controllers\Admin\UnidadResponsableController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserUnidadAccesoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::middleware('can:admin.usuarios')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/usuarios', [UserController::class, 'index'])->name('usuarios.index');
        Route::put('/usuarios/{user}/roles', [UserController::class, 'updateRoles'])->name('usuarios.roles.update');
        Route::patch('/usuarios/{user}/activo', [UserController::class, 'toggleActive'])->name('usuarios.activo');
    });

    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/estructura-organizacional', [EstructuraOrganizacionalController::class, 'index'])->name('estructura.index');
        Route::get('/estructura-organizacional/crear', [EstructuraOrganizacionalController::class, 'create'])->name('estructura.create');
        Route::post('/estructura-organizacional', [EstructuraOrganizacionalController::class, 'store'])->name('estructura.store');
        Route::get('/estructura-organizacional/{unidad}/editar', [EstructuraOrganizacionalController::class, 'edit'])->name('estructura.edit');
        Route::put('/estructura-organizacional/{unidad}', [EstructuraOrganizacionalController::class, 'update'])->name('estructura.update');
        Route::patch('/estructura-organizacional/{unidad}/activo', [EstructuraOrganizacionalController::class, 'toggle'])->name('estructura.toggle');
        Route::get('/tipos-organizacionales', [TipoUnidadOrganizacionalController::class, 'index'])->name('tipos-organizacionales.index');
        Route::post('/tipos-organizacionales', [TipoUnidadOrganizacionalController::class, 'store'])->name('tipos-organizacionales.store');
        Route::put('/tipos-organizacionales/{tipo}', [TipoUnidadOrganizacionalController::class, 'update'])->name('tipos-organizacionales.update');
        Route::get('/responsabilidades', [UnidadResponsableController::class, 'index'])->name('responsabilidades.index');
        Route::get('/responsabilidades/crear', [UnidadResponsableController::class, 'create'])->name('responsabilidades.create');
        Route::post('/responsabilidades', [UnidadResponsableController::class, 'store'])->name('responsabilidades.store');
        Route::get('/responsabilidades/{responsabilidad}/editar', [UnidadResponsableController::class, 'edit'])->name('responsabilidades.edit');
        Route::put('/responsabilidades/{responsabilidad}', [UnidadResponsableController::class, 'update'])->name('responsabilidades.update');
        Route::patch('/responsabilidades/{responsabilidad}/cerrar', [UnidadResponsableController::class, 'close'])->name('responsabilidades.close');
        Route::get('/accesos-operativos', [UserUnidadAccesoController::class, 'index'])->name('accesos.index');
        Route::get('/accesos-operativos/crear', [UserUnidadAccesoController::class, 'create'])->name('accesos.create');
        Route::post('/accesos-operativos', [UserUnidadAccesoController::class, 'store'])->name('accesos.store');
        Route::get('/accesos-operativos/{acceso}/editar', [UserUnidadAccesoController::class, 'edit'])->name('accesos.edit');
        Route::put('/accesos-operativos/{acceso}', [UserUnidadAccesoController::class, 'update'])->name('accesos.update');
        Route::patch('/accesos-operativos/{acceso}/cerrar', [UserUnidadAccesoController::class, 'close'])->name('accesos.close');
        Route::get('/calidades-contractuales', [CalidadContractualController::class, 'index'])->name('calidades.index');
        Route::post('/calidades-contractuales', [CalidadContractualController::class, 'store'])->name('calidades.store');
        Route::put('/calidades-contractuales/{calidad}', [CalidadContractualController::class, 'update'])->name('calidades.update');
        Route::patch('/calidades-contractuales/{calidad}/activo', [CalidadContractualController::class, 'toggle'])->name('calidades.toggle');
        Route::get('/dotacion', [DotacionController::class, 'index'])->name('dotacion.index');
        Route::get('/dotacion/crear', [DotacionController::class, 'create'])->name('dotacion.create');
        Route::post('/dotacion', [DotacionController::class, 'store'])->name('dotacion.store');
        Route::get('/dotacion/personas/{persona}', [DotacionController::class, 'persona'])->name('dotacion.persona');
        Route::get('/dotacion/{vinculo}/editar', [DotacionController::class, 'edit'])->name('dotacion.edit');
        Route::put('/dotacion/{vinculo}', [DotacionController::class, 'update'])->name('dotacion.update');
        Route::patch('/dotacion/{vinculo}/cerrar', [DotacionController::class, 'close'])->name('dotacion.close');
    });
});

require __DIR__.'/auth.php';
