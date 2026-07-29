<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; color: #333; line-height: 1.6; }
        .header { text-align: center; border-bottom: 2px solid #06b6d4; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { color: #06b6d4; margin: 0; font-size: 24px; }
        .header p { margin: 5px 0; color: #666; }
        .section { margin-bottom: 20px; }
        .section h2 { color: #06b6d4; font-size: 14px; border-bottom: 1px solid #e5e7eb; padding-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        table td { padding: 6px 10px; border: 1px solid #e5e7eb; }
        table td:first-child { font-weight: bold; width: 35%; background: #f9fafb; }
        .terms { font-size: 10px; color: #666; }
        .terms ol { padding-left: 20px; }
        .terms li { margin-bottom: 5px; }
        .signatures { margin-top: 60px; display: table; width: 100%; }
        .signature-box { display: table-cell; width: 45%; text-align: center; }
        .signature-line { border-top: 1px solid #333; margin-top: 60px; padding-top: 5px; }
        .footer { text-align: center; font-size: 10px; color: #999; margin-top: 30px; border-top: 1px solid #e5e7eb; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $company->name }}</h1>
        <p>Contrato de Renta de {{ $machine?->kindLabel() ?? 'Equipo' }}</p>
        <p>Folio: RNT-{{ str_pad($rental->id, 6, '0', STR_PAD_LEFT) }}</p>
    </div>

    <div class="section">
        <h2>Datos de la Empresa</h2>
        <table>
            <tr><td>Empresa</td><td>{{ $company->name }}</td></tr>
            <tr><td>Teléfono</td><td>{{ $company->phone ?? 'N/A' }}</td></tr>
            <tr><td>Email</td><td>{{ $company->email ?? 'N/A' }}</td></tr>
        </table>
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
        <h2>Datos del Equipo</h2>
        <table>
            <tr><td>Código</td><td>{{ $machine->machine_code }}</td></tr>
            <tr><td>Qué es</td><td>{{ $machine->kindLabel() }}</td></tr>
            <tr><td>Marca / Modelo</td><td>{{ $machine->brand }} {{ $machine->model }}</td></tr>
            <tr><td>Número de Serie</td><td>{{ $machine->serial_number ?? 'N/A' }}</td></tr>
            @if ($machine->type)
                <tr><td>Cómo carga</td><td>{{ $machine->type }}</td></tr>
            @endif
        </table>
    </div>

    <div class="section">
        <h2>Detalles de la Renta</h2>
        <table>
            <tr><td>Fecha de Inicio</td><td>{{ \Carbon\Carbon::parse($rental->start_date)->format('d/m/Y') }}</td></tr>
            <tr><td>Fecha de Fin</td><td>{{ \Carbon\Carbon::parse($rental->end_date)->format('d/m/Y') }}</td></tr>
            <tr><td>Precio por Periodo</td><td>${{ number_format($terms->price ?? 0, 2) }} MXN</td></tr>
            <tr><td>Periodo de Pago</td><td>Cada {{ $terms->days }} días</td></tr>
            @if ($rental->deposit > 0)
                <tr><td>Depósito en Garantía</td><td>${{ number_format($rental->deposit, 2) }} MXN</td></tr>
            @endif
            @if ($settings?->chargesLateFee())
                <tr>
                    <td>Recargo por Atraso</td>
                    <td>
                        @if ($settings->late_fee_type === 'porcentaje')
                            {{ rtrim(rtrim(number_format($settings->late_fee_amount, 2), '0'), '.') }}% por periodo vencido
                        @else
                            ${{ number_format($settings->late_fee_amount, 2) }} MXN por periodo vencido
                        @endif
                        @if ($settings->late_fee_grace_days > 0)
                            (después de {{ $settings->late_fee_grace_days }} días de gracia)
                        @endif
                    </td>
                </tr>
            @endif
            <tr><td>Estado</td><td>{{ ucfirst($rental->status) }}</td></tr>
        </table>
    </div>

    <div class="section terms">
        <h2>Términos y Condiciones</h2>
        <ol>
            <li>El cliente se compromete a realizar los pagos en las fechas acordadas.</li>
            <li>El equipo debe ser utilizado exclusivamente para uso doméstico.</li>
            <li>El cliente es responsable del cuidado y mantenimiento básico del equipo durante el periodo de renta.</li>
            <li>En caso de daño por mal uso, el cliente deberá cubrir los costos de reparación.</li>
            <li>La empresa se reserva el derecho de recoger el equipo en caso de incumplimiento de pago.</li>
            <li>El cliente debe notificar cualquier falla o problema técnico dentro de las primeras 24 horas.</li>
            <li>Al término del contrato, el equipo debe ser devuelto en las mismas condiciones en que fue entregado.</li>
            @if ($rental->deposit > 0)
                {{-- Lo que el cliente dejó tiene que estar escrito: es su dinero. --}}
                <li>
                    El cliente entrega ${{ number_format($rental->deposit, 2) }} MXN como depósito en garantía,
                    que le será devuelto al entregar el equipo, descontando los daños que hubiera por mal uso.
                </li>
            @endif
            @if ($settings?->chargesLateFee())
                <li>
                    Los pagos que se realicen después de la fecha acordada generan un recargo por cada
                    periodo vencido, en los términos indicados en este contrato.
                </li>
            @endif
        </ol>
    </div>

    <div class="signatures">
        <div class="signature-box">
            <div class="signature-line">
                {{ $company->name }}<br>
                <small>Arrendador</small>
            </div>
        </div>
        <div class="signature-box" style="float: right;">
            <div class="signature-line">
                {{ $customer->name }}<br>
                <small>Arrendatario</small>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>Documento generado el {{ now()->format('d/m/Y H:i') }} | {{ $company->name }} - Renta Fácil</p>
    </div>
</body>
</html>
