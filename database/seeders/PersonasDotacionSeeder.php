<?php

namespace Database\Seeders;

use App\Models\Estamento;
use App\Models\Persona;
use App\Models\Profesion;
use App\Models\UnidadServicio;
use App\Models\User;
use App\Models\UserUnidad;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PersonasDotacionSeeder extends Seeder
{
    public function run(): void
    {
        $urgencia = UnidadServicio::query()->where('nombre', 'U. de Emergencia Hospitalaria')->firstOrFail();
        $laboratorio = UnidadServicio::query()->where('nombre', 'U. de Laboratorio')->firstOrFail();
        $farmacia = UnidadServicio::query()->where('nombre', 'U. de Farmacia')->firstOrFail();
        $profesional = Estamento::query()->where('nombre', 'Profesional')->firstOrFail();
        $tecnico = Estamento::query()->where('nombre', 'Técnico')->firstOrFail();
        $enfermeria = Profesion::query()->where('nombre', 'Enfermero/a')->firstOrFail();
        $tens = Profesion::query()->where('nombre', 'Técnico en Enfermería')->firstOrFail();

        $ana = Persona::query()->updateOrCreate(['rut' => '12345678-5'], [
            'nombres' => 'Ana Prueba', 'apellido_paterno' => 'Demostración', 'apellido_materno' => 'Ficticia', 'active' => true,
        ]);
        $ana->vinculos()->updateOrCreate(['unidad_servicio_id' => $urgencia->id, 'start_date' => '2026-01-01'], [
            'estamento_id' => $profesional->id, 'profesion_id' => $enfermeria->id, 'cargo_texto' => 'Profesional clínico ficticio', 'status' => 'ACTIVO',
        ]);

        $bruno = Persona::query()->updateOrCreate(['rut' => '11111111-1'], [
            'nombres' => 'Bruno Ejemplo', 'apellido_paterno' => 'Histórico', 'apellido_materno' => null, 'active' => true,
        ]);
        $bruno->vinculos()->updateOrCreate(['unidad_servicio_id' => $laboratorio->id, 'start_date' => '2024-01-01'], [
            'estamento_id' => $tecnico->id, 'profesion_id' => $tens->id, 'cargo_texto' => 'Función histórica ficticia', 'end_date' => '2025-12-31', 'status' => 'INACTIVO',
        ]);
        $bruno->vinculos()->updateOrCreate(['unidad_servicio_id' => $urgencia->id, 'start_date' => '2026-01-01'], [
            'estamento_id' => $tecnico->id, 'profesion_id' => $tens->id, 'cargo_texto' => 'Función vigente ficticia', 'status' => 'ACTIVO',
        ]);

        $carla = Persona::query()->updateOrCreate(['rut' => '22222222-2'], [
            'nombres' => 'Carla Muestra', 'apellido_paterno' => 'Temporal', 'apellido_materno' => 'Ejemplo', 'active' => true,
        ]);
        $carla->vinculos()->updateOrCreate(['unidad_servicio_id' => $farmacia->id, 'start_date' => '2026-08-01'], [
            'estamento_id' => $profesional->id, 'cargo_texto' => 'Asignación ficticia', 'status' => 'EN_TRAMITACION',
        ]);

        $jefe = User::query()->updateOrCreate(['email' => 'jefatura@example.test'], [
            'name' => 'Jefatura Ficticia', 'rut' => '33333333-3', 'password' => Hash::make('password'), 'active' => true, 'email_verified_at' => now(),
        ]);
        $jefe->syncRoles('Jefe de Servicio');
        foreach ([$urgencia, $laboratorio] as $unidad) {
            UserUnidad::query()->updateOrCreate(['user_id' => $jefe->id, 'unidad_servicio_id' => $unidad->id], ['active' => true]);
        }
    }
}
