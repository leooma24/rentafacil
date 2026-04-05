<?php

namespace App\Filament\Client\Resources\TicketResource\Pages;

use App\Filament\Client\Resources\TicketResource;
use App\Notifications\NewTicketNotification;
use Filament\Resources\Pages\CreateRecord;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['status'] = 'abierta';

        $companyIds = auth()->user()->companies()->pluck('companies.id');
        $data['company_id'] = $companyIds->first();

        return $data;
    }

    protected function afterCreate(): void
    {
        $incident = $this->record;
        $incident->load('company.members');

        $incident->company->members->each(fn ($member) =>
            $member->notify(new NewTicketNotification($incident))
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
