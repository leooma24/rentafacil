<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $apiUrl;
    protected string $token;
    protected string $phoneNumberId;

    public function __construct()
    {
        $this->apiUrl = 'https://graph.facebook.com/v18.0';
        $this->token = config('services.whatsapp.token', '');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id', '');
    }

    public function sendMessage(string $to, string $message): bool
    {
        if (empty($this->token) || empty($this->phoneNumberId)) {
            Log::warning('WhatsApp not configured, message not sent', ['to' => $to]);
            return false;
        }

        $phone = $this->formatPhone($to);

        try {
            $response = Http::withToken($this->token)
                ->post("{$this->apiUrl}/{$this->phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'text',
                    'text' => ['body' => $message],
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('WhatsApp send failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function sendPaymentReminder(string $to, string $customerName, string $machineCode, string $endDate, ?string $paymentUrl = null): bool
    {
        $message = "Hola {$customerName} 👋\n\n"
            . "Te recordamos que tu renta de lavadora *{$machineCode}* vence el *{$endDate}*.\n\n"
            . "Por favor realiza tu pago para continuar con el servicio.";

        if ($paymentUrl) {
            $message .= "\n\n💳 Paga aquí: {$paymentUrl}";
        }

        $message .= "\n\n— Renta Fácil";

        return $this->sendMessage($to, $message);
    }

    public function sendOverdueNotice(string $to, string $customerName, string $machineCode, int $daysOverdue, ?string $paymentUrl = null): bool
    {
        $message = "Hola {$customerName}\n\n"
            . "⚠️ Tu renta de lavadora *{$machineCode}* tiene *{$daysOverdue} días de atraso*.\n\n"
            . "Es importante que regularices tu pago lo antes posible para evitar la recolección del equipo.";

        if ($paymentUrl) {
            $message .= "\n\n💳 Paga aquí: {$paymentUrl}";
        }

        $message .= "\n\n— Renta Fácil";

        return $this->sendMessage($to, $message);
    }

    public function sendPaymentConfirmation(string $to, string $customerName, float $amount, string $newEndDate): bool
    {
        $message = "Hola {$customerName} ✅\n\n"
            . "Hemos recibido tu pago de *\${$amount}*.\n\n"
            . "Tu renta ha sido extendida hasta el *{$newEndDate}*.\n\n"
            . "¡Gracias por tu pago!\n\n— Renta Fácil";

        return $this->sendMessage($to, $message);
    }

    protected function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($phone) === 10) {
            $phone = '52' . $phone;
        }

        return $phone;
    }
}
