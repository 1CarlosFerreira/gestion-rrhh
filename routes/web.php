<?php

use App\Http\Controllers\Admin\CalidadContractualController;
use App\Http\Controllers\Admin\DotacionController;
use App\Http\Controllers\Admin\EstructuraOrganizacionalController;
use App\Http\Controllers\Admin\PersonaController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\TipoUnidadOrganizacionalController;
use App\Http\Controllers\Admin\UnidadResponsableController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserUnidadAccesoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GestionPersonasReemplazoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReemplazoController;
use App\Http\Controllers\ReemplazoDocumentoController;
use App\Http\Controllers\ReemplazoFormalizacionController;
use App\Http\Controllers\TramiteAdjuntoController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/reemplazos/crear', [ReemplazoController::class, 'create'])->name('reemplazos.create');
    Route::post('/reemplazos', [ReemplazoController::class, 'store'])->name('reemplazos.store');
    Route::get('/reemplazos/funcionarios', [ReemplazoController::class, 'funcionarios'])->name('reemplazos.funcionarios');
    Route::get('/reemplazos/{tramite}/editar', [ReemplazoController::class, 'edit'])->name('reemplazos.edit');
    Route::put('/reemplazos/{tramite}', [ReemplazoController::class, 'update'])->name('reemplazos.update');
    Route::post('/reemplazos/{tramite}/enviar', [ReemplazoController::class, 'send'])->name('reemplazos.send');
    Route::post('/reemplazos/{tramite}/documento', [ReemplazoDocumentoController::class, 'store'])->name('reemplazos.documentos.store');
    Route::get('/reemplazos/{tramite}/documentos/{documento}/descargar', [ReemplazoDocumentoController::class, 'download'])->name('reemplazos.documentos.download');
    Route::post('/reemplazos/{tramite}/formalizar', [ReemplazoFormalizacionController::class, 'store'])->name('reemplazos.formalizaciones.store');
    Route::post('/reemplazos/{tramite}/adjuntos', [TramiteAdjuntoController::class, 'store'])->name('reemplazos.adjuntos.store');
    Route::post('/reemplazos/{tramite}/adjuntos/{adjunto}/version', [TramiteAdjuntoController::class, 'version'])->name('reemplazos.adjuntos.version');
    Route::get('/reemplazos/{tramite}/adjuntos/{adjunto}/descargar', [TramiteAdjuntoController::class, 'download'])->name('reemplazos.adjuntos.download');
    Route::get('/reemplazos/{tramite}/adjuntos/{adjunto}/ver', [TramiteAdjuntoController::class, 'view'])->name('reemplazos.adjuntos.view');
    Route::patch('/reemplazos/{tramite}/adjuntos/{adjunto}/anular', [TramiteAdjuntoController::class, 'annul'])->name('reemplazos.adjuntos.annul');

    Route::prefix('gestion-personas/reemplazos')->name('gestion-personas.reemplazos.')->group(function (): void {
        Route::get('/', [GestionPersonasReemplazoController::class, 'index'])->name('index');
        Route::get('/{tramite}', [GestionPersonasReemplazoController::class, 'show'])->name('show');
        Route::post('/{tramite}/iniciar', [GestionPersonasReemplazoController::class, 'start'])->name('start');
        Route::put('/{tramite}/revision', [GestionPersonasReemplazoController::class, 'save'])->name('save');
        Route::post('/{tramite}/devolver', [GestionPersonasReemplazoController::class, 'return'])->name('return');
        Route::post('/{tramite}/aprobar', [GestionPersonasReemplazoController::class, 'approve'])->name('approve');
    });

    Route::middleware('can:admin.usuarios')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/usuarios', [UserController::class, 'index'])->name('usuarios.index');
        Route::get('/personas/{persona}/usuario/crear', [UserController::class, 'createForPersona'])->name('usuarios.create-for-persona');
        Route::post('/personas/{persona}/usuario', [UserController::class, 'storeForPersona'])->name('usuarios.store-for-persona');
        Route::put('/usuarios/{user}/roles', [UserController::class, 'updateRoles'])->name('usuarios.roles.update');
        Route::patch('/usuarios/{user}/activo', [UserController::class, 'toggleActive'])->name('usuarios.activo');
    });

    Route::middleware('can:admin.roles_permisos')->prefix('admin')->name('admin.roles-permisos.')->group(function (): void {
        Route::get('/roles-permisos', [RolePermissionController::class, 'index'])->name('index');
        Route::get('/roles-permisos/crear', [RolePermissionController::class, 'create'])->name('create');
        Route::post('/roles-permisos', [RolePermissionController::class, 'store'])->name('store');
        Route::get('/roles-permisos/{role}/editar', [RolePermissionController::class, 'edit'])->name('edit');
        Route::put('/roles-permisos/{role}', [RolePermissionController::class, 'update'])->name('update');
    });

    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/estructura-organizacional', [EstructuraOrganizacionalController::class, 'index'])->name('estructura.index');
        Route::get('/estructura-organizacional/organigrama', [EstructuraOrganizacionalController::class, 'organigrama'])->name('estructura.organigrama');
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

        Route::middleware('can:dotacion.ver')->group(function (): void {
            Route::get('/dotacion', [DotacionController::class, 'index'])->name('dotacion.index');
            Route::get('/dotacion/personas/{persona}', [DotacionController::class, 'persona'])->name('dotacion.persona');
        });
        Route::middleware('can:dotacion.gestionar')->group(function (): void {
            Route::get('/dotacion/crear', [DotacionController::class, 'create'])->name('dotacion.create');
            Route::post('/dotacion', [DotacionController::class, 'store'])->name('dotacion.store');
            Route::get('/dotacion/{vinculo}/editar', [DotacionController::class, 'edit'])->name('dotacion.edit');
            Route::put('/dotacion/{vinculo}', [DotacionController::class, 'update'])->name('dotacion.update');
            Route::patch('/dotacion/{vinculo}/cerrar', [DotacionController::class, 'close'])->name('dotacion.close');
        });

        Route::middleware('can:personas.ver')->group(function (): void {
            Route::get('/personas', [PersonaController::class, 'index'])->name('personas.index');
            Route::middleware('can:personas.gestionar')->group(function (): void {
                Route::get('/personas/crear', [PersonaController::class, 'create'])->name('personas.create');
                Route::post('/personas', [PersonaController::class, 'store'])->name('personas.store');
                Route::get('/personas/{persona}/editar', [PersonaController::class, 'edit'])->name('personas.edit');
                Route::put('/personas/{persona}', [PersonaController::class, 'update'])->name('personas.update');
                Route::patch('/personas/{persona}/activo', [PersonaController::class, 'toggleActive'])->name('personas.activo');
            });
            Route::get('/personas/{persona}', [PersonaController::class, 'show'])->name('personas.show');
        });
    });
});

require __DIR__.'/auth.php';
