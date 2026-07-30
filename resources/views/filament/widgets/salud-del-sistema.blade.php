@php
    $tareas = $this->getTareas();
    $problemas = $this->getConProblema();
    $datos = $this->getDatos();
    $incoherencias = $datos->sum(fn ($r) => $r->cuantos());
@endphp

{{-- Clases rf-, estilizadas en public/css/panel.css. --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Si el sistema está trabajando</x-slot>
        <x-slot name="description">
            Una tarea caída no se ve por ningún lado: si marcar vencidas se muere, nadie sale
            como vencido y todo parece en orden.
        </x-slot>

        @if ($problemas->isEmpty() && $incoherencias === 0)
            <p class="rf-salud-ok">
                Todas las tareas al corriente y ningún dato contradiciéndose.
            </p>
        @endif

        <div class="rf-salud-lista">
            @foreach ($tareas as $t)
                <div class="rf-salud-fila @if ($t->perdida || $t->ok === false) rf-salud-fila-mal @elseif ($t->nuncaVista) rf-salud-fila-gris @endif">
                    <span class="rf-salud-nombre">
                        {{ $t->queHace }}
                        <span class="rf-salud-comando">{{ $t->tarea }}</span>
                    </span>

                    <span class="rf-salud-estado">
                        @if ($t->nuncaVista)
                            sin datos todavía
                        @elseif ($t->ok === false)
                            falló {{ $t->ultima->diffForHumans() }}
                        @elseif ($t->perdida)
                            sin correr desde {{ $t->ultima->diffForHumans() }}
                        @else
                            corrió {{ $t->ultima->diffForHumans() }}
                        @endif
                    </span>
                </div>
            @endforeach
        </div>

        @if ($incoherencias > 0)
            <div class="rf-salud-datos">
                <p class="rf-salud-datos-titulo">
                    {{ $incoherencias === 1
                        ? '1 dato se contradice'
                        : $incoherencias . ' datos se contradicen' }}
                    en {{ $datos->count() === 1 ? '1 cuenta' : $datos->count() . ' cuentas' }}
                </p>

                @foreach ($datos as $revision)
                    <p class="rf-salud-datos-linea">
                        <strong>{{ $revision->empresa->name }}</strong>
                        @foreach ($revision->hallazgos as $h)
                            <span class="rf-salud-hallazgo">{{ $h->equipo }}: {{ $h->que_pasa }}</span>
                        @endforeach
                    </p>
                @endforeach

                <p class="rf-salud-datos-pie">
                    Son equipos que no aparecen para rentar sin que nada explique por qué.
                    A cada dueño le sale en sus pendientes del día, con el botón para arreglarlo.
                </p>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
