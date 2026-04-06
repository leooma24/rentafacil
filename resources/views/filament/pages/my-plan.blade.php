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
                        <a href="https://wa.me/6682493398?text=Quiero%20contratar%20el%20plan%20{{ urlencode($package->name) }}%20%28%24{{ $package->price }}%2Fmes%29.%20Quiero%20pagar%20por%20SPEI." target="_blank"
                           style="display: block; text-align: center; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; text-decoration: none; background: #25D366; color: white;">
                            Pagar por SPEI / Transferencia
                        </a>
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
