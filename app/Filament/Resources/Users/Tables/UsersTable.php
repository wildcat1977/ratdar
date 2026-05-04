<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
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
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

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
                Action::make('grant_admin')
                    ->label('授予管理員')
                    ->icon('heroicon-o-shield-check')
                    ->color('warning')
                    ->visible(fn ($record) => ! $record->is_admin)
                    ->requiresConfirmation()
                    ->modalHeading('授予管理員權限')
                    ->modalDescription(fn ($record) => "確定要授予「{$record->name}」管理員權限？授予後該帳號可登入後台並審核回報。")
                    ->modalSubmitActionLabel('確認授予')
                    ->action(fn ($record) => $record->update(['is_admin' => true])),
                Action::make('revoke_admin')
                    ->label('撤銷管理員')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('danger')
                    ->visible(fn ($record) => $record->is_admin && ! in_array($record->email, User::PROTECTED_ADMIN_EMAILS))
                    ->requiresConfirmation()
                    ->modalHeading('撤銷管理員權限')
                    ->modalDescription(fn ($record) => "確定要撤銷「{$record->name}」的管理員權限？撤銷後該帳號將無法登入後台。")
                    ->modalSubmitActionLabel('確認撤銷')
                    ->action(fn ($record) => $record->update(['is_admin' => false])),
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
