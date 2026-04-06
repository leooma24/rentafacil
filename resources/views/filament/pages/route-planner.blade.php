<x-filament-panels::page>
    <x-filament-panels::form wire:submit="calculateRoute">
        {{ $this->form }}

        <div style="display: flex; gap: 12px; margin-top: 16px;">
            <x-filament::button type="submit" icon="heroicon-o-map">
                Calcular Mejor Ruta
            </x-filament::button>

            @if($mapsUrl)
            <x-filament::button
                tag="a"
                :href="$mapsUrl"
                target="_blank"
                color="success"
                icon="heroicon-o-arrow-top-right-on-square"
            >
                Abrir en Google Maps
            </x-filament::button>
            @endif
        </div>
    </x-filament-panels::form>

    @if(count($optimizedRoute) > 0)
    <div style="margin-top: 24px;">
        <div style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="font-size: 18px; font-weight: 700; margin: 0;">Ruta Optimizada</h3>
                <span style="background: #dcfce7; color: #16a34a; padding: 4px 14px; border-radius: 20px; font-size: 13px; font-weight: 600;">
                    {{ number_format($totalDistance, 1) }} km aprox.
                </span>
            </div>

            <div style="display: flex; flex-direction: column; gap: 0;">
                @foreach($optimizedRoute as $index => $stop)
                <div style="display: flex; align-items: flex-start; gap: 16px; padding: 16px 0; {{ !$loop->last ? 'border-bottom: 1px solid #f1f5f9;' : '' }}">
                    {{-- Step number --}}
                    <div style="flex-shrink: 0; width: 36px; height: 36px; border-radius: 50%; background: {{ $stop['status'] === 'vencida' ? '#ef4444' : '#06b6d4' }}; color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">
                        {{ $index + 1 }}
                    </div>

                    {{-- Stop info --}}
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                            <strong style="font-size: 15px;">{{ $stop['name'] }}</strong>
                            <span style="background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 8px; font-size: 11px; font-weight: 600;">
                                {{ $stop['machine'] }}
                            </span>
                            @if($stop['status'] === 'vencida')
                            <span style="background: #fee2e2; color: #dc2626; padding: 2px 8px; border-radius: 8px; font-size: 11px; font-weight: 600;">
                                VENCIDA
                            </span>
                            @endif
                        </div>
                        <div style="font-size: 13px; color: #64748b;">
                            {{ $stop['address'] }}
                        </div>
                    </div>

                    {{-- Navigate link --}}
                    <a href="https://www.google.com/maps/dir/?api=1&destination={{ $stop['latitude'] }},{{ $stop['longitude'] }}"
                       target="_blank"
                       style="flex-shrink: 0; background: #f1f5f9; color: #475569; padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 4px;">
                        Navegar
                    </a>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif
</x-filament-panels::page>
