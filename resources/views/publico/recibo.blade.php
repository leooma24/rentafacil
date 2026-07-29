@php
    $negocio = $company?->name ?? 'Renta Fácil';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Recibo de pago — {{ $negocio }}</title>
    @include('publico.meta', [
        'titulo' => 'Recibo de pago — ' . $negocio,
        'descripcion' => 'Tu comprobante de pago.',
    ])
    {{-- CSS propio: esta página la abre el cliente desde WhatsApp y no debe
         depender del CSS de Filament, que no compila lo que no usa. --}}
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 24px 16px; background: #f1f5f9; color: #0f172a;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        .hoja { max-width: 480px; margin: 0 auto; background: #fff; border-radius: 16px;
                box-shadow: 0 1px 3px rgba(0,0,0,.1); overflow: hidden; }
        .encabezado { background: #06b6d4; color: #fff; padding: 20px 24px; }
        .encabezado .negocio { font-size: 14px; opacity: .9; }
        .encabezado h1 { margin: 4px 0 0; font-size: 20px; }
        .cuerpo { padding: 24px; }
        .monto { font-size: 40px; font-weight: 700; color: #059669; line-height: 1.1; }
        .monto-etiqueta { font-size: 13px; color: #64748b; margin-bottom: 4px; }
        .datos { margin-top: 24px; border-top: 1px solid #e2e8f0; }
        .dato { display: flex; justify-content: space-between; gap: 16px;
                padding: 12px 0; border-bottom: 1px solid #f1f5f9; font-size: 15px; }
        .dato span:first-child { color: #64748b; }
        .dato span:last-child { text-align: right; font-weight: 600; }
        .cubierta { margin-top: 20px; padding: 14px 16px; background: #ecfdf5;
                    border-radius: 10px; font-size: 15px; color: #065f46; }
        .boton { display: block; margin-top: 24px; padding: 14px; text-align: center;
                 background: #0f172a; color: #fff; text-decoration: none;
                 border-radius: 10px; font-weight: 600; }
        .pie { text-align: center; font-size: 13px; color: #94a3b8; padding: 16px; }
    </style>
</head>
<body>
    <div class="hoja">
        <div class="encabezado">
            <div class="negocio">{{ $negocio }}</div>
            <h1>Recibo de pago</h1>
        </div>

        <div class="cuerpo">
            <div class="monto-etiqueta">Pagaste</div>
            <div class="monto">${{ number_format((float) $payment->amount, 2) }}</div>

            <div class="datos">
                <div class="dato">
                    <span>Fecha</span>
                    <span>{{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y') : '—' }}</span>
                </div>
                <div class="dato">
                    <span>Cliente</span>
                    <span>{{ $customer?->name ?? '—' }}</span>
                </div>
                <div class="dato">
                    <span>Equipo</span>
                    <span>{{ $machine?->machine_code ?? '—' }}</span>
                </div>
                <div class="dato">
                    <span>Método</span>
                    <span>{{ $payment->payment_method ?? '—' }}</span>
                </div>
                <div class="dato">
                    <span>Folio</span>
                    <span>PAG-{{ str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT) }}</span>
                </div>
            </div>

            @if ($rental?->end_date)
                <div class="cubierta">
                    Tu renta queda cubierta hasta el
                    <strong>{{ \Carbon\Carbon::parse($rental->end_date)->format('d/m/Y') }}</strong>.
                </div>
            @endif

            <a class="boton" href="{{ \App\Support\ShareableLinks::receiptPdfUrl($payment) }}">
                Descargar en PDF
            </a>
        </div>
    </div>

    <p class="pie">{{ $negocio }} · Renta Fácil</p>
</body>
</html>
