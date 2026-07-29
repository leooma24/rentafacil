@php
    $d = $this->getDecision();
@endphp

{{-- Clases rf-, estilizadas en public/css/panel.css. --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">¿Te alcanza para otra lavadora?</x-slot>
        <x-slot name="description">
            Las tres cifras que deciden, juntas: si está todo colocado, si hay a quién dárselo,
            y en cuánto se paga con tu tarifa.
        </x-slot>

        <div class="rf-crecer">
            <div class="rf-crecer-cifras">
                <div class="rf-crecer-cifra">
                    <span class="rf-crecer-num">{{ $d->ocupacion() }}%</span>
                    <span class="rf-crecer-et">colocado</span>
                    <span class="rf-crecer-pie">{{ $d->colocados }} de {{ $d->parque }} equipos</span>
                </div>

                <div class="rf-crecer-cifra @if ($d->parados > 0) rf-crecer-cifra-mal @endif">
                    <span class="rf-crecer-num">{{ $d->parados }}</span>
                    <span class="rf-crecer-et">{{ $d->parados === 1 ? 'parado' : 'parados' }}</span>
                    <span class="rf-crecer-pie">
                        {{ $d->parados > 0 ? 'no están generando nada' : 'todo está trabajando' }}
                    </span>
                </div>

                <div class="rf-crecer-cifra">
                    <span class="rf-crecer-num">{{ $d->prospectos }}</span>
                    <span class="rf-crecer-et">esperando</span>
                    <span class="rf-crecer-pie">
                        {{ $d->prospectos > 0 ? 'pidieron informes' : 'nadie en la lista' }}
                    </span>
                </div>

                <div class="rf-crecer-cifra">
                    <span class="rf-crecer-num">${{ number_format($d->ingresoPorPeriodo, 0) }}</span>
                    <span class="rf-crecer-et">por periodo</span>
                    <span class="rf-crecer-pie">
                        si cobras todo lo colocado
                    </span>
                </div>
            </div>

            <p class="rf-crecer-veredicto rf-crecer-{{ $d->color() }}">
                {{ $d->veredicto() }}
            </p>

            @if ($d->convieneComprar())
                <div class="rf-crecer-accion">
                    <x-filament::button
                        :href="route('filament.propietario.pages.contactar', ['tenant' => \Filament\Facades\Filament::getTenant()])"
                        tag="a"
                        icon="heroicon-o-phone"
                        color="success"
                        size="sm"
                    >
                        Ver a quién le urge
                    </x-filament::button>
                </div>
            @elseif ($d->parados > 0)
                <div class="rf-crecer-accion">
                    <x-filament::button
                        :href="route('filament.propietario.resources.lavadoras.index', ['tenant' => \Filament\Facades\Filament::getTenant()])"
                        tag="a"
                        icon="heroicon-o-archive-box"
                        color="warning"
                        size="sm"
                    >
                        Ver los que están parados
                    </x-filament::button>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
