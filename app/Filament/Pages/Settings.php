<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;

class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-s-cog-6-tooth';

    protected static string $view = 'filament.pages.settings';

    protected static ?string $navigationGroup = 'Mi cuenta';
    protected static ?string $navigationLabel = 'Preferencias';
    protected static ?string $title = 'Preferencias';

    public function getSubheading(): ?string
    {
        return 'Cómo cobras. Estos números alimentan los cobros, los adeudos, los estados de cuenta y los contratos.';
    }

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
                Section::make('Tu tarifa de renta')
                    ->description('Lo que cobras y cada cuánto. Es la base de todo: los cobros, los adeudos y los estados de cuenta salen de aquí.')
                    ->icon('heroicon-o-banknotes')
                    ->iconColor('primary')
                    ->schema([
                        TextInput::make('price')
                            ->label('Cuánto cobras')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->prefix('$')
                            ->suffix('MXN')
                            ->required()
                            ->live(onBlur: true)
                            ->helperText('Puedes cobrar distinto a un cliente al crear su renta; esto es el precio por omisión.'),

                        TextInput::make('days_per_payment')
                            ->label('Cada cuántos días')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->suffix('días')
                            ->required()
                            ->live(onBlur: true)
                            ->helperText('Cada pago recorre la fecha de vencimiento estos días.'),

                        // Lo que se está configurando, dicho en una frase. Dos
                        // números sueltos no dejan ver si quedó como se quería.
                        Placeholder::make('resumen_tarifa')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->content(function (Get $get) {
                                $precio = (float) ($get('price') ?? 0);
                                $dias = (int) ($get('days_per_payment') ?? 0);

                                if ($precio <= 0 || $dias <= 0) {
                                    return new HtmlString(
                                        '<div class="rf-cfg-resumen rf-cfg-resumen-falta">'
                                        . 'Falta tu tarifa. Sin ella no se pueden registrar cobros ni calcular adeudos.'
                                        . '</div>'
                                    );
                                }

                                $alMes = round(30 / $dias * $precio);

                                return new HtmlString(
                                    '<div class="rf-cfg-resumen">'
                                    . '<strong>Cobras $' . number_format($precio, 2) . ' cada ' . $dias . ' días.</strong>'
                                    . ' Son alrededor de $' . number_format($alMes, 2) . ' al mes por equipo rentado.'
                                    . '</div>'
                                );
                            }),
                    ])
                    ->columns(2),

                // Arranca en cero: sin configurarlo, el adeudo se calcula igual
                // que siempre. Un recargo que aparece solo, sin que el dueño lo
                // haya decidido, le rompe la relación con sus clientes.
                Section::make('Recargo por atraso')
                    ->description('Opcional. Déjalo en cero y nada cambia: los adeudos se calculan como hasta hoy.')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->iconColor('warning')
                    ->collapsed(fn () => ! ($this->tenant->settings?->chargesLateFee() ?? false))
                    ->collapsible()
                    ->schema([
                        TextInput::make('late_fee_amount')
                            ->label('Cuánto')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->required()
                            ->live(onBlur: true)
                            ->prefix(fn (Get $get) => $get('late_fee_type') === 'porcentaje' ? null : '$')
                            ->suffix(fn (Get $get) => $get('late_fee_type') === 'porcentaje' ? '%' : 'MXN'),

                        Select::make('late_fee_type')
                            ->label('Cómo se calcula')
                            ->options(Setting::LATE_FEE_TYPES)
                            ->default('fijo')
                            ->native(false)
                            ->live()
                            ->required(),

                        TextInput::make('late_fee_grace_days')
                            ->label('Días de gracia')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->suffix('días')
                            ->required()
                            ->live(onBlur: true)
                            ->helperText('Quien se atrase menos de estos días no lleva recargo.'),

                        // Un ejemplo con números reales: un recargo mal calibrado
                        // espanta clientes, y en abstracto no se nota.
                        Placeholder::make('ejemplo_recargo')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->content(fn (Get $get) => new HtmlString($this->ejemploDeRecargo($get))),
                    ])
                    ->columns(3),
            ])
            ->statePath('data');
    }

    /**
     * Qué pagaría un cliente con dos periodos de atraso, con los números que el
     * dueño acaba de escribir.
     */
    private function ejemploDeRecargo(Get $get): string
    {
        $monto = (float) ($get('late_fee_amount') ?? 0);

        if ($monto <= 0) {
            return '<div class="rf-cfg-resumen rf-cfg-resumen-neutro">'
                . 'Con el recargo en cero, atrasarse no cuesta nada. Es como funciona hoy.'
                . '</div>';
        }

        $precio = (float) ($get('price') ?? 0);
        $dias = (int) ($get('days_per_payment') ?? 0);
        $gracia = (int) ($get('late_fee_grace_days') ?? 0);

        if ($precio <= 0 || $dias <= 0) {
            return '<div class="rf-cfg-resumen rf-cfg-resumen-falta">'
                . 'Configura primero tu tarifa para ver cómo quedaría el recargo.'
                . '</div>';
        }

        $recargo = $get('late_fee_type') === 'porcentaje'
            ? 2 * $precio * ($monto / 100)
            : 2 * $monto;

        $renta = 2 * $precio;

        $nota = $gracia > 0
            ? ' Si se atrasa ' . $gracia . ' días o menos, no lleva recargo.'
            : '';

        return '<div class="rf-cfg-resumen rf-cfg-resumen-aviso">'
            . '<strong>Ejemplo:</strong> un cliente con 2 periodos vencidos debería $'
            . number_format($renta, 2) . ' de renta más $' . number_format($recargo, 2)
            . ' de recargo. Total: <strong>$' . number_format($renta + $recargo, 2) . '</strong>.'
            . $nota
            . '</div>';
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
