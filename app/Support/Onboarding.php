<?php

namespace App\Support;

use App\Models\Company;

/**
 * Los cinco pasos para que una empresa arranque, y cuáles lleva hechos.
 *
 * Existe porque 11 de 17 cuentas reales nunca cargaron una lavadora: no es que no
 * quisieran, es que nada les dijo que ése era el primer paso.
 *
 * El quinto paso —el primer cobro— se agregó después, y es el que importa. La
 * lista terminaba en "registra tu primera renta" y ahí se daba por completa, pero
 * la pared real está justo en el escalón siguiente: de las 6 cuentas que cargaron
 * equipo, 5 nunca registraron un cobro. Y como el recuadro se esconde solo al
 * completarse, la cuenta que más necesitaba el empujón era precisamente la que se
 * quedaba sin nada.
 *
 * Registrar el primer cobro es lo que convierte esto en la herramienta de trabajo
 * de alguien, en vez de un catálogo de sus lavadoras.
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
                'titulo' => 'Carga tus equipos',
                'ayuda' => 'Lavadoras y secadoras. Una por una, o importa tu Excel de un jalón.',
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
                'ayuda' => 'Asigna un equipo a un cliente y empieza a llevar el control.',
                'hecho' => $company->rentals()->exists(),
            ],
            [
                'clave' => 'cobro',
                'titulo' => 'Registra tu primer cobro',
                'ayuda' => 'Con el botón Cobrar en Rentas. La fecha del cliente se recorre sola y de ahí salen su estado de cuenta, tu corte de caja y tu ganancia del mes.',
                'hecho' => $company->payments()->exists(),
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

    /**
     * El siguiente paso pendiente, para poder empujar a uno solo.
     *
     * @return array{clave: string, titulo: string, ayuda: string, hecho: bool}|null
     */
    public function siguiente(): ?array
    {
        return collect($this->steps)->reject(fn (array $paso) => $paso['hecho'])->first();
    }

    /** Cargó equipo y clientes pero nunca ha cobrado: ahí es donde se caen. */
    public function faltaElPrimerCobro(): bool
    {
        return ! collect($this->steps)->firstWhere('clave', 'cobro')['hecho']
            && collect($this->steps)->firstWhere('clave', 'renta')['hecho'];
    }

    public function needsPrice(): bool
    {
        return ! collect($this->steps)->firstWhere('clave', 'precio')['hecho'];
    }
}
