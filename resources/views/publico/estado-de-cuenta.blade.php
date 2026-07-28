@php
    $negocio = $company?->name ?? 'Renta Fácil';
    $debe = $statement->hasDebt();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Estado de cuenta — {{ $negocio }}</title>
    @include('publico.meta', [
        'titulo' => 'Estado de cuenta — ' . $negocio,
        'descripcion' => 'Consulta tu saldo y tus lavadoras.',
    ])
    {{-- CSS propio, igual que el recibo: esta página vive fuera del panel. --}}
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
        .saldo-etiqueta { font-size: 13px; color: #64748b; margin-bottom: 4px; }
        .saldo { font-size: 40px; font-weight: 700; line-height: 1.1; }
        .saldo.debe { color: #dc2626; }
        .saldo.ok { color: #059669; font-size: 26px; }
        .desde { margin-top: 6px; font-size: 14px; color: #64748b; }
        .titulo { margin: 26px 0 8px; font-size: 14px; font-weight: 700; color: #475569;
                  text-transform: uppercase; letter-spacing: .03em; }
        .maquina { display: flex; justify-content: space-between; gap: 16px;
                   padding: 12px 0; border-bottom: 1px solid #f1f5f9; font-size: 15px; }
        .maquina .codigo { font-weight: 600; }
        .maquina .detalle { font-size: 13px; color: #64748b; }
        .maquina .importe { font-weight: 700; text-align: right; white-space: nowrap; }
        .maquina .importe.debe { color: #dc2626; }
        .aviso { margin-top: 20px; padding: 14px 16px; background: #f8fafc;
                 border-radius: 10px; font-size: 14px; color: #475569; }
        .pie { text-align: center; font-size: 13px; color: #94a3b8; padding: 16px; }
    </style>
</head>
<body>
    <div class="hoja">
        <div class="encabezado">
            <div class="negocio">{{ $negocio }}</div>
            <h1>Estado de cuenta</h1>
        </div>

        <div class="cuerpo">
            <div class="saldo-etiqueta">{{ $customer->name }}</div>

            @if (! $statement->calculable)
                <div class="saldo ok">Sin información</div>
                <p class="desde">Todavía no podemos calcular tu saldo. Pregúntale a tu proveedor.</p>
            @elseif ($debe)
                <div class="saldo debe">${{ number_format($statement->total, 2) }}</div>
                @if ($statement->owingSince)
                    <p class="desde">Desde el {{ $statement->owingSince->format('d/m/Y') }}</p>
                @endif
            @else
                <div class="saldo ok">Estás al corriente</div>
                <p class="desde">No debes nada. Gracias.</p>
            @endif

            @if ($statement->calculable && count($statement->lines))
                <div class="titulo">Tus lavadoras</div>
                @foreach ($statement->lines as $linea)
                    <div class="maquina">
                        <div>
                            <div class="codigo">{{ $linea->rental->washingMachine?->machine_code ?? '—' }}</div>
                            <div class="detalle">
                                Pagada hasta el
                                {{ \Carbon\Carbon::parse($linea->rental->end_date)->format('d/m/Y') }}
                            </div>
                            @if ($linea->hasCredit())
                                <div class="detalle">
                                    Ya abonaste ${{ number_format($linea->credit, 2) }} ·
                                    faltan ${{ number_format($linea->missingForNextPeriod(), 2) }}
                                </div>
                            @endif
                        </div>
                        <div class="importe {{ $linea->amount > 0 ? 'debe' : '' }}">
                            ${{ number_format($linea->amount, 2) }}
                        </div>
                    </div>
                @endforeach
            @endif

            <div class="aviso">
                Si ya pagaste y aquí todavía aparece pendiente, avísale a tu proveedor.
            </div>
        </div>
    </div>

    <p class="pie">{{ $negocio }} · Renta Fácil</p>
</body>
</html>
