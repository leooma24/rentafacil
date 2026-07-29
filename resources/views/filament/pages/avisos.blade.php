{{-- Clases rf-, estilizadas en public/css/panel.css. --}}
<x-filament-panels::page>
    {{-- Arriba de los avisos a propósito: al que ya pasó de la raya no se le
         vuelve a mandar mensaje, se va por el equipo. --}}
    @if ($recoger->hay())
        <div class="rf-recoger">
            <div class="rf-recoger-encabezado">
                <div>
                    <p class="rf-recoger-titulo">
                        Ya toca ir por {{ $recoger->cuantas() }}
                        {{ $recoger->cuantas() === 1 ? 'equipo' : 'equipos' }}
                    </p>
                    <p class="rf-recoger-texto">
                        Llevan {{ $recoger->periodosDeTolerancia }}
                        {{ $recoger->periodosDeTolerancia === 1 ? 'periodo' : 'periodos' }} o más sin pagar.
                        @if ($recoger->rentaDetenidaPorPeriodo() > 0)
                            Son <strong>${{ number_format($recoger->rentaDetenidaPorPeriodo(), 2) }}</strong>
                            de renta detenida cada periodo.
                        @endif
                    </p>
                </div>

                @if ($recoger->ubicados() > 0)
                    <x-filament::button
                        :href="route('filament.propietario.pages.rutas', ['tenant' => \Filament\Facades\Filament::getTenant()])"
                        tag="a"
                        icon="heroicon-o-map"
                        color="danger"
                        size="sm"
                    >
                        Armar la ruta
                    </x-filament::button>
                @endif
            </div>

            <div class="rf-recoger-lista">
                @foreach ($recoger->rentas as $renta)
                    <div class="rf-recoger-fila">
                        <span class="rf-recoger-cliente">{{ $renta->customer?->name ?? 'Cliente' }}</span>
                        <span class="rf-recoger-equipo">
                            {{ $renta->washingMachine?->machine_code ?? '—' }}
                            · {{ $renta->washingMachine?->kindLabel() ?? 'equipo' }}
                        </span>
                        <span class="rf-recoger-atraso">
                            desde el {{ \Carbon\Carbon::parse($renta->end_date)->format('d/m/Y') }}
                        </span>
                    </div>
                @endforeach
            </div>

            <p class="rf-recoger-pie">
                Se recoge desde Equipos, con el botón <strong>Recoger equipo</strong>. Ahí se decide qué
                pasa con lo que quedó debiendo: si queda anotado o si quedaron en paz.
                El corte lo cambias en Preferencias.
            </p>
        </div>
    @endif

    @if (! $avisos->hayAvisos())
        <div class="rf-avisos-vacio">
            <p class="rf-avisos-vacio-titulo">No hay nadie a quien avisarle hoy</p>
            <p class="rf-avisos-vacio-texto">
                Aquí van a salir los clientes que se vencen en los próximos
                {{ \App\Support\AvisosDelDia::DIAS_DE_ANTICIPACION }} días y los que ya se atrasaron.
            </p>
        </div>
    @else
        @php($vencidos = $avisos->vencidos())
        @php($porVencer = $avisos->porVencer())

        <div class="rf-avisos-resumen">
            <div class="rf-avisos-cifra rf-avisos-cifra-mal">
                <span class="rf-avisos-num">{{ $vencidos->count() }}</span>
                <span class="rf-avisos-etiqueta">ya vencidos</span>
            </div>
            <div class="rf-avisos-cifra">
                <span class="rf-avisos-num">{{ $porVencer->count() }}</span>
                <span class="rf-avisos-etiqueta">vencen pronto</span>
            </div>
        </div>

        <div class="rf-avisos-lista">
            @foreach ($avisos->porUrgencia() as $aviso)
                <div class="rf-aviso {{ $aviso->vencida ? 'rf-aviso-vencido' : '' }}">
                    <div class="rf-aviso-datos">
                        <p class="rf-aviso-cliente">{{ $aviso->cliente() }}</p>
                        <p class="rf-aviso-equipo">{{ $aviso->equipo() }}</p>
                        <p class="rf-aviso-cuando {{ $aviso->vencida ? 'rf-aviso-cuando-mal' : '' }}">
                            {{ $aviso->cuando() }}
                            @if ($aviso->adeudo > 0)
                                · debe ${{ number_format($aviso->adeudo, 2) }}
                            @endif
                        </p>
                    </div>

                    <div class="rf-aviso-mensaje">{{ $aviso->mensaje() }}</div>

                    <div class="rf-aviso-accion">
                        <x-filament::button
                            :href="$aviso->whatsappUrl()"
                            tag="a"
                            target="_blank"
                            icon="heroicon-o-chat-bubble-left-ellipsis"
                            :color="$aviso->vencida ? 'danger' : 'primary'"
                            size="sm"
                        >
                            Mandar
                        </x-filament::button>
                    </div>
                </div>
            @endforeach
        </div>

        <p class="rf-avisos-pie">
            Los mensajes no se mandan solos: WhatsApp automático necesita una cuenta de pago.
            Aquí quedan escritos y ordenados para que sólo toques.
        </p>
    @endif
</x-filament-panels::page>
