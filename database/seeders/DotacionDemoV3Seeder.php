<?php

namespace Database\Seeders;

use App\Enums\AlcanceAccesoOperativo;
use App\Enums\OrigenVinculoDotacion;
use App\Enums\TipoResponsabilidad;
use App\Models\CalidadContractual;
use App\Models\Estamento;
use App\Models\Persona;
use App\Models\PersonaUnidadVinculo;
use App\Models\Profesion;
use App\Models\UnidadOrganizacional;
use App\Models\UnidadResponsable;
use App\Models\User;
use App\Models\UserUnidadAcceso;
use App\Services\Accesos\AccesoOperativoService;
use App\Services\Dotacion\DotacionService;
use App\Services\Responsabilidades\ResponsabilidadInstitucionalService;
use App\Support\Rut\Rut;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;
use Spatie\Permission\Models\Role;

class DotacionDemoV3Seeder extends Seeder
{
    private const MARKER = 'Datos ficticios V3: DotacionDemoV3Seeder';

    private const ACTIVE_FROM = '2025-01-01';

    private const HISTORICAL_FROM = '2024-01-01';

    private const HISTORICAL_TO = '2024-12-31';

    public function run(): void
    {
        $this->guardEnvironment();

        DB::transaction(function (): void {
            $units = $this->units();
            $catalogs = $this->catalogs();
            $this->requireRoles();

            $people = [];
            foreach ($this->fixtures() as $fixture) {
                $people[$fixture['serial']] = $this->person($fixture);
            }

            $operator = $this->user($people[6], 'Operador Demo V3', 'demo.v3.solicitante@example.test', 'Solicitante');
            $reviewer = $this->user($people[7], 'Revisor Demo V3', 'demo.v3.gestion@example.test', 'Gestión de Personas');

            foreach ($this->fixtures() as $fixture) {
                $this->link($people[$fixture['serial']], $units[$fixture['unit']], $catalogs, $fixture, $operator);
            }

            foreach ([$operator, $reviewer] as $user) {
                $this->access($user, $units['SDGADM'], AlcanceAccesoOperativo::UNIDAD_Y_DESCENDIENTES, $operator);
                $this->access($user, $units['SDGDP-DGP-GP'], AlcanceAccesoOperativo::SOLO_UNIDAD, $operator);
            }

            foreach ([1 => 'SDGADM-INF', 5 => 'SDGDP-DGP-GP', 8 => 'SDGADM'] as $serial => $code) {
                $this->responsibility($people[$serial], $units[$code], $operator);
            }
        });
    }

