<?php

namespace App\Filament\Resources\Users\Tables;

use App\Support\MaskHelper;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('名稱')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (?string $state) => MaskHelper::maskName($state)),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (?string $state) => MaskHelper::maskEmail($state)),

                TextColumn::make('provider')
                    ->label('登入方式')
                    ->default('email')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'google' => 'Google',
                        'line'   => 'LINE',
                        default  => 'Email',
                    }),

                IconColumn::make('is_admin')
                    ->label('管理員')
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-user'),

                IconColumn::make('is_banned')
                    ->label('封禁')
                    ->boolean()
                    ->trueIcon('heroicon-o-no-symbol')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),

                TextColumn::make('ban_reason')
                    ->label('封禁原因')
                    ->limit(30)
                    ->placeholder('—')
                    ->tooltip(fn ($record) => $record->ban_reason),

                TextColumn::make('reports_count')
                    ->label('回報數')
                    ->counts('reports')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('註冊時間')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_admin')
                    ->label('管理員篩選')
                    ->trueLabel('僅管理員')
                    ->falseLabel('僅一般用戶'),
                TernaryFilter::make('is_banned')
                    ->label('封禁篩選')
                    ->trueLabel('僅封禁帳號')
                    ->falseLabel('僅正常帳號'),
            ])
            ->recordActions([
                Action::make('ban')
                    ->label('封禁')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn ($record) => ! $record->is_banned)
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('ban_reason')
                            ->label('封禁原因')
                            ->required()
                            ->rows(2)
                            ->placeholder('例：亂傳照片、留言騷擾…'),
                    ])
                    ->action(fn ($record, array $data) => $record->update([
                        'is_banned'  => true,
                        'ban_reason' => $data['ban_reason'],
                    ])),
                Action::make('unban')
                    ->label('解除封禁')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->is_banned)
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->update([
                        'is_banned'  => false,
                        'ban_reason' => null,
                    ])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
