<?php

namespace App\Filament\Resources\Reports\Schemas;

use App\Models\Report;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class ReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.components.map-picker')
                    ->columnSpanFull(),

                Select::make('type')
                    ->label('類型')
                    ->options([
                        Report::TYPE_RAT => '🐀 鼠蹤',
                        Report::TYPE_POISON => '☠️ 毒餌 / 老鼠藥',
                    ])
                    ->default(Report::TYPE_RAT)
                    ->required(),

                TextInput::make('latitude')
                    ->label('緯度')
                    ->numeric()
                    ->required(),

                TextInput::make('longitude')
                    ->label('經度')
                    ->numeric()
                    ->required(),

                Textarea::make('description')
                    ->label('描述')
                    ->columnSpanFull(),

                Select::make('status')
                    ->label('狀態')
                    ->options([
                        Report::STATUS_PENDING => '待審核',
                        Report::STATUS_APPROVED => '🔴 已核准（地圖上架）',
                        Report::STATUS_REPORTED_1999 => '🟡 已通報 1999',
                        Report::STATUS_RESOLVED => '🟢 已處理完畢',
                        Report::STATUS_REJECTED => '已拒絕',
                    ])
                    ->required(),

                Select::make('rejection_reason')
                    ->label('拒絕原因')
                    ->options(Report::REJECTION_REASONS)
                    ->nullable()
                    ->native(false)
                    ->placeholder('（未填）')
                    ->helperText('僅在拒絕時填寫，使用者將看到此原因'),

                DateTimePicker::make('reviewed_at')
                    ->label('審核時間')
                    ->disabled()
                    ->dehydrated(false)
                    ->displayFormat('Y-m-d H:i'),

                FileUpload::make('image_path')
                    ->label('照片')
                    ->disk('public')
                    ->directory('reports')
                    ->image()
                    ->fetchFileInformation(false)
                    ->columnSpanFull()
                    ->hintAction(
                        Action::make('viewImage')
                            ->label('開新頁查看原圖')
                            ->icon(Heroicon::ArrowTopRightOnSquare)
                            ->url(fn (?Report $record) => $record?->image_path
                                ? Storage::disk('public')->url($record->image_path)
                                : null)
                            ->openUrlInNewTab()
                            ->visible(fn (?Report $record): bool => (bool) $record?->image_path)
                    ),
            ]);
    }
}
