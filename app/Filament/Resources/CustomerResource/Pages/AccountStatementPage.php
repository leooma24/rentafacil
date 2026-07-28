<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Models\Payment;
use App\Support\AccountStatement;
use App\Support\Statement;
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
