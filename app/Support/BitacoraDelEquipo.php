<?php

namespace App\Support;

use App\Models\Maintenance;
use App\Models\RentalMachineChange;
use App\Models\WashingMachine;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Toda la vida de un aparato en una sola línea de tiempo.
 *
 * Hoy lo de una lavadora está repartido en cuatro pantallas: quién la ha tenido
 * está en Rentas, lo que se le ha reparado en Mantenimientos, lo que le pasó en
 * Incidencias, y de dónde vino en el historial de cambios. Para contestar "¿por
 * qué esta lavadora me está saliendo tan cara?" hay que abrir las cuatro y
 * armarlo de memoria, así que no se hace.
 *
 * Todo va ordenado por fecha y revuelto a propósito: lo que explica a un aparato
 * es la secuencia —se le rentó a este, se descompuso, se cambió, volvió, se
 * reparó— y no cuatro listas paralelas que hay que cruzar con el dedo.
 */
class BitacoraDelEquipo
{
    /** @param Collection<int, object> $eventos */
    private function __construct(
        public readonly WashingMachine $equipo,
        public readonly Collection $eventos,
        public readonly RentabilidadDelEquipo $rentabilidad,
    ) {
    }

    public static function for(WashingMachine $equipo): self
    {
        return new self(
            equipo: $equipo,
            eventos: self::eventos($equipo)->sortByDesc('fecha')->values(),
            rentabilidad: RentabilidadDelEquipo::for($equipo),
        );
    }

    /** @return Collection<int, object> */
    private static function eventos(WashingMachine $equipo): Collection
    {
        $eventos = collect();

        if ($equipo->purchase_date) {
            $eventos->push((object) [
                'fecha' => Carbon::parse($equipo->purchase_date),
                'tipo' => 'compra',
                'icono' => 'heroicon-o-shopping-bag',
                'color' => 'gray',
                'titulo' => 'La compraste',
                'detalle' => $equipo->purchase_price > 0
                    ? 'Costó $' . number_format((float) $equipo->purchase_price, 2)
                    : 'Sin precio capturado',
            ]);
        }

        foreach ($equipo->rentals()->with('customer')->get() as $renta) {
            $quien = $renta->customer?->name ?? 'un cliente';
            $cobrado = (float) $renta->payments()->where('status', 'completado')->sum('amount');

            $eventos->push((object) [
                'fecha' => Carbon::parse($renta->start_date),
                'tipo' => 'renta',
                'icono' => 'heroicon-o-user',
                'color' => 'primary',
                'titulo' => 'Se le rentó a ' . $quien,
                'detalle' => $cobrado > 0
                    ? 'Dejó $' . number_format($cobrado, 2) . ' en ' . $renta->payments()->count() . ' cobros'
                    : 'Sin cobros registrados',
            ]);

            // El cierre sólo se anota si de verdad cerró: una renta viva no tiene
            // final que contar todavía.
            if (! in_array($renta->status, ['activa', 'vencida'], true)) {
                $debio = (float) $renta->debt_at_close;

                $eventos->push((object) [
                    'fecha' => Carbon::parse($renta->end_date),
                    'tipo' => 'devolucion',
                    'icono' => 'heroicon-o-arrow-uturn-left',
                    'color' => $debio > 0 ? 'danger' : 'success',
                    'titulo' => $renta->status === 'cancelada'
                        ? 'Se canceló la renta de ' . $quien
                        : 'Volvió de ' . $quien,
                    'detalle' => match (true) {
                        $debio > 0 && ! $renta->debt_settled => 'Quedó debiendo $' . number_format($debio, 2),
                        $debio > 0 => 'Quedó debiendo $' . number_format($debio, 2) . ', se le perdonó',
                        default => 'Al corriente',
                    },
                ]);
            }
        }

        foreach (Maintenance::where('washing_machine_id', $equipo->id)->get() as $orden) {
            $eventos->push((object) [
                'fecha' => Carbon::parse($orden->start_date),
                'tipo' => 'mantenimiento',
                'icono' => 'heroicon-o-wrench-screwdriver',
                'color' => 'warning',
                'titulo' => ucfirst($orden->maintenance_type) . ' · ' . $orden->technician_name,
                'detalle' => trim(($orden->description ?? '')
                    . ((float) $orden->cost > 0 ? ' — $' . number_format((float) $orden->cost, 2) : '')),
            ]);
        }

        foreach ($equipo->incidents()->get() as $reporte) {
            $eventos->push((object) [
                'fecha' => Carbon::parse($reporte->created_at),
                'tipo' => 'incidencia',
                'icono' => 'heroicon-o-exclamation-triangle',
                'color' => 'danger',
                'titulo' => 'Reporte: ' . $reporte->title,
                'detalle' => ucfirst(str_replace('_', ' ', (string) $reporte->status)),
            ]);
        }

        // Los cambios se anotan por los dos lados: entró a cubrir a otra, o salió
        // y otra la cubrió. Sin las dos mitades la secuencia no se entiende.
        $cambios = RentalMachineChange::where('from_machine_id', $equipo->id)
            ->orWhere('to_machine_id', $equipo->id)
            ->with(['rental.customer', 'fromMachine', 'toMachine'])
            ->get();

        foreach ($cambios as $cambio) {
            $salio = $cambio->from_machine_id === $equipo->id;
            $quien = $cambio->rental?->customer?->name ?? 'un cliente';

            $eventos->push((object) [
                'fecha' => Carbon::parse($cambio->created_at),
                'tipo' => 'cambio',
                'icono' => 'heroicon-o-arrow-path',
                'color' => 'info',
                'titulo' => $salio
                    ? 'Se le retiró a ' . $quien . ' y se le dio otra'
                    : 'Entró a cubrir con ' . $quien,
                'detalle' => 'Motivo: ' . $cambio->reason,
            ]);
        }

        return $eventos;
    }

    /** Cuántas veces ha regresado: el número que dice si vale la pena tenerla. */
    public function vecesQueVolvio(): int
    {
        return $this->eventos->where('tipo', 'devolucion')->count();
    }

    public function reparaciones(): int
    {
        return $this->eventos->where('tipo', 'mantenimiento')->count();
    }

    public function clientesQueLaHanTenido(): int
    {
        return $this->eventos->where('tipo', 'renta')->count();
    }

    /** Cuántos días lleva sin generar, si está libre. */
    public function diasParada(): ?int
    {
        if (! in_array($this->equipo->status, ['disponible', 'en_revision'], true)) {
            return null;
        }

        $ultima = $this->equipo->rentals()->max('end_date')
            ?? $this->equipo->purchase_date
            ?? $this->equipo->created_at;

        return max(0, (int) Carbon::parse($ultima)->startOfDay()->diffInDays(Carbon::today(), false));
    }
}
