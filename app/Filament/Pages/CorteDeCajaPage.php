<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\CorteDeCaja;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;

/**
 * El cierre del día: cuánto se cobró, cuánto de eso es efectivo y si cuadra.
 *
 * El proceso que todos los rentadores hacen todas las tardes y que hasta ahora
 * tocaba hacer en papel.
 */
class CorteDeCajaPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Corte de caja';

    protected static ?string $title = 'Corte de caja';

    protected static ?string $navigationGroup = 'Finanzas';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'corte-de-caja';

    protected static string $view = 'filament.pages.corte-de-caja';

    public ?string $fecha = null;

    public ?int $cobradorId = null;

    public function mount(): void
    {
        $this->fecha = today()->toDateString();
        $this->cobradorId = auth()->id();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            DatePicker::make('fecha')
                ->label('Día del corte')
                ->native(false)
                ->maxDate(today())
                ->live()
                ->required(),

            Select::make('cobradorId')
                ->label('De quién')
                ->options($this->cobradores())
                ->live()
                ->helperText('El corte se cierra por persona: cada quien cuadra lo que trae.')
                ->required(),
        ])->columns(2);
    }

    /** @return array<int, string> */
    private function cobradores(): array
    {
        return Filament::getTenant()
            ->members()
            ->orderBy('name')
            ->pluck('name', 'users.id')
            ->all();
    }

    public function corte(): CorteDeCaja
    {
        return CorteDeCaja::para(
            Filament::getTenant(),
            Carbon::parse($this->fecha ?? today()),
            User::find($this->cobradorId),
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cerrar')
                ->label(fn () => $this->corte()->estaCerrado() ? 'Corregir el corte' : 'Cerrar el día')
                ->icon('heroicon-o-lock-closed')
                ->color(fn () => $this->corte()->estaCerrado() ? 'gray' : 'primary')
                ->disabled(fn () => $this->corte()->cuantos() === 0)
                ->modalHeading('Cerrar el corte del día')
                ->modalDescription('Cuenta el efectivo que traes y anótalo. Si no cuadra, queda registrada la diferencia.')
                ->form([
                    Placeholder::make('esperado')
                        ->label('Deberías traer')
                        ->content(fn () => new HtmlString(
                            '<span style="font-size:1.6rem;font-weight:800;color:#0f172a">$'
                            . number_format($this->corte()->efectivo(), 2)
                            . '</span>'
                        )),
                    TextInput::make('contado')
                        ->label('Cuánto contaste')
                        ->numeric()
                        ->minValue(0)
                        ->step('0.01')
                        ->required()
                        ->default(fn () => $this->corte()->efectivo()),
                    Textarea::make('notas')
                        ->label('Notas')
                        ->placeholder('Por ejemplo: le di cambio de más a un cliente.')
                        ->rows(2),
                ])
                ->action(function (array $data) {
                    $corte = $this->corte();

                    $cierre = $corte->cerrar(
                        User::findOrFail($this->cobradorId),
                        (float) $data['contado'],
                        $data['notas'] ?? null,
                    );

                    $diferencia = (float) $cierre->difference;

                    $aviso = Notification::make()->title('Corte cerrado');

                    if ($diferencia === 0.0) {
                        $aviso->body('Cuadró exacto.')->success();
                    } elseif ($diferencia < 0) {
                        $aviso->body('Faltan $' . number_format(abs($diferencia), 2) . '. Quedó anotado.')->warning();
                    } else {
                        $aviso->body('Sobran $' . number_format($diferencia, 2) . '. Quedó anotado.')->warning();
                    }

                    $aviso->send();
                }),
        ];
    }

    public function getSubheading(): ?string
    {
        return 'Cierra el día: cuánto entró, cuánto de eso es efectivo que traes encima, y si cuadra con lo que contaste.';
    }
}
