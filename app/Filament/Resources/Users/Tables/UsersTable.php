<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
            ])
            ->recordActions([
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
