<?php

namespace App\Filament\Widgets;

use App\Models\Company;
use App\Support\PanoramaSaaS;
use App\Support\ShareableLinks;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Las cuentas que se registraron y nunca cargaron un equipo.
 *
 * Es la lista más accionable que hay para quien vende la app: son personas que
 * ya dijeron que sí, dejaron su teléfono y se quedaron en la puerta. Once de
 * diecisiete están aquí.
 *
 * Trae el botón de WhatsApp con el mensaje escrito, porque el objetivo no es
 * mirar la lista: es marcarles.
 */
class CuentasAtoradas extends BaseWidget
{
    protected static ?int $sort = 1;

    // Sin esto el widget se queda en el placeholder de carga y nunca aparece.
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Se registraron y no han cargado nada';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Company::query()
                    ->where('is_demo', false)
                    ->where('created_at', '<=', now()->subDays(PanoramaSaaS::DIAS_PARA_CONSIDERARLA_ATORADA))
                    ->whereNotIn('id', DB::table('washing_machines')->select('company_id')->distinct())
                    ->orderBy('created_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Negocio')
                    ->description(fn (Company $record) => collect([$record->phone, $record->email])
                        ->filter()
                        ->join(' · '))
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Se registró')
                    ->date('d/m/Y')
                    ->description(fn (Company $record) => $record->created_at->diffForHumans())
                    ->sortable()
                    ->visibleFrom('md'),

                // Si ya se le venció la prueba, marcarle es otra conversación.
                Tables\Columns\TextColumn::make('plan')
                    ->label('Plan')
                    ->state(fn (Company $record) => \App\Support\PlanUsage::for($record)->planLabel())
                    ->badge()
                    ->color(fn (Company $record) => \App\Support\PlanUsage::for($record)->planColor())
                    ->visibleFrom('lg'),
            ])
            ->actions([
                Tables\Actions\Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->visible(fn (Company $record) => filled($record->phone))
                    ->url(fn (Company $record) => ShareableLinks::whatsappUrl(
                        $record->phone,
                        "Qué tal, le escribo de Renta Fácil. Vi que abrió su cuenta y todavía no ha "
                        . "cargado sus lavadoras. Si quiere, yo se las subo desde su Excel y se la dejo "
                        . "lista para usarse, sin costo. ¿Le parece?"
                    ))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('Todas las cuentas arrancaron')
            ->emptyStateDescription('No hay nadie registrado que no haya cargado su equipo.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([10, 25]);
    }
}
