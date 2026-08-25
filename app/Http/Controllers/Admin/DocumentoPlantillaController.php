<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentoPlantilla;
use Illuminate\View\View;

class DocumentoPlantillaController extends Controller
{
    public function index(): View
    {
        return view('admin.plantillas.index', [
            'plantillas' => DocumentoPlantilla::query()->with(['tipoTramite', 'tipoDocumento'])->orderBy('codigo')->orderByDesc('version')->get(),
        ]);
    }
}
