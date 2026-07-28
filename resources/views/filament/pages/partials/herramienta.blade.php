{{-- Una tarjeta de la guía. Ver sacale-provecho.blade.php sobre las clases rf-. --}}
<div class="rf-herramienta {{ $destacada ? 'rf-herramienta-destacada' : '' }}">
    <div class="rf-herramienta-icono">
        <x-filament::icon :icon="$herramienta->icono" class="rf-herramienta-svg" />
    </div>

    <div class="rf-herramienta-cuerpo">
        <div class="rf-herramienta-encabezado">
            <h3 class="rf-herramienta-titulo">{{ $herramienta->titulo }}</h3>

            @if ($herramienta->usando())
                <span class="rf-etiqueta rf-etiqueta-ok">Ya la usas</span>
            @elseif ($herramienta->sinEstrenar())
                <span class="rf-etiqueta rf-etiqueta-nueva">Sin estrenar</span>
            @endif
        </div>

        <p class="rf-herramienta-beneficio">{{ $herramienta->beneficio }}</p>

        @if ($herramienta->pista)
            <p class="rf-herramienta-pista">{{ $herramienta->pista }}</p>
        @endif

        <p class="rf-herramienta-como">
            <span class="rf-herramienta-como-rotulo">Cómo:</span>
            {{ $herramienta->comoSeUsa }}
        </p>

        <div class="rf-herramienta-accion">
            <x-filament::button
                :href="$herramienta->url()"
                tag="a"
                size="sm"
                :color="$destacada ? 'primary' : 'gray'"
            >
                {{ $herramienta->accion }}
            </x-filament::button>
        </div>
    </div>
</div>
