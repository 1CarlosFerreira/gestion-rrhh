<?php

use App\Http\Controllers\Admin\CatalogoController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::middleware('can:admin.usuarios')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/usuarios', [UserController::class, 'index'])->name('usuarios.index');
        Route::put('/usuarios/{user}/roles', [UserController::class, 'updateRoles'])->name('usuarios.roles.update');
        Route::patch('/usuarios/{user}/activo', [UserController::class, 'toggleActive'])->name('usuarios.activo');
    });

    Route::middleware('can:admin.catalogos')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/catalogos', [CatalogoController::class, 'index'])->name('catalogos.index');
        Route::patch('/catalogos/{catalogo}/{id}/activo', [CatalogoController::class, 'toggleActive'])->name('catalogos.activo');
    });
});

require __DIR__.'/auth.php';
