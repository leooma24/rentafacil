<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Ligas firmadas para que el cliente vea su recibo o su estado de cuenta sin
 * tener cuenta.
 *
 * Sus clientes no son usuarios del sistema y no lo van a ser: quien renta una
 * lavadora no crea cuenta ni recuerda contraseña. Un link de WhatsApp que abre
 * y ya es lo que sí usa.
 */
class ShareableLinks
{
    /** El saldo cambia, así que un estado de cuenta viejo no debe seguir sirviendo. */
    public const DIAS_ESTADO_DE_CUENTA = 30;

    /** El recibo no caduca: es el comprobante de algo que ya pasó. */
    public static function receiptUrl(Payment $payment): string
    {
        return URL::signedRoute('publico.recibo', ['payment' => $payment->id]);
    }

    public static function receiptPdfUrl(Payment $payment): string
    {
        return URL::signedRoute('publico.recibo.pdf', ['payment' => $payment->id]);
    }

    public static function statementUrl(Customer $customer): string
    {
        return URL::temporarySignedRoute(
            'publico.estado-de-cuenta',
            now()->addDays(self::DIAS_ESTADO_DE_CUENTA),
            ['customer' => $customer->id],
        );
    }

    public static function receiptMessage(Payment $payment): string
    {
        $cliente = $payment->rental?->customer?->name ?? 'qué tal';
        $monto = number_format((float) $payment->amount, 2);
        $liga = self::receiptUrl($payment);

        $cubierta = $payment->rental?->end_date
            ? "\n\nTu renta queda cubierta hasta el " . Carbon::parse($payment->rental->end_date)->format('d/m/Y') . '.'
            : '';

        return "Hola {$cliente}, recibimos tu pago de \${$monto}. Aquí está tu comprobante:\n"
            . $liga
            . $cubierta
            . "\n— Renta Fácil";
    }

    public static function statementMessage(Customer $customer): string
    {
        $statement = app(AccountStatement::class)->forCustomer($customer);
        $liga = self::statementUrl($customer);

        $detalle = $statement->hasDebt()
            ? "\n\nDebes \$" . number_format($statement->total, 2)
                . ($statement->owingSince ? ' desde el ' . $statement->owingSince->format('d/m/Y') : '')
                . '.'
            : "\n\nEstás al corriente, no debes nada.";

        return "Hola {$customer->name}, este es tu estado de cuenta con nosotros:\n"
            . $liga
            . $detalle
            . "\n— Renta Fácil";
    }

    /** La liga de WhatsApp con el mensaje ya escrito, lista para abrir. */
    public static function whatsappUrl(?string $phone, string $message): string
    {
        return 'https://wa.me/' . ProspectOutreach::whatsappNumber($phone)
            . '?text=' . urlencode($message);
    }
}
