<?php

namespace App\Filament\Resources\Contacts\Schemas;

use App\Models\Contact;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ContactForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('姓名')
                ->disabled(),

            TextInput::make('email')
                ->label('Email')
                ->disabled(),

            TextInput::make('subject')
                ->label('主旨')
                ->disabled()
                ->columnSpanFull(),

            Textarea::make('message')
                ->label('訊息內容')
                ->rows(6)
                ->disabled()
                ->columnSpanFull(),

            Select::make('status')
                ->label('狀態')
                ->options([
                    Contact::STATUS_UNREAD  => '未讀',
                    Contact::STATUS_READ    => '已讀',
                    Contact::STATUS_REPLIED => '已回覆',
                ])
                ->required(),

            Textarea::make('reply')
                ->label('回覆內容（備忘）')
                ->rows(4)
                ->placeholder('記錄回覆內容…')
                ->columnSpanFull(),

            DateTimePicker::make('replied_at')
                ->label('回覆時間'),
        ]);
    }
}
