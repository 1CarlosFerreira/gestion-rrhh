<?php

use App\Http\Controllers\Admin\EstructuraOrganizacionalController;
use App\Http\Controllers\Admin\TipoUnidadOrganizacionalController;
use App\Http\Controllers\Admin\UserController;
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
    });
});

require __DIR__.'/auth.php';
