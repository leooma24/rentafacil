<?php

namespace App\Support;

use App\Models\Rental;
use Illuminate\Support\Facades\DB;

/**
 * Recoger el equipo y cerrar la renta sin perder lo que el cliente debía.
 *
 * El adeudo no se guarda en ninguna parte: se deduce de qué tan atrás quedó
 * end_date. Eso funciona mientras la renta esté abierta, pero recoger la ponía en
 * "completada" y le movía end_date a hoy, así que el saldo se borraba por los dos
 * lados a la vez. Y el caso más común de recolección es justo el del que dejó de
 * pagar: el sistema olvidaba la deuda en el único momento en que de verdad
 * importa acordarse de ella, que es cuando ese cliente vuelve a pedir equipo.
 *
 * Vive aquí y no dentro de la acción porque hay DOS botones para recoger —el de
 * Equipos y el de la ficha del cliente— y sólo uno pedía fotos y depósito. El
 * otro, el que aparece cuando la renta está vencida, era el que más se usaba con
 * los morosos.
 */
class Recoleccion
{
    /**
     * Lo que debe ahora mismo, antes de tocarle nada.
     *
     * Se calcula aquí y no después porque cerrar la renta mueve end_date, y a
     * partir de ese momento el número ya no se puede reconstruir.
     */
    public function adeudo(Rental $renta): float
    {
        return (float) app(AccountStatement::class)->forRental($renta)->amount;
    }

    /**
     * @param bool $quedaronEnPaz Si el dueño le perdona lo que debía. Es su
     *        decisión y por eso se guarda: a veces te llevas la lavadora y ahí
     *        quedó, y adivinarlo en cualquiera de los dos sentidos es peor.
     * @param array<string, mixed> $extra Fotos de cómo lo devolvieron y datos de
     *        la devolución del depósito, cuando el formulario los pidió.
     */
    public function ejecutar(Rental $renta, bool $quedaronEnPaz, array $extra = []): float
    {
        $debia = $this->adeudo($renta);

        DB::transaction(function () use ($renta, $quedaronEnPaz, $extra, $debia) {
            // A revisión y no directo a disponible: la lavadora regresa sucia, con
            // la manguera mordida o sin la tapa, y eso se descubre en la puerta
            // del cliente siguiente si nadie la abre antes.
            $renta->washingMachine?->update(['status' => 'en_revision']);

            $cambios = [
                'status' => 'completada',
                'end_date' => now()->toDateString(),
                'debt_at_close' => $debia,
                'debt_settled' => $quedaronEnPaz,
            ];

            if (array_key_exists('pickup_photos', $extra)) {
                $cambios['pickup_photos'] = $extra['pickup_photos'] ?? [];
            }

            if (isset($extra['deposit_returned']) && $renta->hasPendingDeposit()) {
                $cambios['deposit_returned'] = (float) $extra['deposit_returned'];
                $cambios['deposit_returned_at'] = now();

                if (filled($extra['deposit_notes'] ?? null)) {
                    $cambios['notes'] = trim(($renta->notes ? $renta->notes . ' · ' : '')
                        . 'Depósito: ' . $extra['deposit_notes']);
                }
            }

            // Por el modelo y no por el query builder: una actualización masiva se
            // salta los casts y guardaría el arreglo de fotos como texto basura.
            //
            // Se cierran todas las abiertas de ese equipo y no sólo ésta: si
            // quedaron dos por algún descuadre viejo, dejar una viva marcaría el
            // aparato como rentado sin cliente.
            $abiertas = $renta->washingMachine
                ? $renta->washingMachine->rentals()->whereIn('status', ['activa', 'vencida'])->get()
                : collect([$renta]);

            foreach ($abiertas as $abierta) {
                // El adeudo congelado es de cada renta, no el de la que se recogió.
                $abierta->update(array_merge($cambios, [
                    'debt_at_close' => $abierta->is($renta) ? $debia : $this->adeudo($abierta),
                ]));
            }
        });

        return $debia;
    }

    /** El texto que se le pone enfrente al dueño antes de decidir. */
    public function resumen(Rental $renta): string
    {
        $debia = $this->adeudo($renta);
        $quien = $renta->customer?->name ?? 'El cliente';

        if ($debia <= 0) {
            return $quien . ' está al corriente: no queda nada que cobrarle.';
        }

        return $quien . ' te queda debiendo <strong>$' . number_format($debia, 2) . '</strong>.';
    }
}
