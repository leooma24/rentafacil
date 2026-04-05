<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; color: #333; line-height: 1.6; }
        .header { text-align: center; border-bottom: 2px solid #10b981; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { color: #10b981; margin: 0; font-size: 22px; }
        .header .folio { font-size: 16px; color: #666; margin-top: 5px; }
        .badge { display: inline-block; padding: 3px 12px; border-radius: 12px; font-size: 11px; font-weight: bold; color: white; }
        .badge-success { background: #10b981; }
        .badge-warning { background: #f59e0b; }
        .badge-danger { background: #ef4444; }
        .section { margin-bottom: 20px; }
        .section h2 { color: #10b981; font-size: 13px; border-bottom: 1px solid #e5e7eb; padding-bottom: 5px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        table td { padding: 8px 10px; border: 1px solid #e5e7eb; }
        table td:first-child { font-weight: bold; width: 40%; background: #f9fafb; }
        .total-row td { background: #ecfdf5 !important; font-size: 16px; font-weight: bold; }
        .footer { text-align: center; font-size: 10px; color: #999; margin-top: 40px; border-top: 1px solid #e5e7eb; padding-top: 10px; }
        .watermark { text-align: center; margin: 20px 0; font-size: 10px; color: #999; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $company->name }}</h1>
        <p class="folio">Recibo de Pago #{{ str_pad($payment->id, 6, '0', STR_PAD_LEFT) }}</p>
        <span class="badge {{ $payment->status === 'completado' ? 'badge-success' : ($payment->status === 'pendiente' ? 'badge-warning' : 'badge-danger') }}">
            {{ strtoupper($payment->status) }}
        </span>
    </div>

    <div class="section">
        <h2>Datos del Cliente</h2>
        <table>
            <tr><td>Nombre</td><td>{{ $customer->name }}</td></tr>
            <tr><td>Email</td><td>{{ $customer->email ?? 'N/A' }}</td></tr>
            <tr><td>Teléfono</td><td>{{ $customer->phone ?? 'N/A' }}</td></tr>
        </table>
    </div>

    <div class="section">
        <h2>Detalle del Pago</h2>
        <table>
            <tr><td>Fecha de Pago</td><td>{{ \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y') }}</td></tr>
            <tr><td>Método de Pago</td><td>{{ $payment->payment_method }}</td></tr>
            <tr><td>Referencia</td><td>{{ $payment->reference ?? 'N/A' }}</td></tr>
            <tr><td>Lavadora</td><td>{{ $machine->machine_code }} - {{ $machine->brand }} {{ $machine->model }}</td></tr>
            <tr><td>Periodo de Renta</td><td>{{ \Carbon\Carbon::parse($rental->start_date)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($rental->end_date)->format('d/m/Y') }}</td></tr>
            <tr class="total-row"><td>TOTAL</td><td>${{ number_format($payment->amount, 2) }} MXN</td></tr>
        </table>
    </div>

    <div class="watermark">
        Este documento es un comprobante de pago generado electrónicamente.
    </div>

    <div class="footer">
        <p>Recibo generado el {{ now()->format('d/m/Y H:i') }} | {{ $company->name }} - Renta Fácil</p>
    </div>
</body>
</html>
