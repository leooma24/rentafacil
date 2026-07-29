@php
    $guia = $this->getGuia();
    $siguiente = $guia->siguiente();
@endphp

<x-filament-widgets::widget>
    <x-filament::section class="rf-guia">
        <x-slot name="heading">Pruébalo tú mismo</x-slot>
        <x-slot name="description">
            @if ($guia->termino())
                Ya viste las {{ $guia->total() }}. Los datos son de ejemplo: haz lo que quieras, no se le rompe nada a nadie.
            @else
                {{ $guia->total() }} cosas que este sistema hace y que no se ven nada más mirando la pantalla.
                Llevas {{ $guia->vistos() }}. Los datos son de ejemplo: puedes cobrar, borrar y cambiar lo que quieras.
            @endif
        </x-slot>

        <div class="rf-guia-lista">
            @foreach ($guia->pasos as $i => $paso)
                <button
                    type="button"
                    wire:click="abrir('{{ $paso['clave'] }}', '{{ $paso['url'] }}')"
                    class="rf-guia-paso @if ($paso['visto']) rf-guia-paso-visto @endif @if ($siguiente && $siguiente['clave'] === $paso['clave']) rf-guia-paso-sigue @endif"
                >
                    <span class="rf-guia-num">
                        @if ($paso['visto'])
                            <x-filament::icon icon="heroicon-s-check" class="h-4 w-4" />
                        @else
                            {{ $i + 1 }}
                        @endif
                    </span>

                    <span class="rf-guia-texto">
                        <span class="rf-guia-titulo">
                            <x-filament::icon :icon="$paso['icono']" class="h-4 w-4 flex-shrink-0" style="margin-top: .1rem;" />
                            {{ $paso['titulo'] }}
                        </span>
                        <span class="rf-guia-gancho">{{ $paso['gancho'] }}</span>
                    </span>
                </button>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
