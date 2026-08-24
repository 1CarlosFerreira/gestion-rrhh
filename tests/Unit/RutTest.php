<?php

namespace Tests\Unit;

use App\Support\Rut\Rut;
use PHPUnit\Framework\TestCase;

class RutTest extends TestCase
{
    public function test_normalizes_rut_with_and_without_dots(): void
    {
        $this->assertSame('12345678-5', Rut::normalize('12.345.678-5'));
        $this->assertSame('12345678-5', Rut::normalize('12345678-5'));
        $this->assertSame('12345678-5', Rut::normalize(' 12.345.678 - 5 '));
    }

    public function test_accepts_valid_rut(): void
    {
        $this->assertTrue(Rut::validate('12.345.678-5'));
        $this->assertTrue(Rut::validate('6.532.697-3'));
    }

    public function test_rejects_invalid_rut(): void
    {
        $this->assertFalse(Rut::validate('12.345.678-9'));
        $this->assertFalse(Rut::validate('no-es-rut'));
    }
}
