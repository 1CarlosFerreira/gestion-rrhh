<?php

namespace App\Support\Documentos;

class DuracionMinutos
{
    public static function asText(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    public static function asExcelTime(int $minutes): float
    {
        return $minutes / 1440;
    }
}
