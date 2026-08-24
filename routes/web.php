<?php

use App\Http\Controllers\Admin\CatalogoController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DotacionController;
use App\Http\Controllers\GestionPersonasBandejaController;
use App\Http\Controllers\PersonaController;
use App\Http\Controllers\PersonaSearchController;
use App\Http\Controllers\PersonaUnidadVinculoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TramiteController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/tramites', [TramiteController::class, 'index'])->name('tramites.index');
    Route::get('/tramites/nuevo', [TramiteController::class, 'create'])->name('tramites.create');
    Route::post('/tramites', [TramiteController::class, 'store'])->name('tramites.store');
    Route::get('/tramites/{tramite}', [TramiteController::class, 'show'])->name('tramites.show');
    Route::get('/gestion-personas/bandeja', GestionPersonasBandejaController::class)->name('gestion-personas.bandeja');

    Route::get('/personas/crear/nueva', [PersonaController::class, 'create'])->middleware('can:personas.gestionar')->name('personas.create');

    Route::middleware('can:personas.ver')->group(function () {
        Route::get('/personas', [PersonaController::class, 'index'])->name('personas.index');
        Route::get('/personas/buscar', PersonaSearchController::class)->middleware('throttle:30,1')->name('personas.buscar');
        Route::get('/personas/{persona}', [PersonaController::class, 'show'])->name('personas.show');
    });

    Route::middleware('can:personas.gestionar')->group(function () {
        Route::post('/personas', [PersonaController::class, 'store'])->name('personas.store');
        Route::get('/personas/{persona}/editar', [PersonaController::class, 'edit'])->name('personas.edit');
        Route::put('/personas/{persona}', [PersonaController::class, 'update'])->name('personas.update');
        Route::patch('/personas/{persona}/activo', [PersonaController::class, 'toggleActive'])->name('personas.activo');
        Route::post('/personas/{persona}/vinculos', [PersonaUnidadVinculoController::class, 'store'])->name('personas.vinculos.store');
        Route::put('/personas/{persona}/vinculos/{vinculo}', [PersonaUnidadVinculoController::class, 'update'])->name('personas.vinculos.update');
    });

    Route::get('/dotacion', [DotacionController::class, 'index'])->middleware('can:dotacion.ver')->name('dotacion.index');

    Route::middleware('can:admin.usuarios')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/usuarios', [UserController::class, 'index'])->name('usuarios.index');
        Route::put('/usuarios/{user}/roles', [UserController::class, 'updateRoles'])->name('usuarios.roles.update');
        Route::patch('/usuarios/{user}/activo', [UserController::class, 'toggleActive'])->name('usuarios.activo');
    });

    Route::middleware('can:usuarios.unidades.gestionar')->prefix('admin')->name('admin.')->group(function () {
        Route::post('/usuarios/{user}/unidades', [UserController::class, 'storeUnidad'])->name('usuarios.unidades.store');
        Route::patch('/usuarios/{user}/unidades/{asignacion}', [UserController::class, 'toggleUnidad'])->name('usuarios.unidades.toggle');
    });

    Route::middleware('can:admin.catalogos')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/catalogos', [CatalogoController::class, 'index'])->name('catalogos.index');
        Route::patch('/catalogos/{catalogo}/{id}/activo', [CatalogoController::class, 'toggleActive'])->name('catalogos.activo');
    });
});

require __DIR__.'/auth.php';
