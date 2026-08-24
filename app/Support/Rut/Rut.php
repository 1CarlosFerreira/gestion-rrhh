<?php

namespace App\Support\Rut;

final class Rut
{
    public static function normalize(?string $rut): string
    {
        $clean = strtoupper(preg_replace('/[.\s-]+/', '', trim((string) $rut)) ?? '');

        if (! preg_match('/^(\d+)([0-9K])$/', $clean, $matches)) {
            return $clean;
        }

        $body = ltrim($matches[1], '0') ?: '0';

        return $body.'-'.$matches[2];
    }

    public static function validate(?string $rut): bool
    {
        $normalized = self::normalize($rut);

        if (! preg_match('/^(\d{1,8})-([0-9K])$/', $normalized, $matches)) {
            return false;
        }

        $sum = 0;
        $multiplier = 2;
        for ($index = strlen($matches[1]) - 1; $index >= 0; $index--) {
            $sum += ((int) $matches[1][$index]) * $multiplier;
            $multiplier = $multiplier === 7 ? 2 : $multiplier + 1;
        }

        $result = 11 - ($sum % 11);
        $checkDigit = match ($result) {
            11 => '0',
            10 => 'K',
            default => (string) $result,
        };

        return hash_equals($checkDigit, $matches[2]);
    }

    public static function format(?string $rut): string
    {
        $normalized = self::normalize($rut);
        if (! preg_match('/^(\d+)-([0-9K])$/', $normalized, $matches)) {
            return $normalized;
        }

        return number_format((int) $matches[1], 0, ',', '.').'-'.$matches[2];
    }
}
