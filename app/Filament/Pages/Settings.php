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

    protected static ?string $title = 'Configuración';

    protected static ?string $slug = 'configuracion';

    protected static ?int $navigationSort = 6;

    public ?array $data = [];
    public $tenant = null;

    public function mount() : void {
        $this->tenant = Filament::getTenant();
        $this->form->fill([
            'price' => $this->tenant->settings?->price,
            'days_per_payment' => $this->tenant->settings?->days_per_payment,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Schema fields here
                TextInput::make('price')
                    ->label('Precio por pago')
                    ->required(),
                TextInput::make('days_per_payment')
                    ->label('Días que cubre el pago')
                    ->required(),
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

    public function save() : void {
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
