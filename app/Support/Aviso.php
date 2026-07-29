<?php

namespace App\Support;

use App\Models\Rental;

/**
 * Un aviso por mandar: a quién, por qué equipo y con qué mensaje.
 */
class Aviso
{
    public function __construct(
        public readonly Rental $rental,
        public readonly bool $vencida,
        /** Días de atraso si está vencida; días que faltan si no. */
        public readonly int $dias,
        public readonly float $adeudo,
    ) {
    }

    public function cliente(): string
    {
        return $this->rental->customer?->name ?? 'Cliente';
    }

    public function equipo(): string
    {
        $maquina = $this->rental->washingMachine;

        return $maquina
            ? "{$maquina->machine_code} · {$maquina->kindLabel()}"
            : '—';
    }

    public function cuando(): string
    {
        if ($this->vencida) {
            return $this->dias === 1 ? 'Venció ayer' : "Venció hace {$this->dias} días";
        }

        return match ($this->dias) {
            0 => 'Vence hoy',
            1 => 'Vence mañana',
            default => "Vence en {$this->dias} días",
        };
    }

    /**
     * El mensaje que se le manda.
     *
     * Sin regaños y sin signos de admiración: el que debe ya sabe que debe, y un
     * mensaje agresivo hace que deje de contestar.
     */
    public function mensaje(): string
    {
        $nombre = $this->rental->customer?->name ?? '';
        $equipo = mb_strtolower($this->rental->washingMachine?->kindLabel() ?? 'lavadora');
        $negocio = $this->rental->company?->name ?? '';

        if (! $this->vencida) {
            $cuando = match ($this->dias) {
                0 => 'hoy',
                1 => 'mañana',
                default => "en {$this->dias} días",
            };

            return "Hola {$nombre}, le recordamos que su renta de la {$equipo} vence {$cuando}. "
                . "Cualquier cosa nos avisa. {$negocio}";
        }

        $atraso = $this->dias === 1 ? 'ayer' : "hace {$this->dias} días";
        $monto = $this->adeudo > 0 ? ' Su saldo es de $' . number_format($this->adeudo, 2) . '.' : '';

        return "Hola {$nombre}, su renta de la {$equipo} venció {$atraso}.{$monto} "
            . "Pásenos a decir cuándo le queda bien y lo acomodamos. {$negocio}";
    }

    public function whatsappUrl(): ?string
    {
        $telefono = $this->rental->customer?->phone;

        if (! filled($telefono)) {
            return null;
        }

        return ShareableLinks::whatsappUrl($telefono, $this->mensaje());
    }
}
