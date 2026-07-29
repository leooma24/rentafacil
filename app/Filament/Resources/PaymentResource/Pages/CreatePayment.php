<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use App\Models\Rental;
use App\Support\Abonos;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    /**
     * El alta pasa por el mismo camino que el botón Cobrar.
     *
     * Antes creaba la fila y ya: el dinero quedaba registrado pero la fecha de
     * la renta no se movía, así que el cliente que acababa de pagar seguía
     * saliendo como moroso en Avisos, en la ruta de cobranza y en su estado de
     * cuenta. Eran dos maneras de registrar un pago y sólo una que servía.
     *
     * Abonos::register es esa única lógica: si el monto completa uno o más
     * periodos, extiende la renta; si no alcanza, queda como saldo a favor.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $renta = Rental::findOrFail($data['rental_id']);

        $resultado = Abonos::register(
            $renta,
            (float) $data['amount'],
            $data['payment_method'] ?? 'Efectivo',
            $data['payment_date'] ?? null,
            $data['reference'] ?? null,
        );

        if ($resultado['periodos'] > 0) {
            Notification::make()
                ->title('Cobro registrado')
                ->body('La renta quedó pagada hasta el '
                    . \Carbon\Carbon::parse($renta->fresh()->end_date)->format('d/m/Y') . '.')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Abono registrado')
                ->body('No alcanzó para un periodo, así que la fecha no se movió. '
                    . 'Lleva $' . number_format($resultado['restante'], 2) . ' a favor.')
                ->warning()
                ->send();
        }

        return $resultado['payment'];
    }
}
