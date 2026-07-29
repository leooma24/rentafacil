{{-- Clases rf-, estilizadas en public/css/panel.css: las utilidades de Tailwind
     que Filament no usa no están en su CSS compilado.

     Va envuelto en x-filament-widgets::widget porque es ese componente el que
     emite el contenedor con el ancho de columna. Sin él, columnSpan 'full' se
     ignora y el widget se queda a media pantalla. --}}
<x-filament-widgets::widget>
<div class="rf-pendientes">
    <div class="rf-pendientes-titulo">Antes de que se acabe el día</div>

    <div class="rf-pendientes-lista">
        @foreach ($pendientes as $pendiente)
            <div class="rf-pendiente rf-pendiente-{{ $pendiente->color }}">
                <div class="rf-pendiente-icono">
                    <x-filament::icon :icon="$pendiente->icono" class="rf-pendiente-svg" />
                </div>

                <div class="rf-pendiente-texto">
                    <p class="rf-pendiente-que">{{ $pendiente->titulo }}</p>
                    <p class="rf-pendiente-porque">{{ $pendiente->detalle }}</p>
                </div>

                <div class="rf-pendiente-accion">
                    <x-filament::button
                        :href="$pendiente->url()"
                        tag="a"
                        size="sm"
                        :color="$pendiente->color"
                    >
                        {{ $pendiente->accion }}
                    </x-filament::button>
                </div>
            </div>
        @endforeach
    </div>
</div>
</x-filament-widgets::widget>
