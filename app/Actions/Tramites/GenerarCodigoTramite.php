<?php

namespace App\Actions\Tramites;

use App\Models\TramiteSecuencia;

class GenerarCodigoTramite
{
    public function execute(?int $year = null): string
    {
        $year ??= (int) now()->format('Y');

        TramiteSecuencia::query()->insertOrIgnore([
            'year' => $year,
            'next_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sequence = TramiteSecuencia::query()->whereKey($year)->lockForUpdate()->firstOrFail();
        $number = $sequence->next_number;
        $sequence->increment('next_number');

        return sprintf('TR-%d-%06d', $year, $number);
    }
}
