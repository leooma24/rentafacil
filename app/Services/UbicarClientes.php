<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Customer;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Busca en el mapa la ubicación de varios clientes de un jalón.
 *
 * Corre dentro de la petición y no en una cola: no hay trabajador de colas
 * corriendo, y una tarea encolada que nadie procesa se ve igual que una que
 * falló. Por eso la tanda está acotada y el dueño ve el resultado enseguida.
 */
class UbicarClientes
{
    /**
     * Nominatim pide una consulta por segundo, y una dirección mal capturada se
     * lleva hasta tres intentos. Por eso hay dos topes: uno de cuántos y otro
     * de cuánto tiempo, y manda el que se cumpla primero. Sin el de tiempo, una
     * tanda de direcciones sucias tumbaría la petición.
     */
    public const MAXIMO_POR_TANDA = 25;

    public const SEGUNDOS_MAXIMOS = 40;

    public function __construct(
        private readonly Geocoder $geocoder,
        /** Segundos entre consultas. Es parámetro para que las pruebas no tarden media hora. */
        private readonly int $pausa = Geocoder::PAUSA_ENTRE_CONSULTAS,
    ) {
    }

    /** @param Collection<int, Customer> $clientes */
    public function paraTodos(Collection $clientes): void
    {
        // Sin load() en la colección: Filament manda una de Eloquent pero las
        // pruebas una simple, y la carga por modelo cuesta nada al lado de la
        // consulta al servicio de mapas que viene después.
        $pendientes = $clientes
            ->flatMap(fn (Customer $cliente) => $cliente->addresses)
            ->reject(fn (Address $direccion) => $direccion->hasCoordinates())
            ->values();

        if ($pendientes->isEmpty()) {
            Notification::make()
                ->title('Ya estaban todos ubicados')
                ->body('Los clientes que escogiste ya tienen su ubicación en el mapa.')
                ->success()
                ->send();

            return;
        }

        $tanda = $pendientes->take(self::MAXIMO_POR_TANDA);

        $ubicados = 0;
        $fallidos = 0;
        $atendidos = 0;
        $arranque = microtime(true);

        foreach ($tanda as $indice => $direccion) {
            // La pausa va entre consultas y no antes de la primera.
            if ($indice > 0 && $this->pausa > 0) {
                sleep($this->pausa);
            }

            $this->geocoder->ubicar($direccion) ? $ubicados++ : $fallidos++;
            $atendidos++;

            if (microtime(true) - $arranque > self::SEGUNDOS_MAXIMOS) {
                break;
            }
        }

        $this->avisar($ubicados, $fallidos, $pendientes->count() - $atendidos);
    }

    private function avisar(int $ubicados, int $fallidos, int $quedan): void
    {
        $detalle = [];

        if ($fallidos > 0) {
            $detalle[] = $fallidos === 1
                ? 'De 1 no se encontró la dirección; revísala o captura la ubicación a mano.'
                : "De {$fallidos} no se encontró la dirección; revísalas o captura la ubicación a mano.";
        }

        if ($quedan > 0) {
            $detalle[] = "Quedan {$quedan} por ubicar: vuelve a seleccionarlos y repite.";
        }

        $aviso = Notification::make()
            ->title($ubicados === 1 ? '1 cliente ubicado en el mapa' : "{$ubicados} clientes ubicados en el mapa")
            ->body(implode(' ', $detalle) ?: 'Ya puedes incluirlos en la ruta del día.');

        $ubicados > 0 ? $aviso->success() : $aviso->warning();

        $aviso->send();
    }
}
