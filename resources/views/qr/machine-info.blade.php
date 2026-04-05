<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $machine->machine_code }} - Renta Fácil</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f3f4f6; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .card { background: white; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.1); max-width: 400px; width: 100%; overflow: hidden; }
        .header { background: linear-gradient(135deg, #06b6d4, #0891b2); color: white; padding: 24px; text-align: center; }
        .header h1 { font-size: 24px; margin-bottom: 4px; }
        .header p { opacity: 0.9; font-size: 14px; }
        .body { padding: 24px; }
        .info-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f3f4f6; }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #6b7280; font-size: 13px; }
        .info-value { font-weight: 600; color: #1f2937; font-size: 14px; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-disponible { background: #dbeafe; color: #1d4ed8; }
        .badge-rentada { background: #dcfce7; color: #16a34a; }
        .badge-mantenimiento { background: #f3f4f6; color: #6b7280; }
        .badge-vencida { background: #fee2e2; color: #dc2626; }
        .footer { text-align: center; padding: 16px; color: #9ca3af; font-size: 12px; }
        .report-btn { display: block; text-align: center; background: #ef4444; color: white; padding: 12px; border-radius: 8px; text-decoration: none; font-weight: 600; margin-top: 16px; }
        .report-btn:hover { background: #dc2626; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>{{ $machine->machine_code }}</h1>
            <p>{{ $machine->brand }} {{ $machine->model }}</p>
        </div>
        <div class="body">
            <div class="info-row">
                <span class="info-label">Empresa</span>
                <span class="info-value">{{ $machine->company->name ?? 'N/A' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Número de Serie</span>
                <span class="info-value">{{ $machine->serial_number ?? 'N/A' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Tipo</span>
                <span class="info-value">{{ $machine->type ?? 'N/A' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Estado</span>
                <span class="info-value">
                    <span class="badge badge-{{ $machine->status }}">{{ ucfirst($machine->status) }}</span>
                </span>
            </div>
            @if($machine->activeRental)
            <div class="info-row">
                <span class="info-label">Cliente Actual</span>
                <span class="info-value">{{ $machine->activeRental->customer->name ?? 'N/A' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Vence</span>
                <span class="info-value">
                    <span class="badge badge-{{ $machine->activeRental->status }}">
                        {{ \Carbon\Carbon::parse($machine->activeRental->end_date)->format('d/m/Y') }}
                    </span>
                </span>
            </div>
            @endif
        </div>
        <div class="footer">
            Renta Fácil &mdash; {{ $machine->company->name ?? '' }}
        </div>
    </div>
</body>
</html>
