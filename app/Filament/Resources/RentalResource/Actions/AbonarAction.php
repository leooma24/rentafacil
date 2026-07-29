<?php

namespace App\Filament\Resources\RentalResource\Actions;

use App\Models\Company;
use App\Models\Rental;
use App\Support\Abonos;
use App\Support\RentalTerms;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;

/**
 * Registrar un pago parcial.
 *
 * Se usa cuando el cliente trae menos de lo que debe, que en cobranza en
 * efectivo es todos los días.
 */
class AbonarAction
{
    /**
     * @param  \Closure(Rental|\App\Models\WashingMachine): ?Rental  $resolver
     *         Cómo sacar la renta del registro de la tabla, que en Rentas es él
     *         mismo y en Lavadoras es su renta viva.
     */
    public static function make(Company $tenant, \Closure $resolver): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('abonar')
            ->label('Abonar')
            ->icon('heroicon-o-banknotes')
            ->color('warning')
            ->visible(fn ($record) => $resolver($record) !== null)
            ->modalHeading('Registrar un abono')
            // Las condiciones salen de la renta, no de la empresa: con precio
            // pactado por cliente, el general dice una cifra que no es la suya.
            ->modalDescription(function ($record) use ($resolver) {
                $rental = $resolver($record);

                return self::descripcion(
                    $rental,
                    $rental ? RentalTerms::forRental($rental) : RentalTerms::for($record->company)
                );
            })
            ->modalSubmitActionLabel('Registrar abono')
            ->form([
                Forms\Components\TextInput::make('amount')
                    ->label('¿Cuánto te dio?')
                    ->numeric()
                    ->minValue(1)
                    ->required()
                    ->autofocus(),
                Forms\Components\Select::make('payment_method')
                    ->label('Método')
                    ->options([
                        'Efectivo' => 'Efectivo',
                        'Transferencia Bancaria' => 'Transferencia Bancaria',
                        'Tarjeta de Débito' => 'Tarjeta de Débito',
                        'Tarjeta de Crédito' => 'Tarjeta de Crédito',
                    ])
                    ->default('Efectivo')
                    ->required(),
            ])
            ->action(function (array $data, $record) use ($resolver) {
                $rental = $resolver($record);

                if (! $rental) {
                    return;
                }

                $resultado = Abonos::register($rental, (float) $data['amount'], $data['payment_method']);

                Notification::make()
                    ->title(self::resultado($resultado))
                    ->success()
                    ->send();
            });
    }

    private static function descripcion(?Rental $rental, RentalTerms $terms): string
    {
        if (! $rental) {
            return '';
        }

        if (! $terms->isConfigured()) {
            return 'Falta configurar tu precio de renta en Preferencias.';
        }

        $abonado = Abonos::creditFor($rental);

        if ($abonado > 0) {
            return 'Lleva abonados $' . number_format($abonado, 2)
                . ' de $' . number_format($terms->price, 2)
                . '. Le faltan $' . number_format(max(0, $terms->price - $abonado), 2) . '.';
        }

        return 'El periodo cuesta $' . number_format($terms->price, 2)
            . '. Al completarlo, la renta se extiende sola.';
    }

    /** @param array{payment: \App\Models\Payment, periodos: int, restante: float} $resultado */
    private static function resultado(array $resultado): string
    {
        $monto = number_format((float) $resultado['payment']->amount, 2);

        if ($resultado['periodos'] > 0) {
            $texto = "Abono de \${$monto} registrado. ";
            $texto .= $resultado['periodos'] === 1
                ? 'Completó el periodo y la renta se extendió.'
                : "Completó {$resultado['periodos']} periodos y la renta se extendió.";

            if ($resultado['restante'] > 0) {
                $texto .= ' Le quedan $' . number_format($resultado['restante'], 2) . ' a favor.';
            }

            return $texto;
        }

        return "Abono de \${$monto} registrado. Lleva \$"
            . number_format($resultado['restante'], 2) . ' abonados.';
    }
}
