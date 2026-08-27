<?php

use App\Http\Controllers\Admin\CatalogoController;
use App\Http\Controllers\Admin\DocumentoPlantillaController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocDigitalController;
use App\Http\Controllers\DotacionController;
use App\Http\Controllers\GestionPersonasBandejaController;
use App\Http\Controllers\HorasExtraController;
use App\Http\Controllers\PersonaController;
use App\Http\Controllers\PersonaSearchController;
use App\Http\Controllers\PersonaUnidadVinculoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReemplazoController;
use App\Http\Controllers\TramiteAdjuntoController;
use App\Http\Controllers\TramiteController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/reemplazos/crear', [ReemplazoController::class, 'create'])->name('reemplazos.create');
    Route::post('/reemplazos', [ReemplazoController::class, 'store'])->name('reemplazos.store');
    Route::get('/reemplazos/{tramite}/editar', [ReemplazoController::class, 'edit'])->name('reemplazos.edit');
    Route::put('/reemplazos/{tramite}', [ReemplazoController::class, 'update'])->name('reemplazos.update');
    Route::post('/reemplazos/{tramite}/enviar', [ReemplazoController::class, 'send'])->name('reemplazos.send');
    Route::post('/reemplazos/{tramite}/iniciar-revision', [ReemplazoController::class, 'startReview'])->name('reemplazos.review.start');
    Route::put('/reemplazos/{tramite}/revision', [ReemplazoController::class, 'saveReview'])->name('reemplazos.review.save');
    Route::post('/reemplazos/{tramite}/devolver', [ReemplazoController::class, 'returnCorrection'])->name('reemplazos.return');
    Route::post('/reemplazos/{tramite}/completar-revision', [ReemplazoController::class, 'completeReview'])->name('reemplazos.review.complete');

    Route::get('/horas-extra/crear', [HorasExtraController::class, 'create'])->name('horas-extra.create');
    Route::post('/horas-extra', [HorasExtraController::class, 'store'])->name('horas-extra.store');
    Route::get('/horas-extra/{tramite}/editar', [HorasExtraController::class, 'edit'])->name('horas-extra.edit');
    Route::put('/horas-extra/{tramite}', [HorasExtraController::class, 'update'])->name('horas-extra.update');
    Route::post('/horas-extra/{tramite}/transiciones/{action}', [HorasExtraController::class, 'transition'])->name('horas-extra.transition');
    Route::post('/horas-extra/{tramite}/funcionarios/{funcionario}/planilla', [HorasExtraController::class, 'upload'])->name('horas-extra.planillas.upload');
    Route::put('/horas-extra/{tramite}/funcionarios/{funcionario}/horas', [HorasExtraController::class, 'hours'])->name('horas-extra.hours');
    Route::post('/horas-extra/{tramite}/funcionarios/{funcionario}/revision', [HorasExtraController::class, 'review'])->name('horas-extra.review');
    Route::post('/horas-extra/{tramite}/finalizar-revision', [HorasExtraController::class, 'finishReview'])->name('horas-extra.review.finish');
    Route::get('/horas-extra/{tramite}/informe-tecnico', [HorasExtraController::class, 'prepareReport'])->name('horas-extra.report.prepare');
    Route::put('/horas-extra/{tramite}/informe-tecnico', [HorasExtraController::class, 'saveReport'])->name('horas-extra.report.save');
    Route::post('/horas-extra/{tramite}/informe-tecnico/generar', [HorasExtraController::class, 'generateReport'])->name('horas-extra.report.generate');

    Route::get('/tramites', [TramiteController::class, 'index'])->name('tramites.index');
    Route::get('/tramites/nuevo', [TramiteController::class, 'create'])->name('tramites.create');
    Route::post('/tramites', [TramiteController::class, 'store'])->name('tramites.store');
    Route::post('/tramites/{tramite}/adjuntos', [TramiteAdjuntoController::class, 'store'])->name('tramites.adjuntos.store');
    Route::post('/tramites/{tramite}/docdigital/envios', [DocDigitalController::class, 'store'])->name('tramites.docdigital.store');
    Route::post('/tramites/{tramite}/docdigital/formalizacion', [DocDigitalController::class, 'formalize'])->name('tramites.docdigital.formalize');
    Route::post('/tramites/{tramite}/adjuntos/{adjunto}/versiones', [TramiteAdjuntoController::class, 'version'])->name('tramites.adjuntos.version');
    Route::get('/tramites/{tramite}/adjuntos/{adjunto}/descargar', [TramiteAdjuntoController::class, 'download'])->name('tramites.adjuntos.download');
    Route::patch('/tramites/{tramite}/adjuntos/{adjunto}/anular', [TramiteAdjuntoController::class, 'annul'])->name('tramites.adjuntos.annul');
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
    Route::get('/dotacion/personas/{persona}', [DotacionController::class, 'show'])->middleware('can:dotacion.ver')->name('dotacion.show');

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
        Route::get('/plantillas-documentales', [DocumentoPlantillaController::class, 'index'])->name('plantillas.index');
    });
});

require __DIR__.'/auth.php';
