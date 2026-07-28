<x-filament-panels::page>
    {{-- Current Plan Status --}}
    <div style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <div>
                <h2 style="font-size: 20px; font-weight: 700; color: #1f2937; margin: 0 0 4px;">
                    @if($currentPlan)
                        Plan {{ $currentPlan->name }}
                    @else
                        Sin plan activo
                    @endif
                </h2>
                <p style="font-size: 14px; color: #6b7280; margin: 0;">
                    @if($isOnTrial)
                        Prueba gratuita — te quedan <strong style="color: {{ $trialDaysLeft <= 3 ? '#ef4444' : ($trialDaysLeft <= 7 ? '#f59e0b' : '#06b6d4') }}">{{ $trialDaysLeft }} días</strong>
                    @elseif($hasActivePackage)
                        Plan activo hasta el <strong>{{ \Carbon\Carbon::parse($currentPackage->end_date)->format('d/m/Y') }}</strong>
                    @else
                        Tu plan ha expirado. Elige uno para continuar usando la plataforma.
                    @endif
                </p>
            </div>
            <div>
                @if($hasActivePackage && $currentPlan)
                    <span style="display: inline-block; background: #dcfce7; color: #16a34a; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 600;">
                        @if($isOnTrial) Prueba Gratis @else Activo @endif
                    </span>
                @else
                    <span style="display: inline-block; background: #fee2e2; color: #dc2626; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 600;">
                        Expirado
                    </span>
                @endif
            </div>
        </div>

        @if($currentPlan && $hasActivePackage)
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #f3f4f6;">
            <div style="text-align: center;">
                <div style="font-size: 28px; font-weight: 800; color: #0e7490;">{{ $currentPlan->max_washers }}</div>
                <div style="font-size: 13px; color: #6b7280;">Lavadoras permitidas</div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 28px; font-weight: 800; color: #0e7490;">{{ $currentPlan->max_clients }}</div>
                <div style="font-size: 13px; color: #6b7280;">Clientes permitidos</div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 28px; font-weight: 800; color: #0e7490;">
                    @if($currentPackage->end_date)
                        {{ \Carbon\Carbon::parse($currentPackage->end_date)->diffInDays(now()) }}
                    @else
                        —
                    @endif
                </div>
                <div style="font-size: 13px; color: #6b7280;">Días restantes</div>
            </div>
        </div>
        @endif
    </div>

    {{-- Available Plans --}}
    <h3 style="font-size: 18px; font-weight: 700; color: #1f2937; margin-bottom: 16px;">
        @if($hasActivePackage) Cambiar o renovar plan @else Elige tu plan @endif
    </h3>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
        @foreach($packages as $package)
        <div style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 2px solid {{ $currentPlan && $currentPlan->id === $package->id ? '#06b6d4' : '#e5e7eb' }}; position: relative;">

            @if($currentPlan && $currentPlan->id === $package->id && $hasActivePackage)
            <div style="position: absolute; top: -10px; right: 16px; background: #06b6d4; color: white; padding: 3px 12px; border-radius: 12px; font-size: 11px; font-weight: 700;">
                ACTUAL
            </div>
            @endif

            <h4 style="font-size: 18px; font-weight: 700; color: #334155; margin: 0 0 8px;">{{ $package->name }}</h4>

            <div style="margin-bottom: 20px;">
                <span style="font-size: 36px; font-weight: 800; color: #0f172a;">${{ number_format($package->price, 0) }}</span>
                <span style="font-size: 14px; color: #6b7280;">/mes</span>
            </div>

            <ul style="list-style: none; padding: 0; margin: 0 0 20px;">
                <li style="padding: 6px 0; font-size: 14px; color: #374151; display: flex; align-items: center; gap: 8px;">
                    <span style="color: #10b981;">&#10003;</span> {{ $package->max_washers }} lavadoras
                </li>
                <li style="padding: 6px 0; font-size: 14px; color: #374151; display: flex; align-items: center; gap: 8px;">
                    <span style="color: #10b981;">&#10003;</span> {{ $package->max_clients }} clientes
                </li>
                <li style="padding: 6px 0; font-size: 14px; color: #374151; display: flex; align-items: center; gap: 8px;">
                    <span style="color: #10b981;">&#10003;</span> Todas las funciones
                </li>
                <li style="padding: 6px 0; font-size: 14px; color: #374151; display: flex; align-items: center; gap: 8px;">
                    <span style="color: #10b981;">&#10003;</span> 30 días de acceso
                </li>
            </ul>

            @if($package->price > 0)
                @if($currentPlan && $currentPlan->id === $package->id && $hasActivePackage)
                    <span style="display: block; text-align: center; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; background: #e5e7eb; color: #6b7280;">
                        Plan actual
                    </span>
                @else
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        @if(config('services.stripe.enabled'))
                        <a href="{{ route('plan.checkout', $package) }}"
                           style="display: block; text-align: center; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; text-decoration: none; background: #06b6d4; color: white;">
                            Pagar con tarjeta
                        </a>
                        @endif
                        <button onclick="document.getElementById('spei-info-{{ $package->id }}').style.display = document.getElementById('spei-info-{{ $package->id }}').style.display === 'none' ? 'block' : 'none'"
                           style="display: block; width: 100%; text-align: center; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; background: #0e7490; color: white; border: none; cursor: pointer;">
                            Pagar por SPEI / Transferencia
                        </button>
                        <div id="spei-info-{{ $package->id }}" style="display: none; margin-top: 12px; background: #f0fdfa; border: 1px solid #99f6e4; border-radius: 8px; padding: 16px; font-size: 13px; color: #334155;">
                            <p style="font-weight: 700; margin-bottom: 8px; color: #0e7490;">Datos para transferencia SPEI:</p>
                            <table style="width: 100%; font-size: 13px;">
                                <tr><td style="padding: 3px 0; color: #64748b;">Banco:</td><td style="padding: 3px 0; font-weight: 600;">Banco del Bajío</td></tr>
                                <tr><td style="padding: 3px 0; color: #64748b;">Beneficiario:</td><td style="padding: 3px 0; font-weight: 600;">Omar Alonso Lerma Orduño</td></tr>
                                <tr><td style="padding: 3px 0; color: #64748b;">CLABE:</td><td style="padding: 3px 0; font-weight: 600; font-family: monospace;">030743900001300398</td></tr>
                                <tr><td style="padding: 3px 0; color: #64748b;">Cuenta:</td><td style="padding: 3px 0; font-weight: 600;">10004125</td></tr>
                                <tr><td style="padding: 3px 0; color: #64748b;">Monto:</td><td style="padding: 3px 0; font-weight: 700; color: #0e7490;">${{ number_format($package->price, 2) }} MXN</td></tr>
                                <tr><td style="padding: 3px 0; color: #64748b;">Concepto:</td><td style="padding: 3px 0; font-weight: 600;">Plan {{ $package->name }} - Renta Fácil</td></tr>
                            </table>
                            <a href="https://wa.me/6682493398?text=Ya%20hice%20mi%20transferencia%20SPEI%20por%20el%20plan%20{{ rawurlencode($package->name) }}%20%28%24{{ $package->price }}%29.%20Mi%20empresa%3A%20" target="_blank"
                               style="display: block; text-align: center; margin-top: 12px; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; background: #25D366; color: white;">
                                Ya hice mi pago, confirmar por WhatsApp
                            </a>
                        </div>
                    </div>
                @endif
            @else
                <span style="display: block; text-align: center; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; background: #f3f4f6; color: #6b7280;">
                    Plan gratuito
                </span>
            @endif
        </div>
        @endforeach
    </div>
</x-filament-panels::page>
