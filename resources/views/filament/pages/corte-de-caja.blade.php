{{-- Clases rf-, estilizadas en public/css/panel.css. Ver sacale-provecho.blade.php. --}}
<x-filament-panels::page>
    {{ $this->form }}

    @php($corte = $this->corte())

    @if ($corte->estaCerrado())
        @php($cierre = $corte->cerrado)
        <div class="rf-corte-cerrado {{ $cierre->cuadra() ? '' : 'rf-corte-cerrado-mal' }}">
            <div>
                <p class="rf-corte-cerrado-titulo">
                    @if ($cierre->cuadra())
                        Este día ya está cerrado y cuadró exacto.
                    @elseif ($cierre->falta())
                        Este día ya está cerrado. Faltaron ${{ number_format(abs($cierre->difference), 2) }}.
                    @else
                        Este día ya está cerrado. Sobraron ${{ number_format($cierre->difference, 2) }}.
                    @endif
                </p>
                <p class="rf-corte-cerrado-texto">
                    Se esperaban ${{ number_format($cierre->expected_cash, 2) }} y se contaron
                    ${{ number_format($cierre->counted_cash, 2) }}.
                    @if ($cierre->notes)
                        «{{ $cierre->notes }}»
                    @endif
                </p>
            </div>
        </div>
    @endif

    <div class="rf-corte-cifras">
        <div class="rf-corte-cifra rf-corte-cifra-efectivo">
            <p class="rf-corte-rotulo">Efectivo que traes</p>
            <p class="rf-corte-monto">${{ number_format($corte->efectivo(), 2) }}</p>
            <p class="rf-corte-nota">Esto es lo que hay que contar y depositar</p>
        </div>

        <div class="rf-corte-cifra">
            <p class="rf-corte-rotulo">En transferencia</p>
            <p class="rf-corte-monto">${{ number_format($corte->transferencias(), 2) }}</p>
            <p class="rf-corte-nota">Ya está en el banco, no se cuenta</p>
        </div>

        <div class="rf-corte-cifra">
            <p class="rf-corte-rotulo">Total del día</p>
            <p class="rf-corte-monto">${{ number_format($corte->total(), 2) }}</p>
            <p class="rf-corte-nota">
                {{ $corte->cuantos() === 1 ? '1 cobro' : $corte->cuantos() . ' cobros' }}
            </p>
        </div>
    </div>

    @if ($corte->cuantos() === 0)
        <div class="rf-corte-vacio">
            No hay cobros registrados este día para esta persona.
        </div>
    @else
        <div class="rf-corte-tabla-caja">
            <h3 class="rf-corte-seccion">Los cobros del día</h3>

            <div class="rf-corte-scroll">
                <table class="rf-corte-tabla">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Lavadora</th>
                            <th>Cómo pagó</th>
                            <th class="rf-corte-derecha">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($corte->cobros as $cobro)
                            <tr>
                                <td>{{ $cobro->rental?->customer?->name ?? '—' }}</td>
                                <td>{{ $cobro->rental?->washingMachine?->machine_code ?? '—' }}</td>
                                <td>{{ ucfirst($cobro->payment_method) }}</td>
                                <td class="rf-corte-derecha rf-corte-monto-fila">
                                    ${{ number_format($cobro->amount, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @php($porCobrador = \App\Support\CorteDeCaja::para(
        \Filament\Facades\Filament::getTenant(),
        \Carbon\Carbon::parse($this->fecha)
    )->porCobrador())

    @if ($porCobrador->count() > 1)
        <div class="rf-corte-tabla-caja">
            <h3 class="rf-corte-seccion">Todo el día, por persona</h3>

            <div class="rf-corte-scroll">
                <table class="rf-corte-tabla">
                    <thead>
                        <tr>
                            <th>Quién cobró</th>
                            <th class="rf-corte-derecha">Cobros</th>
                            <th class="rf-corte-derecha">Efectivo</th>
                            <th class="rf-corte-derecha">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($porCobrador as $fila)
                            <tr>
                                <td>{{ $fila['nombre'] }}</td>
                                <td class="rf-corte-derecha">{{ $fila['cuantos'] }}</td>
                                <td class="rf-corte-derecha">${{ number_format($fila['efectivo'], 2) }}</td>
                                <td class="rf-corte-derecha rf-corte-monto-fila">
                                    ${{ number_format($fila['total'], 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-filament-panels::page>
