<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all'     => Tab::make('全部'),
            'admin'   => Tab::make('管理員')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('is_admin', true)),
            'blocked' => Tab::make('封禁')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('is_banned', true)),
        ];
    }
}
