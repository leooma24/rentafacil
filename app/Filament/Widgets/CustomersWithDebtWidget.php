<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CustomerResource;
use App\Support\AccountStatement;
use App\Support\Statement;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class CustomersWithDebtWidget extends Widget
{
    protected static string $view = 'filament.widgets.customers-with-debt';

    protected static ?int $sort = 6;

    // Sin esto el widget se queda en el placeholder de carga y nunca aparece.
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    /** @return Collection<int, Statement> */
    public function getStatements(): Collection
    {
        return app(AccountStatement::class)
            ->forCompany(Filament::getTenant())
            ->take(5);
    }

    public function statementUrl(Statement $statement): string
    {
        return CustomerResource::getUrl('estado-de-cuenta', ['record' => $statement->customer]);
    }
}
