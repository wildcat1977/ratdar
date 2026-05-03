<?php

namespace App\Filament\Resources\Contacts\Tables;

use App\Filament\Resources\Contacts\ContactResource;
use App\Models\Contact;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContactsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                BadgeColumn::make('status')
                    ->label('狀態')
                    ->colors([
                        'danger'   => Contact::STATUS_UNREAD,
                        'warning'  => Contact::STATUS_READ,
                        'success'  => Contact::STATUS_REPLIED,
                    ])
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        Contact::STATUS_UNREAD  => '未讀',
                        Contact::STATUS_READ    => '已讀',
                        Contact::STATUS_REPLIED => '已回覆',
                        default => $state,
                    }),

                TextColumn::make('name')
                    ->label('姓名')
                    ->searchable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('subject')
                    ->label('主旨')
                    ->limit(40)
                    ->searchable(),

                TextColumn::make('message')
                    ->label('訊息')
                    ->limit(50),

                TextColumn::make('user.name')
                    ->label('會員')
                    ->default('訪客'),

                TextColumn::make('created_at')
                    ->label('送出時間')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('狀態')
                    ->options([
                        Contact::STATUS_UNREAD  => '未讀',
                        Contact::STATUS_READ    => '已讀',
                        Contact::STATUS_REPLIED => '已回覆',
                    ]),
            ])
            ->actions([
                Action::make('view')
                    ->label('查看/回覆')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Contact $record) => ContactResource::getUrl('view', ['record' => $record])),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

