<?php

namespace App\Filament\Widgets;

use App\Models\Company;
use App\Support\PlanUsage;
use App\Support\ShareableLinks;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

/**
 * Cuánto alcanzó a cargar cada cuenta que sí arrancó.
 *
 * La lista de al lado enseña a los que nunca cargaron nada. Faltaba la otra
 * mitad: quién sí lo usó y cuánto. Una cuenta con el plan vencida que alcanzó a
 * dar de alta ocho equipos ya sabe para qué sirve esto, y desde el escritorio se
 * veía igual que una que se registró y nunca abrió.
 *
 * Por eso el vencido no se esconde ni se pinta de rojo como problema: es la
 * señal más clara de a quién marcarle.
 */
class CuentasQueLoUsaron extends BaseWidget
{
    protected static ?int $sort = 2;

    // Sin esto el widget se queda en el placeholder de carga y nunca aparece.
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Cuánto cargó cada quien';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Company::query()
                    ->where('is_demo', false)
                    ->whereIn('id', DB::table('washing_machines')->select('company_id')->distinct())
                    ->withCount(['washingMachines', 'rentals', 'payments'])
            )
            ->defaultSort('washing_machines_count', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Negocio')
                    // En celular las demás columnas se esconden, así que el
                    // resumen viaja aquí.
                    ->description(fn (Company $record) => collect([
                        $record->washing_machines_count . ' equipos',
                        $record->rentals_count > 0 ? $record->rentals_count . ' rentas' : null,
                        $record->payments_count > 0
                            ? $record->payments_count . ' cobros'
                            : 'sin cobrar',
                    ])->filter()->join(' · '))
                    ->searchable(),

                Tables\Columns\TextColumn::make('washing_machines_count')
                    ->label('Equipos')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('rentals_count')
                    ->label('Rentas')
                    ->sortable()
                    ->visibleFrom('lg'),

                Tables\Columns\TextColumn::make('payments_count')
                    ->label('Cobros')
                    ->sortable()
                    ->visibleFrom('lg'),

                Tables\Columns\TextColumn::make('ultimo_movimiento')
                    ->label('Último cobro')
                    ->state(function (Company $record) {
                        $fecha = DB::table('payments')
                            ->where('company_id', $record->id)
                            ->max('payment_date');

                        return $fecha
                            ? \Carbon\Carbon::parse($fecha)->diffForHumans()
                            : 'Nunca';
                    })
                    ->color(fn (string $state) => $state === 'Nunca' ? 'gray' : null)
                    ->visibleFrom('md'),

                // El plan va sin dramatizarse: vencido aquí no es una alarma,
                // es la razón para marcarle.
                Tables\Columns\TextColumn::make('plan')
                    ->label('Plan')
                    ->state(fn (Company $record) => PlanUsage::for($record)->planLabel())
                    ->badge()
                    ->color(fn (Company $record) => PlanUsage::for($record)->planColor())
                    ->visibleFrom('md'),
            ])
            ->actions([
                Tables\Actions\Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->visible(fn (Company $record) => filled($record->phone))
                    ->url(fn (Company $record) => ShareableLinks::whatsappUrl(
                        $record->phone,
                        self::mensajePara($record)
                    ))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('Todavía nadie ha cargado equipo')
            ->emptyStateDescription('En cuanto una cuenta dé de alta su primera lavadora, aparece aquí.')
            ->emptyStateIcon('heroicon-o-archive-box')
            ->paginated([10, 25, 50]);
    }

    /**
     * El mensaje cambia según hasta dónde llegó: a quien se le venció el plan
     * se le habla de reactivar, y a quien cargó y no ha cobrado, de arrancar.
     */
    private static function mensajePara(Company $empresa): string
    {
        $equipos = $empresa->washingMachines()->count();

        if (! $empresa->hasActivePackage()) {
            return "Qué tal, le escribo de Renta Fácil. Vi que ya tenía sus {$equipos} equipos "
                . 'cargados y se le venció el plan. Si quiere se lo reactivo con todo lo que ya '
                . 'tenía capturado, tal como lo dejó. ¿Le sirve?';
        }

        if ($empresa->payments()->count() === 0) {
            return "Qué tal, le escribo de Renta Fácil. Vi que ya cargó sus {$equipos} equipos. "
                . 'Si gusta le enseño en cinco minutos cómo registrar un cobro para que la fecha '
                . 'de cada cliente se le mueva sola. ¿Cuándo le queda bien?';
        }

        return 'Qué tal, le escribo de Renta Fácil. Solo para saber cómo le ha ido con el sistema '
            . 'y si hay algo en lo que le pueda ayudar.';
    }
}
