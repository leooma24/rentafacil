{{--
    El punto de partida de la ruta, tomado del GPS del aparato.

    Se resuelve aquí, en el navegador, y no en un servicio de mapas: la
    ubicación del dueño no tiene por qué salir a ningún lado. Los campos de
    latitud y longitud siguen abajo por si prefiere escribirlos.
--}}
<div x-data="{ estado: 'listo' }" class="rf-ubicacion">
    <x-filament::button
        type="button"
        icon="heroicon-m-map-pin"
        color="primary"
        x-bind:disabled="estado === 'buscando'"
        x-on:click="
            if (! navigator.geolocation) {
                estado = 'sin-soporte'
                return
            }

            estado = 'buscando'

            navigator.geolocation.getCurrentPosition(
                (posicion) => {
                    $wire.set('data.origin_lat', posicion.coords.latitude.toFixed(6))
                    $wire.set('data.origin_lng', posicion.coords.longitude.toFixed(6))
                    estado = 'encontrada'
                },
                () => { estado = 'negada' },
                { enableHighAccuracy: true, timeout: 10000 }
            )
        "
    >
        <span x-show="estado !== 'buscando'">Usar mi ubicación</span>
        <span x-show="estado === 'buscando'" x-cloak>Buscando…</span>
    </x-filament::button>

    <p class="rf-ubicacion-nota" x-show="estado === 'listo' || estado === 'buscando'">
        Para que la ruta arranque desde donde estás parado ahorita.
    </p>
    <p class="rf-ubicacion-nota rf-ubicacion-ok" x-show="estado === 'encontrada'" x-cloak>
        Listo, ya tomé tu ubicación.
    </p>
    <p class="rf-ubicacion-nota rf-ubicacion-mal" x-show="estado === 'negada'" x-cloak>
        No me diste permiso de ubicación. Puedes escribirla abajo o dejarla vacía.
    </p>
    <p class="rf-ubicacion-nota rf-ubicacion-mal" x-show="estado === 'sin-soporte'" x-cloak>
        Este navegador no da la ubicación. Escríbela abajo o déjala vacía.
    </p>
</div>
