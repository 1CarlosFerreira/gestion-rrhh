export function cambiarModoReemplazante(estado, modo) {
    if (!['existente', 'nuevo'].includes(modo) || estado.modo === modo) return;

    estado.modo = modo;
    estado.solicitudBusqueda++;
    estado.solicitudRut++;
    estado.buscando = false;
    estado.mensajeBusqueda = '';
    estado.mensajeRut = '';
    estado.sinCoincidencias = false;
    estado.estamentoId = '';
    estado.profesionId = '';
    estado.calidadId = '';
    estado.cargo = '';

    if (modo === 'nuevo') {
        estado.personaId = '';
        estado.seleccionada = null;
        estado.buscar = '';
        estado.opciones = estado.opcionesBase;
        estado.vinculoBase = '';
    } else {
        estado.rutNuevo = '';
        estado.nombresNuevos = '';
        estado.apellidoPaternoNuevo = '';
        estado.apellidoMaternoNuevo = '';
    }
}

export function seleccionarPersonaReemplazante(estado, persona) {
    if (!persona?.id || !persona?.rut) return;

    estado.personaId = String(persona.id);
    estado.seleccionada = persona;
    estado.buscar = persona.rut;
    estado.opciones = [];
    estado.sinCoincidencias = false;
    estado.mensajeBusqueda = '';
    estado.solicitudBusqueda++;
    estado.vinculoBase = '';
    estado.estamentoId = '';
    estado.profesionId = '';
    estado.calidadId = '';
    estado.cargo = '';
}

export function cambiarPersonaSeleccionada(estado) {
    estado.personaId = '';
    estado.seleccionada = null;
    estado.buscar = '';
    estado.opciones = [];
    estado.sinCoincidencias = false;
    estado.mensajeBusqueda = '';
    estado.solicitudBusqueda++;
    estado.vinculoBase = '';
    estado.estamentoId = '';
    estado.profesionId = '';
    estado.calidadId = '';
    estado.cargo = '';
}
