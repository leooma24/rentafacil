@php
    $prospect = $this->getProspect();
    $ciudades = $this->getCities();
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-sm text-gray-500">Te faltan por contactar</p>
                <p class="text-3xl font-bold">{{ $this->getPendingCount() }}</p>
            </div>

            @if (count($ciudades))
                <div class="w-full sm:w-64">
                    <label class="mb-1 block text-sm text-gray-500">Ciudad</label>
                    <select
                        wire:model.live="ciudad"
                        wire:change="cambiarCiudad"
                        class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900"
                    >
                        <option value="">Todas</option>
                        @foreach ($ciudades as $ciudad)
                            <option value="{{ $ciudad }}">{{ $ciudad }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>
    </x-filament::section>

    @if (! $prospect)
        <x-filament::section>
            <p class="text-sm font-semibold">No queda nadie por contactar.</p>
            <p class="text-sm text-gray-500">
                Ya trabajaste toda la lista{{ $this->ciudad ? ' de ' . $this->ciudad : '' }}.
            </p>
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">{{ $prospect->name }}</x-slot>
            <x-slot name="description">
                {{ collect([$prospect->business_name, $prospect->city, $prospect->phone])->filter()->join(' · ') }}
            </x-slot>

            <div class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm text-gray-500">Mensaje</label>
                    <select
                        wire:model.live="plantilla"
                        class="fi-input block w-full max-w-xs rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900"
                    >
                        @foreach (\App\Support\ProspectOutreach::PLANTILLAS as $clave => $nombre)
                            <option value="{{ $clave }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Estilo en línea: las utilidades de Tailwind que no usa Filament
                     no entran en su CSS compilado y el bloque saldría sin fondo. --}}
                <pre style="white-space: pre-wrap; overflow-wrap: anywhere; background: rgba(0,0,0,.04); border-radius: .5rem; padding: 1rem; font-size: .875rem; line-height: 1.5;">{{ $this->getMessagePreview() }}</pre>

                <x-filament::button
                    tag="a"
                    href="{{ $this->getWhatsappUrl() }}"
                    target="_blank"
                    rel="noopener"
                    wire:click="marcarContactado"
                    color="success"
                    size="lg"
                    icon="heroicon-o-chat-bubble-left-ellipsis"
                >
                    Abrir WhatsApp
                </x-filament::button>

                <p class="text-sm text-gray-500">
                    Al abrirlo se marca como contactado. Cuando vuelvas, dime cómo te fue:
                </p>

                <div class="flex flex-wrap gap-2">
                    <x-filament::button wire:click="calificar('interesado')" color="success">
                        Le interesó
                    </x-filament::button>
                    <x-filament::button wire:click="calificar('demo')" color="info">
                        Agendé demo
                    </x-filament::button>
                    <x-filament::button wire:click="calificar('no_interesado')" color="danger">
                        No le interesó
                    </x-filament::button>
                    <x-filament::button wire:click="calificar('cliente')" color="primary">
                        Ya es cliente
                    </x-filament::button>
                    <x-filament::button wire:click="saltar" color="gray">
                        Saltar
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
