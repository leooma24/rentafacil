@php
    $statement = $this->getStatement();
    $payments = $this->getPayments();
@endphp

<x-filament-panels::page>
    @if (! $statement->calculable)
        <x-filament::section>
            <div class="text-sm">
                <p class="font-semibold">No se puede calcular el adeudo.</p>
                <p class="text-gray-500">
                    Falta configurar el precio de renta y cada cuántos días se cobra.
                    Ve a Configuración y captúralos para que aparezcan los saldos.
                </p>
            </div>
        </x-filament::section>
    @else
        <x-filament::section>
            <div class="flex flex-wrap items-baseline justify-between gap-4">
                <div>
                    <p class="text-sm text-gray-500">Saldo de {{ $this->record->name }}</p>
                    <p @class([
                        'text-4xl font-bold',
                        'text-danger-600' => $statement->total > 0,
                        'text-success-600' => $statement->total <= 0,
                    ])>
                        ${{ number_format($statement->total, 2) }}
                    </p>
                </div>
                <div class="text-sm text-gray-500">
                    @if ($statement->owingSince)
                        Debe desde el {{ $statement->owingSince->format('d/m/Y') }}
                    @else
                        Está al corriente
                    @endif
                </div>
            </div>
        </x-filament::section>

        <x-filament::section heading="Lavadoras que trae">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-gray-500">
                        <tr>
                            <th class="py-2 pr-4">Código</th>
                            <th class="py-2 pr-4">Desde</th>
                            <th class="py-2 pr-4">Pagado hasta</th>
                            <th class="py-2 pr-4">Periodos vencidos</th>
                            <th class="py-2">Debe</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($statement->lines as $line)
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-4">{{ $line->rental->washingMachine?->machine_code ?? '—' }}</td>
                                <td class="py-2 pr-4">{{ \Carbon\Carbon::parse($line->rental->start_date)->format('d/m/Y') }}</td>
                                <td class="py-2 pr-4">{{ \Carbon\Carbon::parse($line->rental->end_date)->format('d/m/Y') }}</td>
                                <td class="py-2 pr-4">{{ $line->overduePeriods }}</td>
                                <td @class(['py-2 font-semibold', 'text-danger-600' => $line->amount > 0])>
                                    ${{ number_format($line->amount, 2) }}
                                    @if ($line->hasCredit())
                                        <div class="text-xs font-normal text-gray-500">
                                            Abonó ${{ number_format($line->credit, 2) }} ·
                                            faltan ${{ number_format($line->missingForNextPeriod(), 2) }}
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4 text-gray-500">No trae lavadoras rentadas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    <x-filament::section heading="Historial de pagos">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-gray-500">
                    <tr>
                        <th class="py-2 pr-4">Fecha</th>
                        <th class="py-2 pr-4">Monto</th>
                        <th class="py-2 pr-4">Método</th>
                        <th class="py-2 pr-4">Referencia</th>
                        <th class="py-2">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="py-2 pr-4">{{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y') : '—' }}</td>
                            <td class="py-2 pr-4">${{ number_format((float) $payment->amount, 2) }}</td>
                            <td class="py-2 pr-4">{{ $payment->payment_method ?? '—' }}</td>
                            <td class="py-2 pr-4">{{ $payment->reference ?? '—' }}</td>
                            <td class="py-2">{{ ucfirst($payment->status ?? '—') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-4 text-gray-500">Todavía no ha pagado nada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
