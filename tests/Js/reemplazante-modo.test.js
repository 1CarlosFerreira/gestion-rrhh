import assert from 'node:assert/strict';
import test from 'node:test';
import { cambiarModoReemplazante, cambiarPersonaSeleccionada, seleccionarPersonaReemplazante } from '../../resources/js/reemplazante-modo.js';

function estado() {
    return {
        modo: 'existente',
        personaId: '27',
        seleccionada: { id: '27' },
        buscar: '12345678-5',
        opcionesBase: [{ id: '27' }],
        opciones: [{ id: '99' }],
        vinculoBase: '41',
        rutNuevo: '98765432-1',
        nombresNuevos: 'Nombre ficticio',
        apellidoPaternoNuevo: 'Inventado',
        apellidoMaternoNuevo: 'Ejemplo',
        estamentoId: '3',
        profesionId: '4',
        calidadId: '2',
        cargo: 'Cargo de prueba',
        fechaDesde: '2026-10-13',
        solicitudBusqueda: 1,
        solicitudRut: 2,
        buscando: true,
        mensajeBusqueda: 'Buscando',
        mensajeRut: 'Verificando',
    };
}

test('al elegir nuevo se limpian los datos de la persona anterior y se conserva el período', () => {
    const datos = estado();
    cambiarModoReemplazante(datos, 'nuevo');

    assert.equal(datos.modo, 'nuevo');
    assert.equal(datos.personaId, '');
    assert.equal(datos.seleccionada, null);
    assert.equal(datos.buscar, '');
    assert.equal(datos.vinculoBase, '');
    assert.deepEqual(datos.opciones, datos.opcionesBase);
    assert.equal(datos.estamentoId, '');
    assert.equal(datos.profesionId, '');
    assert.equal(datos.calidadId, '');
    assert.equal(datos.cargo, '');
    assert.equal(datos.fechaDesde, '2026-10-13');
    assert.equal(datos.solicitudBusqueda, 2);
    assert.equal(datos.solicitudRut, 3);
});

test('al volver a existente se limpian los datos de registro nuevo', () => {
    const datos = estado();
    cambiarModoReemplazante(datos, 'nuevo');
    cambiarModoReemplazante(datos, 'existente');

    assert.equal(datos.modo, 'existente');
    assert.equal(datos.rutNuevo, '');
    assert.equal(datos.nombresNuevos, '');
    assert.equal(datos.apellidoPaternoNuevo, '');
    assert.equal(datos.apellidoMaternoNuevo, '');
    assert.equal(datos.cargo, '');
    assert.equal(datos.fechaDesde, '2026-10-13');
    assert.equal(datos.mensajeRut, '');
    assert.equal(datos.buscando, false);
});

test('elegir la opción actual o una opción desconocida no altera los datos', () => {
    const datos = estado();
    const original = structuredClone(datos);

    cambiarModoReemplazante(datos, 'existente');
    cambiarModoReemplazante(datos, 'desconocido');

    assert.deepEqual(datos, original);
});

test('seleccionar una coincidencia conserva el ID y RUT para el envío y limpia la propuesta anterior', () => {
    const datos = estado();
    const persona = { id: '42', nombre: 'Persona de Prueba', rut: '98000042-5', unidad_vigente: 'Unidad Ficticia' };

    seleccionarPersonaReemplazante(datos, persona);

    assert.equal(datos.personaId, '42');
    assert.deepEqual(datos.seleccionada, persona);
    assert.equal(datos.buscar, persona.rut);
    assert.deepEqual(datos.opciones, []);
    assert.equal(datos.cargo, '');
    assert.equal(datos.solicitudBusqueda, 2);
});

test('cambiar la persona seleccionada vuelve a una búsqueda vacía', () => {
    const datos = estado();
    cambiarPersonaSeleccionada(datos);

    assert.equal(datos.personaId, '');
    assert.equal(datos.seleccionada, null);
    assert.equal(datos.buscar, '');
    assert.deepEqual(datos.opciones, []);
    assert.equal(datos.estamentoId, '');
    assert.equal(datos.fechaDesde, '2026-10-13');
});
