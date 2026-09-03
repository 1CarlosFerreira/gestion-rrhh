<?php

namespace Database\Seeders;

use App\Models\TipoUnidadOrganizacional;
use App\Models\UnidadOrganizacional;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EstructuraOrganizacionalOficialSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = TipoUnidadOrganizacional::query()->pluck('id', 'codigo');

        DB::transaction(function () use ($tipos): void {
            foreach ($this->unidades() as [$codigo, $nombre, $tipo, $padre, $orden]) {
                $parentId = $padre === null ? null : UnidadOrganizacional::query()->where('codigo', $padre)->value('id');
                if ($padre !== null && $parentId === null) {
                    throw new \LogicException("No se encontró la unidad padre {$padre}.");
                }

                UnidadOrganizacional::query()->updateOrCreate(
                    ['codigo' => $codigo],
                    ['nombre' => $nombre, 'tipo_unidad_organizacional_id' => $tipos->get($tipo) ?? throw new \LogicException("No existe el tipo {$tipo}."), 'parent_id' => $parentId, 'activo' => true, 'orden' => $orden],
                );
            }
        });
    }

    private function unidades(): array
    {
        return [
            ['DIR', 'Dirección', 'DIRECCION', null, 1],
            ['DIR-SSOC', 'Oficina Servicio Social', 'OFICINA', 'DIR', 10],
            ['DIR-SEC', 'Secretaría de Dirección', 'OFICINA', 'DIR', 20],
            ['DIR-PARTES', 'Oficina de Partes', 'OFICINA', 'DIR', 30],
            ['DIR-CALIDAD', 'Unidad de Calidad y Seguridad del Paciente', 'UNIDAD', 'DIR', 40],
            ['DIR-IAAS', 'Unidad de Prevención y Control IAAS', 'UNIDAD', 'DIR', 50],
            ['DIR-ENF', 'Coordinación de Enfermería', 'OTRO', 'DIR', 60],
            ['DIR-CGEST', 'Departamento de Control de Gestión', 'DEPARTAMENTO', 'DIR', 70],
            ['DIR-CGEST-EST', 'Unidad Estadística', 'UNIDAD', 'DIR-CGEST', 10],
            ['DIR-CGEST-GRD', 'Unidad GRD', 'UNIDAD', 'DIR-CGEST', 20],
            ['DIR-CGEST-CC', 'Unidad Centro de Costos', 'UNIDAD', 'DIR-CGEST', 30],
            ['DIR-COM', 'Comunicaciones', 'OTRO', 'DIR', 80],
            ['DIR-PART', 'Unidad Participación Ciudadana y Satisfacción Usuaria', 'UNIDAD', 'DIR', 90],
            ['SDGA', 'Subdirección de Gestión Asistencial', 'SUBDIRECCION', 'DIR', 100],
            ['SDGA-DEMANDA', 'Unidad Gestión de la Demanda', 'UNIDAD', 'SDGA', 10],
            ['SDGA-DEMANDA-PREQ', 'Unidad Prequirúrgica', 'UNIDAD', 'SDGA-DEMANDA', 10],
            ['SDGA-DEMANDA-GES', 'Unidad GES', 'UNIDAD', 'SDGA-DEMANDA', 20],
            ['SDGA-ABIERTA', 'Área Atención Abierta', 'AREA', 'SDGA', 20],
            ['SDGA-ABIERTA-ODONT', 'Servicio Especialidades Odontológicas', 'SERVICIO', 'SDGA-ABIERTA', 10],
            ['SDGA-ABIERTA-MED', 'Consultorio Especialidades Médicas', 'OTRO', 'SDGA-ABIERTA', 20],
            ['SDGA-ABIERTA-SM', 'Centro de Salud Mental', 'OTRO', 'SDGA-ABIERTA', 30],
            ['SDGA-ABIERTA-GIN', 'Consultorio Especialidades Ginecoobstétricas', 'OTRO', 'SDGA-ABIERTA', 40],
            ['SDGA-ABIERTA-MATR', 'Coordinación Matronería', 'OTRO', 'SDGA-ABIERTA', 50],
            ['SDGA-CERRADA', 'Área Atención Cerrada', 'AREA', 'SDGA', 30],
            ['SDGA-CERRADA-CB', 'Servicio Médico-Quirúrgico Adulto Cuidados Básicos', 'SERVICIO', 'SDGA-CERRADA', 10],
            ['SDGA-CERRADA-CM', 'Servicio Médico-Quirúrgico Adulto Cuidados Medios', 'SERVICIO', 'SDGA-CERRADA', 20],
            ['SDGA-CERRADA-NEO', 'Servicio Médico-Quirúrgico Cuidados Medios y Neonatología', 'SERVICIO', 'SDGA-CERRADA', 30],
            ['SDGA-CERRADA-GO', 'Servicio Ginecología y Obstetricia', 'SERVICIO', 'SDGA-CERRADA', 40],
            ['SDGA-CERRADA-PENS', 'Servicio Pensionado', 'SERVICIO', 'SDGA-CERRADA', 50],
            ['SDGA-CERRADA-UTI', 'Unidad de Tratamiento Intermedio', 'UNIDAD', 'SDGA-CERRADA', 60],
            ['SDGA-CERRADA-HD', 'Unidad Hospitalización Domiciliaria', 'UNIDAD', 'SDGA-CERRADA', 70],
            ['SDGA-CERRADA-PAB', 'Unidad de Pabellón y Recuperación', 'UNIDAD', 'SDGA-CERRADA', 80],
            ['SDGA-ADT', 'Área Apoyo Diagnóstico y Terapéutico', 'AREA', 'SDGA', 40],
            ['SDGA-ADT-ENDO', 'Unidad de Procedimientos Endoscópicos', 'UNIDAD', 'SDGA-ADT', 10],
            ['SDGA-ADT-EST', 'Unidad de Esterilización', 'UNIDAD', 'SDGA-ADT', 20],
            ['SDGA-ADT-FARM', 'Unidad de Farmacia', 'UNIDAD', 'SDGA-ADT', 30],
            ['SDGA-ADT-ALIM', 'Unidad Alimentación y Nutrición', 'UNIDAD', 'SDGA-ADT', 40],
            ['SDGA-ADT-IMG', 'Unidad de Imagenología', 'UNIDAD', 'SDGA-ADT', 50],
            ['SDGA-ADT-REH', 'Unidad de Rehabilitación', 'UNIDAD', 'SDGA-ADT', 60],
            ['SDGA-ADT-LAB', 'Unidad Laboratorio Clínico, UMT y Biología Molecular', 'UNIDAD', 'SDGA-ADT', 70],
            ['SDGA-UEH', 'Unidad de Emergencia Hospitalaria', 'UNIDAD', 'SDGA', 50],
            ['SDGDP', 'Subdirección de Gestión y Desarrollo de las Personas', 'SUBDIRECCION', 'DIR', 110],
            ['SDGDP-DGP', 'Área Desarrollo y Gestión de las Personas', 'AREA', 'SDGDP', 10],
            ['SDGDP-DGP-GP', 'Unidad Gestión de las Personas', 'UNIDAD', 'SDGDP-DGP', 10],
            ['SDGDP-DGP-CAP', 'Unidad de Capacitación y RAD', 'UNIDAD', 'SDGDP-DGP', 20],
            ['SDGDP-DGP-RS', 'Unidad Reclutamiento y Selección', 'UNIDAD', 'SDGDP-DGP', 30],
            ['SDGDP-CV', 'Área Calidad de Vida', 'AREA', 'SDGDP', 20],
            ['SDGDP-CV-PRSO', 'Unidad Prevención de Riesgo y Salud Ocupacional', 'UNIDAD', 'SDGDP-CV', 10],
            ['SDGDP-CV-SC', 'Sala Cuna, Jardín Infantil y Club Escolar', 'OTRO', 'SDGDP-CV', 20],
            ['SDGDP-CV-AL', 'Unidad Ambientes Laborales', 'UNIDAD', 'SDGDP-CV', 30],
            ['SDGDP-CV-BIEN', 'Unidad Bienestar', 'UNIDAD', 'SDGDP-CV', 40],
            ['SDGADM', 'Subdirección de Gestión Administrativa', 'SUBDIRECCION', 'DIR', 120],
            ['SDGADM-ABAST', 'Unidad de Abastecimiento', 'UNIDAD', 'SDGADM', 10],
            ['SDGADM-ABAST-BC', 'Bodega Central', 'OTRO', 'SDGADM-ABAST', 10],
            ['SDGADM-ABAST-BFI', 'Bodega Fármacos e Insumos', 'OTRO', 'SDGADM-ABAST', 20],
            ['SDGADM-CONT', 'Unidad de Contabilidad y Finanzas', 'UNIDAD', 'SDGADM', 20],
            ['SDGADM-INF', 'Unidad de Informática', 'UNIDAD', 'SDGADM', 30],
            ['SDGADM-SSGG', 'Unidad de Servicios Generales', 'UNIDAD', 'SDGADM', 40],
            ['SDGADM-SSGG-MOV', 'Movilización', 'OTRO', 'SDGADM-SSGG', 10],
            ['SDGADM-SSGG-ASEO', 'Aseo', 'OTRO', 'SDGADM-SSGG', 20],
            ['SDGADM-SSGG-LAV', 'Lavandería y Ropería', 'OTRO', 'SDGADM-SSGG', 30],
            ['SDGADM-EII', 'Unidad de Equipamiento Industrial e Infraestructura', 'UNIDAD', 'SDGADM', 50],
            ['SDGADM-EM', 'Unidad de Equipamiento Médico', 'UNIDAD', 'SDGADM', 60],
        ];
    }
}
