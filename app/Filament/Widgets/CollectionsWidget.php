<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\RentalResource\Actions\ExtendRentAction;
use App\Models\Company;
use App\Models\Rental;
use App\Support\AccountStatement;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * El trabajo del día: a quién hay que cobrarle, con la acción a un clic.
 *
 * Reemplaza a los tres widgets que antes decían lo mismo en pedazos
 * (rentas vencidas, rentas por vencer y clientes con adeudo).
 */
class CollectionsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected static ?string $heading = 'A quién cobrar';

    protected int|string|array $columnSpan = 'full';

    // Sin esto el widget se queda en el placeholder de carga y nunca aparece.
    protected static bool $isLazy = false;

    /**
     * Rentas vivas que ya vencieron o que vencen dentro de 7 días,
     * la más atrasada primero.
     */
    public static function baseQuery(Company $company): Builder
    {
        return Rental::query()
            ->where('company_id', $company->id)
            ->whereIn('status', ['activa', 'vencida'])
            ->whereDate('end_date', '<=', now()->addDays(7)->toDateString())
            ->with(['customer', 'washingMachine', 'payments'])
            ->orderBy('end_date');
    }

    public function table(Table $table): Table
    {
        $tenant = Filament::getTenant();

        return $table
            ->query(static::baseQuery($tenant))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Nadie te debe')
            ->emptyStateDescription('Ninguna renta está vencida ni vence esta semana.')
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->url(fn (Rental $record) => $record->customer
                        ? CustomerResource::getUrl('estado-de-cuenta', ['record' => $record->customer])
                        : null)
                    ->color('primary'),

                Tables\Columns\TextColumn::make('washingMachine.machine_code')
                    ->label('Lavadora'),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Situación')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        $fin = Carbon::parse($state)->startOfDay();
                        $hoy = now()->startOfDay();

                        if ($fin->lt($hoy)) {
                            $dias = (int) $fin->diffInDays($hoy);

                            return $dias === 1 ? 'Vencida 1 día' : "Vencida {$dias} días";
                        }

                        $dias = (int) $hoy->diffInDays($fin);

                        return match (true) {
                            $dias === 0 => 'Vence hoy',
                            $dias === 1 => 'Vence mañana',
                            default => "Vence en {$dias} días",
                        };
                    })
                    ->color(fn ($state) => Carbon::parse($state)->startOfDay()->lt(now()->startOfDay())
                        ? 'danger'
                        : 'warning'),

                Tables\Columns\TextColumn::make('debe')
                    ->label('Debe')
                    ->state(fn (Rental $record) => app(AccountStatement::class)->forRental($record)->amount)
                    ->formatStateUsing(fn ($state) => '$' . number_format((float) $state, 2))
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray')
                    ->weight(fn ($state) => $state > 0 ? 'bold' : 'normal'),
            ])
            ->actions([
                Tables\Actions\Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->visible(fn (Rental $record) => filled($record->customer?->phone))
                    ->requiresConfirmation()
                    ->modalHeading('Enviar recordatorio por WhatsApp')
                    ->modalDescription(fn (Rental $record) => "Se enviará un mensaje a {$record->customer->name} ({$record->customer->phone})")
                    ->action(function (Rental $record) {
                        $whatsapp = app(\App\Services\WhatsAppService::class);
                        $fin = Carbon::parse($record->end_date);

                        if ($record->status === 'vencida') {
                            $whatsapp->sendOverdueNotice(
                                $record->customer->phone,
                                $record->customer->name,
                                $record->washingMachine->machine_code,
                                (int) $fin->startOfDay()->diffInDays(now()->startOfDay()),
                            );
                        } else {
                            $whatsapp->sendPaymentReminder(
                                $record->customer->phone,
                                $record->customer->name,
                                $record->washingMachine->machine_code,
                                $fin->format('d/m/Y'),
                            );
                        }

                        Notification::make()->title('Mensaje de WhatsApp enviado')->success()->send();
                    }),

                ExtendRentAction::make(Filament::getTenant()),
            ]);
    }
}
