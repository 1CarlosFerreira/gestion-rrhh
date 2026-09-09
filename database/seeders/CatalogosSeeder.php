<?php

namespace Database\Seeders;

use App\Models\Estamento;
use App\Models\Profesion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogosSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Médico', 'Odontólogo', 'Bioquímico', 'Químico Farmacéutico', 'Profesional', 'Técnico', 'Administrativo', 'Auxiliar'] as $nombre) {
            Estamento::query()->updateOrCreate(
                ['codigo' => Str::upper(Str::slug($nombre, '_'))],
                ['nombre' => $nombre, 'activo' => true],
            );
        }

        $profesiones = [
            'ADMINISTRADOR_PUBLICO' => 'Administrador Público',
            'ADMINISTRATIVO' => 'Administrativo',
            'ASISTENTE_SOCIAL' => 'Asistente Social',
            'AUXILIAR' => 'Auxiliar',
            'BIOQUIMICO' => 'Bioquímico',
            'CIRUJANO_DENTISTA' => 'Cirujano Dentista',
            'EDUCADORA_PARVULOS' => 'Educadora de Párvulos',
            'ENFERMERO' => 'Enfermero/a',
            'FONOAUDIOLOGO' => 'Fonoaudiólogo/a',
            'INGENIERO_BIOMEDICO' => 'Ingeniero Biomédico',
            'INGENIERO_INDUSTRIAL' => 'Ingeniero Industrial',
            'INGENIERO_COMERCIAL' => 'Ingeniero/a Comercial',
            'INGENIERO_ADMINISTRACION' => 'Ingeniero/a en Administración',
            'INGENIERO_CONTROL_GESTION' => 'Ingeniero/a en Control de Gestión',
            'INGENIERO_INFORMATICO' => 'Ingeniero/a en Informática',
            'INGENIERO_PREVENCION_RIESGOS' => 'Ingeniero/a en Prevención de Riesgos',
            'KINESIOLOGO' => 'Kinesiólogo/a',
            'MATRON' => 'Matrón/a',
            'MEDICO_CIRUJANO' => 'Médico Cirujano',
            'NUTRICIONISTA' => 'Nutricionista',
            'OTROS_PROFESIONALES' => 'Otros Profesionales',
            'OTROS_TECNICOS' => 'Otros Técnicos',
            'OTROS_TECNICOS_SALUD' => 'Otros Técnicos de Salud',
            'PSICOLOGO' => 'Psicólogo/a',
            'QUIMICO_FARMACEUTICO' => 'Químico Farmacéutico',
            'RELACIONADOR_PUBLICO' => 'Relacionador Público',
            'SOCIOLOGO' => 'Sociólogo/a',
            'TECNICO_EDUCACION_PARVULARIA' => 'Técnico en Educación Parvularia',
            'TECNICO_ENFERMERIA' => 'Técnico en Enfermería',
            'TECNOLOGO_MEDICO' => 'Tecnólogo Médico',
            'TECNOLOGO_MEDICO_IMAGENOLOGIA' => 'Tecnólogo Médico Imagenología',
            'TECNOLOGO_MEDICO_OFTALMOLOGIA' => 'Tecnólogo Médico Oftalmología',
            'TECNOLOGO_MEDICO_OTORRINOLARINGOLOGIA' => 'Tecnólogo Médico Otorrinolaringología',
            'TERAPEUTA_OCUPACIONAL' => 'Terapeuta Ocupacional',
            'TRABAJADOR_SOCIAL' => 'Trabajador/a Social',
        ];

        foreach ($profesiones as $codigo => $nombre) {
            Profesion::query()->updateOrCreate(
                ['codigo' => $codigo],
                [
                    'estamento_id' => null,
                    'nombre' => $nombre,
                    'activo' => true,
                ],
            );
        }
    }
}
