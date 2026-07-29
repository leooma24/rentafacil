<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Actions\Action;
use Filament\Forms\Set;
use App\Models\Setting;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;

class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-s-cog-6-tooth';

    protected static string $view = 'filament.pages.settings';

    protected static ?string $navigationGroup = 'Mi cuenta';
    protected static ?string $navigationLabel = 'Preferencias';
    protected static ?string $title = 'Preferencias';

    protected static ?string $slug = 'configuracion';

    /** Aquí se pone el precio de la renta: es pantalla de dueño. */
    public static function canAccess(): bool
    {
        return \App\Support\Acceso::soloDueno();
    }

    protected static ?int $navigationSort = 10;

    public ?array $data = [];
    public $tenant = null;

    public function mount(): void
    {
        $this->tenant = Filament::getTenant();
        $this->form->fill([
            'price' => $this->tenant->settings?->price,
            'days_per_payment' => $this->tenant->settings?->days_per_payment,
            'late_fee_amount' => $this->tenant->settings?->late_fee_amount ?? 0,
            'late_fee_type' => $this->tenant->settings?->late_fee_type ?? 'fijo',
            'late_fee_grace_days' => $this->tenant->settings?->late_fee_grace_days ?? 0,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Schema fields here
                TextInput::make('price')
                    ->label('Precio por pago')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                TextInput::make('days_per_payment')
                    ->label('Días que cubre el pago')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->required(),

                // Arranca en cero: sin configurarlo, el adeudo se calcula igual
                // que siempre. Un recargo que aparece solo, sin que el dueño lo
                // haya decidido, le rompe la relación con sus clientes.
                \Filament\Forms\Components\Section::make('Recargo por atraso')
                    ->description('Déjalo en cero si no cobras recargos. Nada cambia hasta que pongas una cantidad.')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->collapsed(fn () => ! ($this->tenant->settings?->chargesLateFee() ?? false))
                    ->collapsible()
                    ->schema([
                        TextInput::make('late_fee_amount')
                            ->label('Cuánto')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->required()
                            ->helperText('Se cobra por cada periodo vencido, no por día.'),

                        \Filament\Forms\Components\Select::make('late_fee_type')
                            ->label('Cómo')
                            ->options(\App\Models\Setting::LATE_FEE_TYPES)
                            ->default('fijo')
                            ->native(false)
                            ->required(),

                        TextInput::make('late_fee_grace_days')
                            ->label('Días de gracia')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->required()
                            ->helperText('Quien se atrase menos de estos días no lleva recargo.'),
                    ])
                    ->columns(3),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Guardar')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();
            $this->tenant->settings()->updateOrCreate(['company_id' => $this->tenant->id], $data);

            Notification::make()
                ->title('Configuración guardada')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error al guardar la configuración')
                ->error()
                ->send();
        }
    }
}
