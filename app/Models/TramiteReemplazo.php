<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TramiteReemplazo extends Model
{
    protected $guarded = ['id'];

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'funcionario_id');
    }

    public function reemplazante(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'reemplazante_id');
    }

    public function tipoReemplazo(): BelongsTo
    {
        return $this->belongsTo(TipoReemplazo::class);
    }

    public function diasFuncionario(): int
    {
        return $this->diasInclusivos($this->fecha_funcionario_desde, $this->fecha_funcionario_hasta);
    }

    public function diasReemplazante(): int
    {
        if ($this->fecha_reemplazante_desde === null || $this->fecha_reemplazante_hasta === null) {
            return 0;
        }

        return $this->diasInclusivos($this->fecha_reemplazante_desde, $this->fecha_reemplazante_hasta);
    }

    public function diasSinCobertura(): int
    {
        return $this->diasFuncionario() - $this->diasReemplazante();
    }

    public function coberturaTotal(): bool
    {
        return $this->diasFuncionario() > 0 && $this->diasSinCobertura() === 0;
    }

    public function coberturaParcial(): bool
    {
        return $this->diasReemplazante() > 0 && $this->diasSinCobertura() > 0;
    }

    private function diasInclusivos(\DateTimeInterface|string $desde, \DateTimeInterface|string $hasta): int
    {
        return CarbonImmutable::parse($desde)->diffInDays(CarbonImmutable::parse($hasta)) + 1;
    }

    protected function casts(): array
    {
        return ['fecha_funcionario_desde' => 'date', 'fecha_funcionario_hasta' => 'date', 'fecha_reemplazante_desde' => 'date', 'fecha_reemplazante_hasta' => 'date'];
    }
}