    private function guardEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])
            || ! in_array(config('app.env'), ['local', 'testing'], true)
            || getenv('RRHH_ALLOW_DEMO_SEEDING') !== '1') {
            throw new LogicException('DotacionDemoV3Seeder solo admite local/testing con RRHH_ALLOW_DEMO_SEEDING=1.');
        }

        $password = getenv('RRHH_DEMO_PASSWORD');
        if (! is_string($password) || strlen($password) < 12) {
            throw new LogicException('Configure RRHH_DEMO_PASSWORD con al menos 12 caracteres para las cuentas demo nuevas.');
        }
    }

    private function units(): array
    {
        $codes = ['SDGADM-INF', 'SDGDP-DGP-GP', 'SDGADM'];
        $units = UnidadOrganizacional::query()->whereIn('codigo', $codes)->where('activo', true)->get()->keyBy('codigo');
        foreach ($codes as $code) {
            if (! $units->has($code)) {
                throw new LogicException("Falta la unidad activa {$code}; cargue primero la estructura oficial.");
            }
        }

        return $units->all();
    }

    private function catalogs(): array
    {
        $codes = [
            'estamentos' => ['PROFESIONAL', 'TECNICO', 'ADMINISTRATIVO'],
            'profesiones' => ['INGENIERO_INFORMATICO', 'OTROS_TECNICOS', 'ADMINISTRATIVO', 'ADMINISTRADOR_PUBLICO', 'INGENIERO_ADMINISTRACION', 'INGENIERO_COMERCIAL'],
            'calidades' => ['CONTRATA', 'TITULAR'],
        ];
        $models = ['estamentos' => Estamento::class, 'profesiones' => Profesion::class, 'calidades' => CalidadContractual::class];
        $result = [];
        foreach ($codes as $group => $required) {
            $items = $models[$group]::query()->whereIn('codigo', $required)->where('activo', true)->get()->keyBy('codigo');
            foreach ($required as $code) {
                if (! $items->has($code)) {
                    throw new LogicException("Falta el catálogo activo {$group}.{$code}; cargue primero los catálogos oficiales.");
                }
            }
            $result[$group] = $items->all();
        }

        return $result;
    }

    private function requireRoles(): void
    {
        foreach (['Solicitante', 'Gestión de Personas'] as $name) {
            if (! Role::query()->where('name', $name)->where('guard_name', 'web')->exists()) {
                throw new LogicException("Falta el rol {$name}; cargue primero RolesPermisosSeeder.");
            }
        }
    }

    private function person(array $fixture): Persona
    {
        $rut = $this->demoRut($fixture['serial']);
        $expected = [
            'nombres' => $fixture['name'],
            'apellido_paterno' => $fixture['surname'],
            'apellido_materno' => 'DemoV3',
            'active' => true,
        ];
        $existing = Persona::query()->where('rut', $rut)->first();
        if ($existing) {
            foreach ($expected as $field => $value) {
                if ($value !== $existing->$field) {
                    throw new LogicException("Colisión de identidad ficticia {$fixture['serial']}; no se modificó la persona existente.");
                }
            }
            if (! PersonaUnidadVinculo::query()->where('persona_id', $existing->id)->where('observacion', self::MARKER)->exists()) {
                throw new LogicException("La persona ficticia {$fixture['serial']} ya existe sin procedencia demo verificable.");
            }

            return $existing;
        }

        return Persona::query()->create(['rut' => $rut, ...$expected]);
    }

    private function user(Persona $person, string $name, string $email, string $role): User
    {
        $existing = User::query()->where('email', $email)->orWhere('rut', $person->rut)->orWhere('persona_id', $person->id)->first();
        if ($existing) {
            if ($existing->email !== $email || $existing->rut !== $person->rut || $existing->persona_id !== $person->id
                || $existing->name !== $name || ! $existing->active || $existing->getRoleNames()->all() !== [$role]) {
                throw new LogicException("Colisión de cuenta demo {$email}; no se modificó la cuenta existente.");
            }
            if (! UserUnidadAcceso::query()->where('user_id', $existing->id)->where('observacion', self::MARKER)->exists()) {
                throw new LogicException("La cuenta {$email} ya existe sin procedencia demo verificable.");
            }

            return $existing;
        }

        $user = User::query()->create([
            'name' => $name,
            'rut' => $person->rut,
            'persona_id' => $person->id,
            'email' => $email,
            'password' => getenv('RRHH_DEMO_PASSWORD'),
            'active' => true,
        ]);
        $user->assignRole($role);
        $permission = $role === 'Solicitante' ? 'reemplazos.crear' : 'reemplazos.revisar';
        if (! $user->can($permission)) {
            throw new LogicException("El rol {$role} no tiene {$permission}.");
        }

        return $user;
    }

    private function link(Persona $person, UnidadOrganizacional $unit, array $catalogs, array $fixture, User $actor): void
    {
        $historical = $fixture['historical'] ?? false;
        $start = $historical ? self::HISTORICAL_FROM : self::ACTIVE_FROM;
        $end = $historical ? self::HISTORICAL_TO : null;
        $estamento = $catalogs['estamentos'][$fixture['estamento']];
        $profesion = $catalogs['profesiones'][$fixture['profesion']];
        $calidad = $catalogs['calidades'][$fixture['calidad']];
        if ($profesion->estamento_id !== null && $profesion->estamento_id !== $estamento->id) {
            throw new LogicException('La profesión del fixture no corresponde al estamento.');
        }
        $normalizedCargo = mb_strtolower($fixture['cargo']);
        $existing = PersonaUnidadVinculo::query()
            ->where('persona_id', $person->id)
            ->where('unidad_organizacional_id', $unit->id)
            ->where('calidad_contractual_id', $calidad->id)
            ->where('cargo_funcion_normalizado', $normalizedCargo)
            ->whereDate('vigente_desde', $start)
            ->when($end === null, fn ($query) => $query->whereNull('vigente_hasta'), fn ($query) => $query->whereDate('vigente_hasta', $end))
            ->first();
        if ($existing) {
            if ($existing->estamento_id !== $estamento->id || $existing->profesion_id !== $profesion->id
                || $existing->cargo_funcion !== $fixture['cargo'] || $existing->grado_eus !== null
                || $existing->origen !== OrigenVinculoDotacion::IMPORTACION || $existing->observacion !== self::MARKER
                || $existing->created_by !== $actor->id) {
                throw new LogicException('Existe un vínculo equivalente distinto; no se modificó.');
            }

            return;
        }

        app(DotacionService::class)->crear([
            'persona_id' => $person->id,
            'unidad_organizacional_id' => $unit->id,
            'estamento_id' => $estamento->id,
            'profesion_id' => $profesion->id,
            'calidad_contractual_id' => $calidad->id,
            'cargo_funcion' => $fixture['cargo'],
            'grado_eus' => null,
            'vigente_desde' => $start,
            'vigente_hasta' => $end,
            'origen' => OrigenVinculoDotacion::IMPORTACION->value,
            'observacion' => self::MARKER,
        ], $actor);
    }

    private function access(User $user, UnidadOrganizacional $unit, AlcanceAccesoOperativo $scope, User $actor): void
    {
        $existing = UserUnidadAcceso::query()->where('user_id', $user->id)
            ->where('unidad_organizacional_id', $unit->id)
            ->where('alcance', $scope->value)
            ->whereDate('vigente_desde', self::ACTIVE_FROM)
            ->whereNull('vigente_hasta')->first();
        if ($existing) {
            if ($existing->observacion !== self::MARKER || $existing->created_by !== $actor->id) {
                throw new LogicException('Existe un acceso demo distinto; no se modificó.');
            }

            return;
        }
        app(AccesoOperativoService::class)->crear([
            'user_id' => $user->id,
            'unidad_organizacional_id' => $unit->id,
            'alcance' => $scope->value,
            'vigente_desde' => self::ACTIVE_FROM,
            'vigente_hasta' => null,
            'observacion' => self::MARKER,
        ], $actor);
    }

    private function responsibility(Persona $person, UnidadOrganizacional $unit, User $actor): void
    {
        $existing = UnidadResponsable::query()->where('persona_id', $person->id)
            ->where('unidad_organizacional_id', $unit->id)
            ->where('tipo', TipoResponsabilidad::TITULAR->value)
            ->whereDate('vigente_desde', self::ACTIVE_FROM)
            ->whereNull('vigente_hasta')->first();
        if ($existing) {
            if (! $existing->puede_aprobar || $existing->observacion !== self::MARKER || $existing->created_by !== $actor->id) {
                throw new LogicException('Existe una responsabilidad demo distinta; no se modificó.');
            }

            return;
        }
        app(ResponsabilidadInstitucionalService::class)->crear([
            'persona_id' => $person->id,
            'unidad_organizacional_id' => $unit->id,
            'tipo' => TipoResponsabilidad::TITULAR->value,
            'vigente_desde' => self::ACTIVE_FROM,
            'vigente_hasta' => null,
            'puede_aprobar' => true,
            'observacion' => self::MARKER,
        ], $actor);
    }

    private function demoRut(int $serial): string
    {
        $body = (string) (90000000 + $serial);
        $sum = 0;
        $factor = 2;
        for ($index = strlen($body) - 1; $index >= 0; $index--) {
            $sum += ((int) $body[$index]) * $factor;
            $factor = $factor === 7 ? 2 : $factor + 1;
        }
        $check = 11 - ($sum % 11);
        $digit = match ($check) {
            11 => '0',
            10 => 'K',
            default => (string) $check,
        };
        $rut = $body.'-'.$digit;
        if (! Rut::validate($rut)) {
            throw new LogicException('Falló la generación de un RUT ficticio válido.');
        }

        return $rut;
    }

    private function fixtures(): array
    {
        return [
            ['serial' => 1, 'name' => 'Ardelia', 'surname' => 'Nivar', 'unit' => 'SDGADM-INF', 'cargo' => 'Jefatura Informática Demo V3', 'estamento' => 'PROFESIONAL', 'profesion' => 'INGENIERO_INFORMATICO', 'calidad' => 'TITULAR'],
            ['serial' => 2, 'name' => 'Belorio', 'surname' => 'Talen', 'unit' => 'SDGADM-INF', 'cargo' => 'Ingeniería de Redes Demo V3', 'estamento' => 'PROFESIONAL', 'profesion' => 'INGENIERO_INFORMATICO', 'calidad' => 'CONTRATA'],
            ['serial' => 3, 'name' => 'Cerina', 'surname' => 'Vorel', 'unit' => 'SDGADM-INF', 'cargo' => 'Soporte TIC Demo V3', 'estamento' => 'TECNICO', 'profesion' => 'OTROS_TECNICOS', 'calidad' => 'CONTRATA'],
            ['serial' => 4, 'name' => 'Dariol', 'surname' => 'Nemer', 'unit' => 'SDGADM-INF', 'cargo' => 'Apoyo Informático Demo V3', 'estamento' => 'ADMINISTRATIVO', 'profesion' => 'ADMINISTRATIVO', 'calidad' => 'CONTRATA'],
            ['serial' => 5, 'name' => 'Eloria', 'surname' => 'Mavel', 'unit' => 'SDGDP-DGP-GP', 'cargo' => 'Jefatura Gestión de las Personas Demo V3', 'estamento' => 'PROFESIONAL', 'profesion' => 'ADMINISTRADOR_PUBLICO', 'calidad' => 'TITULAR'],
            ['serial' => 6, 'name' => 'Fareno', 'surname' => 'Velis', 'unit' => 'SDGDP-DGP-GP', 'cargo' => 'Análisis de Personas Demo V3', 'estamento' => 'PROFESIONAL', 'profesion' => 'INGENIERO_ADMINISTRACION', 'calidad' => 'CONTRATA'],
            ['serial' => 7, 'name' => 'Girela', 'surname' => 'Nolven', 'unit' => 'SDGDP-DGP-GP', 'cargo' => 'Administración de Personas Demo V3', 'estamento' => 'ADMINISTRATIVO', 'profesion' => 'ADMINISTRATIVO', 'calidad' => 'CONTRATA'],
            ['serial' => 8, 'name' => 'Halaro', 'surname' => 'Fenrel', 'unit' => 'SDGADM', 'cargo' => 'Subdirección Administrativa Demo V3', 'estamento' => 'PROFESIONAL', 'profesion' => 'ADMINISTRADOR_PUBLICO', 'calidad' => 'TITULAR'],
            ['serial' => 9, 'name' => 'Ivelia', 'surname' => 'Rolden', 'unit' => 'SDGADM', 'cargo' => 'Secretaría Administrativa Demo V3', 'estamento' => 'ADMINISTRATIVO', 'profesion' => 'ADMINISTRATIVO', 'calidad' => 'CONTRATA'],
            ['serial' => 10, 'name' => 'Junerio', 'surname' => 'Falven', 'unit' => 'SDGADM', 'cargo' => 'Análisis Administrativo Demo V3', 'estamento' => 'PROFESIONAL', 'profesion' => 'INGENIERO_COMERCIAL', 'calidad' => 'CONTRATA'],
            ['serial' => 11, 'name' => 'Karelia', 'surname' => 'Tenvar', 'unit' => 'SDGADM-INF', 'cargo' => 'Apoyo de Redes Demo V3', 'estamento' => 'PROFESIONAL', 'profesion' => 'INGENIERO_INFORMATICO', 'calidad' => 'CONTRATA', 'historical' => true],
            ['serial' => 12, 'name' => 'Loriane', 'surname' => 'Savel', 'unit' => 'SDGDP-DGP-GP', 'cargo' => 'Apoyo de Personas Demo V3', 'estamento' => 'ADMINISTRATIVO', 'profesion' => 'ADMINISTRATIVO', 'calidad' => 'CONTRATA', 'historical' => true],
        ];
    }
}
