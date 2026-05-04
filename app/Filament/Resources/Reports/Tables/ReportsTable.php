<?php

namespace App\Filament\Resources\Reports\Tables;

use App\Models\Report;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('回報者')
                    ->default('訪客')
                    ->searchable(),

                BadgeColumn::make('type')
                    ->label('類型')
                    ->colors([
                        'danger'  => Report::TYPE_RAT,
                        'primary' => Report::TYPE_POISON,
                    ])
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        Report::TYPE_RAT    => '🐀 鼠蹤',
                        Report::TYPE_POISON => '☠️ 毒餌',
                        default => $state,
                    }),

                ImageColumn::make('image_path')
                    ->label('照片')
                    ->disk('public')
                    ->height(60)
                    ->width(80)
                    ->url(fn (Report $record) => $record->image_path
                        ? Storage::disk('public')->url($record->image_path)
                        : null)
                    ->openUrlInNewTab(),

                TextColumn::make('description')
                    ->label('描述')
                    ->limit(40)
                    ->default('—'),

                TextColumn::make('latitude')
                    ->label('緯度')
                    ->numeric(7)
                    ->description(fn (Report $record): ?string =>
                        ((float) $record->latitude === Report::DEFAULT_LATITUDE &&
                         (float) $record->longitude === Report::DEFAULT_LONGITUDE)
                            ? '⚠️ 未提供位置授權'
                            : null
                    ),

                TextColumn::make('longitude')
                    ->label('經度')
                    ->numeric(7),

                BadgeColumn::make('status')
                    ->label('狀態')
                    ->colors([
                        'warning' => Report::STATUS_PENDING,
                        'success' => Report::STATUS_APPROVED,
                        'danger'  => Report::STATUS_REJECTED,
                        'warning' => Report::STATUS_REPORTED_1999,
                        'success' => Report::STATUS_RESOLVED,
                    ])
                    ->color(fn (string $state) => match ($state) {
                        Report::STATUS_PENDING       => 'gray',
                        Report::STATUS_APPROVED      => 'danger',
                        Report::STATUS_REPORTED_1999 => 'warning',
                        Report::STATUS_RESOLVED      => 'success',
                        Report::STATUS_REJECTED      => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        Report::STATUS_PENDING       => '待審核',
                        Report::STATUS_APPROVED      => '🔴 已核准（地圖上架）',
                        Report::STATUS_REJECTED      => '已拒絕',
                        Report::STATUS_REPORTED_1999 => '🟡 已通報 1999',
                        Report::STATUS_RESOLVED      => '🟢 已處理完畢',
                        default => $state,
                    }),

                TextColumn::make('created_at')
                    ->label('回報時間')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('類型篩選')
                    ->options([
                        Report::TYPE_RAT    => '🐀 鼠蹤',
                        Report::TYPE_POISON => '☠️ 毒餌',
                    ]),
                SelectFilter::make('status')
                    ->label('狀態篩選')
                    ->options([
                        Report::STATUS_PENDING       => '待審核',
                        Report::STATUS_APPROVED      => '🔴 已核准（地圖上架）',
                        Report::STATUS_REPORTED_1999 => '🟡 已通報 1999',
                        Report::STATUS_RESOLVED      => '🟢 已處理完畢',
                        Report::STATUS_REJECTED      => '已拒絕',
                    ]),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('核准上架')
                    ->color('danger')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (Report $record) => $record->status === Report::STATUS_PENDING)
                    ->action(fn (Report $record) => $record->update(['status' => Report::STATUS_APPROVED])),

                Action::make('report_1999')
                    ->label('標記已通報 1999')
                    ->color('warning')
                    ->icon('heroicon-o-phone')
                    ->visible(fn (Report $record) => $record->status === Report::STATUS_APPROVED)
                    ->action(fn (Report $record) => $record->update(['status' => Report::STATUS_REPORTED_1999])),

                Action::make('resolve')
                    ->label('標記已處理完畢')
                    ->color('success')
                    ->icon('heroicon-o-check-badge')
                    ->visible(fn (Report $record) => $record->status === Report::STATUS_REPORTED_1999)
                    ->action(fn (Report $record) => $record->update(['status' => Report::STATUS_RESOLVED])),

                Action::make('reject')
                    ->label('拒絕')
                    ->color('gray')
                    ->icon('heroicon-o-x-circle')
                    ->visible(fn (Report $record) => $record->status === Report::STATUS_PENDING)
                    ->action(fn (Report $record) => $record->update(['status' => Report::STATUS_REJECTED])),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('ban_users')
                        ->label('批次封鎖使用者')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('批次封鎖使用者')
                        ->modalDescription('將封鎖所選回報的使用者帳號（訪客回報將略過）。')
                        ->form([
                            Textarea::make('ban_reason')
                                ->label('封禁原因')
                                ->required()
                                ->rows(2)
                                ->placeholder('例：亂傳照片、留言騷擾…'),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $userIds = $records
                                ->pluck('user_id')
                                ->filter()
                                ->unique()
                                ->values();

                            if ($userIds->isNotEmpty()) {
                                User::whereIn('id', $userIds)->update([
                                    'is_banned'  => true,
                                    'ban_reason' => $data['ban_reason'],
                                ]);
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
