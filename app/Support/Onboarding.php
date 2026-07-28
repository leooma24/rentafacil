<?php

namespace App\Support;

use App\Models\Company;

/**
 * Los cuatro pasos para que una empresa arranque, y cuáles lleva hechos.
 *
 * Existe porque 11 de 17 cuentas reales nunca cargaron una lavadora: no es que no
 * quisieran, es que nada les dijo que ese era el primer paso.
 */
class Onboarding
{
    /** @param array<int, array{clave: string, titulo: string, ayuda: string, hecho: bool}> $steps */
    private function __construct(public readonly array $steps)
    {
    }

    public static function for(Company $company): self
    {
        $settings = $company->settings;
        $tienePrecio = $settings && $settings->price > 0 && $settings->days_per_payment > 0;

        return new self([
            [
                'clave' => 'precio',
                'titulo' => 'Configura tu precio de renta',
                'ayuda' => 'Cuánto cobras y cada cuántos días. Sin esto no se pueden registrar cobros.',
                'hecho' => $tienePrecio,
            ],
            [
                'clave' => 'lavadoras',
                'titulo' => 'Carga tus lavadoras',
                'ayuda' => 'Una por una, o importa tu Excel de un jalón.',
                'hecho' => $company->washingMachines()->exists(),
            ],
            [
                'clave' => 'clientes',
                'titulo' => 'Carga tus clientes',
                'ayuda' => 'También puedes importarlos desde Excel.',
                'hecho' => $company->customers()->exists(),
            ],
            [
                'clave' => 'renta',
                'titulo' => 'Registra tu primera renta',
                'ayuda' => 'Asigna una lavadora a un cliente y empieza a llevar el control.',
                'hecho' => $company->rentals()->exists(),
            ],
        ]);
    }

    public function isComplete(): bool
    {
        return collect($this->steps)->every(fn (array $paso) => $paso['hecho']);
    }

    public function pendingCount(): int
    {
        return collect($this->steps)->reject(fn (array $paso) => $paso['hecho'])->count();
    }

    public function doneCount(): int
    {
        return count($this->steps) - $this->pendingCount();
    }

    public function needsPrice(): bool
    {
        return ! collect($this->steps)->firstWhere('clave', 'precio')['hecho'];
    }
}
