{{--
    Las clases van con prefijo rf- y se estilan en public/css/panel.css.
    Las utilidades de Tailwind que Filament no usa no están en su CSS
    compilado, así que escribirlas aquí saldría sin estilo.
--}}
<x-filament-panels::page>
    @php($sinEstrenar = $provecho->sinEstrenar())
    @php($resto = $provecho->resto())

    @if (count($sinEstrenar))
        <div class="rf-provecho-aviso">
            <div class="rf-provecho-aviso-num">{{ count($sinEstrenar) }}</div>
            <div>
                <p class="rf-provecho-aviso-titulo">
                    {{ count($sinEstrenar) === 1
                        ? 'Hay una herramienta que no has estrenado'
                        : 'Hay ' . count($sinEstrenar) . ' herramientas que no has estrenado' }}
                </p>
                <p class="rf-provecho-aviso-texto">
                    Ya vienen incluidas en tu plan. Cada una te quita trabajo del día.
                </p>
            </div>
        </div>

        <h2 class="rf-provecho-seccion">Empieza por aquí</h2>

        <div class="rf-provecho-lista">
            @foreach ($sinEstrenar as $herramienta)
                @include('filament.pages.partials.herramienta', [
                    'herramienta' => $herramienta,
                    'destacada' => true,
                ])
            @endforeach
        </div>
    @else
        <div class="rf-provecho-aviso rf-provecho-aviso-ok">
            <div class="rf-provecho-aviso-num">✓</div>
            <div>
                <p class="rf-provecho-aviso-titulo">Le estás sacando provecho a todo</p>
                <p class="rf-provecho-aviso-texto">
                    No hay herramientas sin estrenar. Aquí abajo quedan como recordatorio.
                </p>
            </div>
        </div>
    @endif

    @if (count($resto))
        <h2 class="rf-provecho-seccion">
            {{ count($sinEstrenar) ? 'Lo demás que ya tienes' : 'Tus herramientas' }}
        </h2>

        <div class="rf-provecho-lista">
            @foreach ($resto as $herramienta)
                @include('filament.pages.partials.herramienta', [
                    'herramienta' => $herramienta,
                    'destacada' => false,
                ])
            @endforeach
        </div>
    @endif

    <p class="rf-provecho-pie">
        Todo esto es el mismo sistema: lo que registras en un lado alimenta al otro.
        Un cobro extiende la renta, la renta alimenta el estado de cuenta, y el estado
        de cuenta es lo que le mandas al cliente por WhatsApp.
    </p>
</x-filament-panels::page>
