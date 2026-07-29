{{-- Clases rf-, estilizadas en public/css/panel.css. --}}
<x-filament-panels::page>
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
