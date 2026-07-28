@php
    $onboarding = $this->getOnboarding();
    $hechos = $onboarding->doneCount();
    $total = count($onboarding->steps);
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Primeros pasos</x-slot>
        <x-slot name="description">
            Llevas {{ $hechos }} de {{ $total }}. En cuanto los termines, este recuadro desaparece.
        </x-slot>

        <ol class="space-y-3">
            @foreach ($onboarding->steps as $paso)
                <li class="flex items-start gap-3">
                    @if ($paso['hecho'])
                        <x-filament::icon
                            icon="heroicon-s-check-circle"
                            class="mt-0.5 h-5 w-5 flex-shrink-0 text-success-600"
                        />
                    @else
                        <x-filament::icon
                            icon="heroicon-o-arrow-right-circle"
                            class="mt-0.5 h-5 w-5 flex-shrink-0 text-primary-600"
                        />
                    @endif

                    <div class="min-w-0">
                        @if ($paso['hecho'])
                            <p class="text-sm font-medium text-gray-400 line-through dark:text-gray-500">
                                {{ $paso['titulo'] }}
                            </p>
                        @else
                            <a href="{{ $this->urlFor($paso['clave']) }}"
                               class="text-sm font-semibold text-primary-600 hover:underline">
                                {{ $paso['titulo'] }}
                            </a>
                            <p class="text-sm text-gray-500">{{ $paso['ayuda'] }}</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    </x-filament::section>
</x-filament-widgets::widget>
