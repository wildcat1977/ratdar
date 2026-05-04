<?php

namespace App\Filament\Resources\Contacts\Pages;

use App\Filament\Resources\Contacts\ContactResource;
use App\Models\Contact;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListContacts extends ListRecords
{
    protected static string $resource = ContactResource::class;

    public function getTabs(): array
    {
        return [
            'all'     => Tab::make('全部'),
            'unread'  => Tab::make('未讀')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Contact::STATUS_UNREAD)),
            'replied' => Tab::make('已回覆')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Contact::STATUS_REPLIED)),
        ];
    }
}
