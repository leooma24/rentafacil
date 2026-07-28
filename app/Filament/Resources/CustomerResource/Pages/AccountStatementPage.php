<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Models\Payment;
use App\Support\AccountStatement;
use App\Support\ShareableLinks;
use App\Support\Statement;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

class AccountStatementPage extends Page
{
    use InteractsWithRecord;

    protected static string $resource = CustomerResource::class;

    protected static string $view = 'filament.resources.customer-resource.pages.account-statement';

    protected static ?string $title = 'Estado de cuenta';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    /**
     * El cliente abre su estado de cuenta sin tener cuenta: la liga va firmada
     * y caduca a los 30 días, porque el saldo cambia.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('mandar_whatsapp')
                ->label('Mandar por WhatsApp')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->color('success')
                ->visible(fn () => filled($this->record->phone))
                ->url(fn () => ShareableLinks::whatsappUrl(
                    $this->record->phone,
                    ShareableLinks::statementMessage($this->record),
                ))
                ->openUrlInNewTab(),
        ];
    }

    public function getStatement(): Statement
    {
        return app(AccountStatement::class)->forCustomer($this->record);
    }

    /** @return Collection<int, Payment> */
    public function getPayments(): Collection
    {
        return Payment::whereIn('rental_id', $this->record->rentals()->pluck('id'))
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get();
    }
}
