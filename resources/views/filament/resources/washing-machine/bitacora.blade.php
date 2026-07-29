@php
    $b = $this->getBitacora();
    $r = $b->rentabilidad;
    $parada = $b->diasParada();
@endphp

{{-- Clases rf-, estilizadas en public/css/panel.css. --}}
<x-filament-panels::page>
    {{-- Arriba las cifras que contestan "¿me conviene tener esta lavadora?" --}}
    <div class="rf-bit-cifras">
        <div class="rf-bit-cifra">
            <span class="rf-bit-num">${{ number_format($r->cobrado, 0) }}</span>
            <span class="rf-bit-et">te ha dejado entrar</span>
        </div>

        <div class="rf-bit-cifra">
            <span class="rf-bit-num">
                {{ $r->calculable() ? '$' . number_format($r->gastado(), 0) : '—' }}
            </span>
            <span class="rf-bit-et">llevas gastado</span>
            <span class="rf-bit-pie">
                {{ $r->mantenimiento > 0
                    ? 'incluye $' . number_format($r->mantenimiento, 0) . ' de reparaciones'
                    : 'sin reparaciones' }}
            </span>
        </div>

        <div class="rf-bit-cifra rf-bit-{{ $r->color() }}">
            {{-- El signo va antes del peso: "$-6,000" se lee como un error de
                 captura, "-$6,000" se lee como una pérdida. --}}
            <span class="rf-bit-num">
                {{ $r->calculable()
                    ? ($r->resultado() < 0 ? '-' : '') . '$' . number_format(abs($r->resultado()), 0)
                    : '—' }}
            </span>
            <span class="rf-bit-et">resultado</span>
            <span class="rf-bit-pie">{{ $r->veredicto() }}</span>
        </div>

        <div class="rf-bit-cifra">
            <span class="rf-bit-num">{{ $b->clientesQueLaHanTenido() }}</span>
            <span class="rf-bit-et">rentas</span>
            <span class="rf-bit-pie">
                {{ $b->reparaciones() }} {{ $b->reparaciones() === 1 ? 'reparación' : 'reparaciones' }}
                @if ($parada !== null)
                    · {{ $parada }} días parada
                @endif
            </span>
        </div>
    </div>

    <x-filament::section>
        <x-slot name="heading">Todo lo que le ha pasado</x-slot>
        <x-slot name="description">
            De lo más reciente a lo más viejo. Lo que explica a un aparato es la secuencia,
            no cuatro listas por separado.
        </x-slot>

        @if ($b->eventos->isEmpty())
            <p class="rf-bit-vacio">
                Todavía no hay nada que contar de este equipo. En cuanto se rente, se repare
                o se le levante un reporte, va a aparecer aquí.
            </p>
        @else
            <ol class="rf-bit-linea">
                @foreach ($b->eventos as $e)
                    <li class="rf-bit-evento rf-bit-evento-{{ $e->color }}">
                        <span class="rf-bit-punto">
                            <x-filament::icon :icon="$e->icono" class="h-4 w-4" />
                        </span>

                        <span class="rf-bit-cuerpo">
                            <span class="rf-bit-titulo">{{ $e->titulo }}</span>
                            @if (filled($e->detalle))
                                <span class="rf-bit-detalle">{{ $e->detalle }}</span>
                            @endif
                        </span>

                        <span class="rf-bit-fecha">{{ $e->fecha->format('d/m/Y') }}</span>
                    </li>
                @endforeach
            </ol>
        @endif
    </x-filament::section>
</x-filament-panels::page>
