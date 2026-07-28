@php
    $statements = $this->getStatements();
@endphp

<x-filament-widgets::widget>
    <x-filament::section heading="Clientes con adeudo">
        @if ($statements->isEmpty())
            <p class="text-sm text-gray-500">Nadie te debe. Todos tus clientes están al corriente.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-gray-500">
                        <tr>
                            <th class="py-2 pr-4">Cliente</th>
                            <th class="py-2 pr-4">Debe desde</th>
                            <th class="py-2 pr-4">Lavadoras</th>
                            <th class="py-2">Debe</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($statements as $statement)
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-4">
                                    <a href="{{ $this->statementUrl($statement) }}"
                                       class="text-primary-600 hover:underline">
                                        {{ $statement->customer->name }}
                                    </a>
                                </td>
                                <td class="py-2 pr-4">
                                    {{ $statement->owingSince?->format('d/m/Y') ?? '—' }}
                                </td>
                                <td class="py-2 pr-4">{{ count($statement->lines) }}</td>
                                <td class="py-2 font-semibold text-danger-600">
                                    ${{ number_format($statement->total, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
