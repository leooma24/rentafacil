<?php

namespace App\Support;

use App\Models\Payment;
use App\Models\Rental;
use Carbon\Carbon;

/**
 * Pagos parciales.
 *
 * El negocio cobra en efectivo en la puerta y ahí la gente paga lo que trae. Un
 * abono se registra contra la renta pero no mueve su fecha de vencimiento: es
 * dinero que todavía no compra tiempo. Cuando lo acumulado alcanza el precio
 * del periodo, la renta se extiende sola y esos abonos quedan consumidos.
 */
class Abonos
{
    /** Lo abonado a esta renta que todavía no compra tiempo. */
    public static function creditFor(Rental $rental): float
    {
        return (float) $rental->payments()
            ->where('status', 'completado')
            ->where('applied', false)
            ->sum('amount');
    }

    /**
     * Registra un abono y, si con él se completan uno o más periodos, extiende
     * la renta y marca como aplicados los abonos que se consumieron.
     *
     * @return array{payment: Payment, periodos: int, restante: float}
     */
    public static function register(
        Rental $rental,
        float $amount,
        string $method = 'Efectivo',
        ?string $date = null,
        ?string $reference = null,
    ): array {
        $payment = $rental->payments()->create([
            'company_id' => $rental->company_id,
            'amount' => $amount,
            'payment_date' => $date ?? now()->toDateString(),
            'payment_method' => $method,
            'reference' => $reference,
            'status' => 'completado',
            'applied' => false,
        ]);

        $periodos = self::applyCompletePeriods($rental);

        return [
            'payment' => $payment,
            'periodos' => $periodos,
            'restante' => self::creditFor($rental->fresh()),
        ];
    }

    /**
     * Consume los abonos que ya alcanzan para uno o más periodos completos.
     *
     * @return int cuántos periodos se extendieron
     */
    public static function applyCompletePeriods(Rental $rental): int
    {
        $terms = RentalTerms::for($rental->company);

        if (! $terms->isConfigured()) {
            return 0;
        }

        $credito = self::creditFor($rental);
        $periodos = (int) floor($credito / $terms->price);

        if ($periodos < 1) {
            return 0;
        }

        $aConsumir = $periodos * $terms->price;

        // Se consumen del más viejo al más nuevo, para que el sobrante quede
        // siempre en el abono más reciente.
        $restantePorConsumir = $aConsumir;

        $abonos = $rental->payments()
            ->where('status', 'completado')
            ->where('applied', false)
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        foreach ($abonos as $abono) {
            if ($restantePorConsumir <= 0) {
                break;
            }

            $monto = (float) $abono->amount;

            if ($monto <= $restantePorConsumir) {
                $abono->update(['applied' => true]);
                $restantePorConsumir -= $monto;

                continue;
            }

            // Este abono es más grande que lo que falta por consumir: se parte
            // en dos, la porción usada y el sobrante que sigue sin aplicar.
            $abono->update(['amount' => $restantePorConsumir, 'applied' => true]);

            $rental->payments()->create([
                'company_id' => $abono->company_id,
                'amount' => $monto - $restantePorConsumir,
                'payment_date' => $abono->payment_date,
                'payment_method' => $abono->payment_method,
                'reference' => $abono->reference,
                'status' => 'completado',
                'applied' => false,
            ]);

            $restantePorConsumir = 0;
        }

        $rental->end_date = Carbon::parse($rental->end_date)
            ->addDays($periodos * $terms->days)
            ->toDateString();

        if ($rental->status === 'vencida' && Carbon::parse($rental->end_date)->gte(Carbon::today())) {
            $rental->status = 'activa';
        }

        $rental->save();

        return $periodos;
    }
}
