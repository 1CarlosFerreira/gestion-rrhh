<?php

namespace Database\Seeders;

use App\Models\ClasificacionArea;
use App\Models\EstadoTramite;
use App\Models\Estamento;
use App\Models\Profesion;
use App\Models\TipoReemplazo;
use App\Models\TipoTramite;
use App\Models\TransicionEstado;
use App\Models\UnidadServicio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogosSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->unidades() as $nombre) {
            UnidadServicio::query()->updateOrCreate(['nombre' => $nombre], ['activo' => true]);
        }

        foreach (['Médico', 'Odontólogo', 'Bioquímico', 'Químico Farmacéutico', 'Profesional', 'Técnico', 'Administrativo', 'Auxiliar'] as $nombre) {
            Estamento::query()->updateOrCreate(['codigo' => Str::slug($nombre, '_')], ['nombre' => $nombre, 'activo' => true]);
        }

        foreach ($this->profesiones() as $nombre) {
            Profesion::query()->updateOrCreate(['codigo' => Str::slug($nombre, '_')], ['nombre' => $nombre, 'activo' => true]);
        }

        foreach (['Licencia Médica', 'Permiso Administrativo c/goce', 'Permiso Administrativo s/goce', 'Licencia Maternal', 'Cargo Vacante'] as $nombre) {
            TipoReemplazo::query()->updateOrCreate(['codigo' => Str::upper(Str::slug($nombre, '_'))], ['nombre' => $nombre, 'activo' => true]);
        }

        foreach (['Área crítica', 'Área semi-crítica', 'Área de apoyo asistencial', 'Área de apoyo administrativo y no crítico'] as $nombre) {
            ClasificacionArea::query()->updateOrCreate(['codigo' => Str::upper(Str::slug($nombre, '_'))], ['nombre' => $nombre, 'activo' => true]);
        }

        $this->seedTiposEstadosYTransiciones();
    }

    private function seedTiposEstadosYTransiciones(): void
    {
        foreach ($this->procesos() as $codigo => $definition) {
            $tipo = TipoTramite::query()->updateOrCreate(['codigo' => $codigo], ['nombre' => $definition['nombre'], 'activo' => true]);
            $estados = [];

            foreach ($definition['estados'] as $orden => $codigoEstado) {
                $estados[$codigoEstado] = EstadoTramite::query()->updateOrCreate(
                    ['tipo_tramite_id' => $tipo->id, 'codigo' => $codigoEstado],
                    ['nombre' => Str::headline(Str::lower($codigoEstado)), 'orden' => $orden + 1, 'activo' => true],
                );
            }

            foreach ($definition['transiciones'] as [$origen, $destino, $accion, $permiso, $observacion]) {
                TransicionEstado::query()->updateOrCreate(
                    ['tipo_tramite_id' => $tipo->id, 'estado_origen_id' => $estados[$origen]->id, 'codigo_accion' => $accion],
                    ['estado_destino_id' => $estados[$destino]->id, 'permiso_requerido' => $permiso, 'requiere_observacion' => $observacion, 'activo' => true],
                );
            }
        }
    }

    private function procesos(): array
    {
        return [
            'REEMPLAZO' => [
                'nombre' => 'Reemplazo',
                'estados' => ['BORRADOR', 'ENVIADA_GESTION_PERSONAS', 'EN_REVISION', 'DEVUELTA_CORRECCION', 'LISTA_GENERAR_DOCUMENTO', 'DOCUMENTO_GENERADO', 'ENVIADA_DOCDIGITAL', 'FORMALIZADA'],
                'transiciones' => [
                    ['BORRADOR', 'ENVIADA_GESTION_PERSONAS', 'ENVIAR_A_GESTION_PERSONAS', 'tramites.transicionar', false],
                    ['ENVIADA_GESTION_PERSONAS', 'EN_REVISION', 'INICIAR_REVISION', 'reemplazos.revisar_personal', false],
                    ['EN_REVISION', 'DEVUELTA_CORRECCION', 'DEVOLVER_CORRECCION', 'reemplazos.devolver', true],
                    ['DEVUELTA_CORRECCION', 'ENVIADA_GESTION_PERSONAS', 'ENVIAR_A_GESTION_PERSONAS', 'tramites.transicionar', false],
                    ['EN_REVISION', 'LISTA_GENERAR_DOCUMENTO', 'COMPLETAR_REVISION', 'reemplazos.revisar_personal', false],
                    ['LISTA_GENERAR_DOCUMENTO', 'DOCUMENTO_GENERADO', 'GENERAR_DOCUMENTO', 'documentos.generar', false],
                    ['DOCUMENTO_GENERADO', 'ENVIADA_DOCDIGITAL', 'REGISTRAR_ENVIO_DOCDIGITAL', 'docdigital.registrar_envio', false],
                    ['ENVIADA_DOCDIGITAL', 'FORMALIZADA', 'REGISTRAR_FORMALIZACION', 'docdigital.registrar_formalizacion', false],
                ],
            ],
            'HORAS_EXTRAORDINARIAS' => [
                'nombre' => 'Horas Extraordinarias',
                'estados' => ['BORRADOR', 'ENVIADA_GESTION_PERSONAS', 'PLANILLA_DISPONIBLE', 'EN_REVISION_JEFATURA', 'OBSERVADA', 'EN_CORRECCION_SIRH', 'CONFORME', 'INFORME_TECNICO_GENERADO', 'ENVIADA_DOCDIGITAL', 'FORMALIZADA'],
                'transiciones' => [
                    ['BORRADOR', 'ENVIADA_GESTION_PERSONAS', 'ENVIAR_A_GESTION_PERSONAS', 'tramites.transicionar', false],
                    ['ENVIADA_GESTION_PERSONAS', 'PLANILLA_DISPONIBLE', 'PUBLICAR_PLANILLAS', 'horas_extra.cargar_planilla', false],
                    ['PLANILLA_DISPONIBLE', 'EN_REVISION_JEFATURA', 'INICIAR_REVISION_JEFATURA', 'horas_extra.revisar_planilla', false],
                    ['EN_REVISION_JEFATURA', 'OBSERVADA', 'FINALIZAR_REVISION_OBSERVADA', 'horas_extra.revisar_planilla', false],
                    ['EN_REVISION_JEFATURA', 'CONFORME', 'FINALIZAR_REVISION_CONFORME', 'horas_extra.revisar_planilla', false],
                    ['OBSERVADA', 'EN_CORRECCION_SIRH', 'INICIAR_CORRECCION_SIRH', 'horas_extra.cargar_planilla', false],
                    ['EN_CORRECCION_SIRH', 'PLANILLA_DISPONIBLE', 'VOLVER_A_PLANILLA_DISPONIBLE', 'horas_extra.cargar_planilla', false],
                    ['CONFORME', 'INFORME_TECNICO_GENERADO', 'GENERAR_INFORME_TECNICO', 'documentos.generar', false],
                    ['INFORME_TECNICO_GENERADO', 'ENVIADA_DOCDIGITAL', 'REGISTRAR_ENVIO_DOCDIGITAL', 'docdigital.registrar_envio', false],
                    ['ENVIADA_DOCDIGITAL', 'FORMALIZADA', 'REGISTRAR_FORMALIZACION', 'docdigital.registrar_formalizacion', false],
                ],
            ],
        ];
    }

    private function profesiones(): array
    {
        return ['N/A', 'Médico Cirujano', 'Enfermero/a', 'Matrón/a', 'Técnologo Médico', 'Terapeuta Ocupacional', 'Kinesiólogo/a', 'Psicólogo/a', 'Nutricionista', 'Fonoaudiólogo/a', 'Químico Farmacéutico', 'Bioquímico', 'Cirujano Dentista', 'Técnico en Enfermería', 'Otros Técnicos de Salud', 'Otros Técnicos', 'Asitente Social', 'Trabajador/a Social', 'Ingeniero/a Comercial', 'Administrador Público', 'Ingeniero/a Prevención de Riesgos', 'Ingeniero/a en Administración', 'Relacionador Público', 'Ingeniero/a Control de Gestión', 'Sociólogo/a', 'Ingeniero/a en Informática', 'Otros Profesionales'];
    }

    private function unidades(): array
    {
        return ['Subdirección Gestión y Desarrollo de las Personas', 'Subdirección Recursos Físicos y Financieros', 'Subdirección Gestión Asistencial', 'Dirección del Hospital', 'S. Ginecología y Obstetricia', 'U. de Calidad y Seguridad del Paciente', 'U. Prevención y Control de IAAS', 'U. OIRS y Participación Ciudadana', 'U. Control de Gestión', 'Oficina S. Social', 'Oficina de Partes', 'Secretaría Dirección', 'Coordinación Enfermería', 'Consultorio Especialidades Médicas', 'Consultorio Especialidades Gineco-Obstétricas', 'Consultorio Especialidades Odontológicas', 'Centro de Salud Mental', 'U. de Emergencia Hospitalaria', 'S. Médico-Quirúrgico Adulto Cuidados Básicos', 'S. Médico-Quirúrgico Adulto Cuidados Medios', 'S. Médico Quirúrgico Infantil', 'S. Pensionado', 'U. de Paciente Crítico Adulto (UPC)', 'U. Pabellón, Recuperación y Esterilización', 'U. de Farmacia', 'U. de Rehabilitación', 'U. de Laboratorio', 'U. de Procedimientos Endoscópicos', 'U. Alimentación y Nutrición', 'U. Imagenología', 'Coordinación de Matronería', 'U. GES', 'U. Pre Quirúrgica', 'U. Gestión de la Demanda (Ex SOME)', 'U. de Gestión de Pacientes', 'U. de Gestión de las Personas', 'U. Capacitación y Relación Asistencial Docente (RAD)', 'U. de Reclutamiento y Selección', 'U. Prevención de Riesgos y Salud Ocupacional', 'Sala Cuna, Jardín Infantil y Club Escolar.', 'U. de Ambientes Laborales', 'U. Bienestar', 'U. Abastecimiento', 'U. Contabilidad y Finanzas', 'U. Informática', 'U. Servicios Generales', 'U. Equipamiento Industrial e Infraestructura', 'U. Equipamiento Médico'];
    }
}
