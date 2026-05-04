<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        $record = $this->getRecord();

        if (in_array($record->email, User::PROTECTED_ADMIN_EMAILS) && isset($this->data['is_admin']) && ! $this->data['is_admin']) {
            Notification::make()
                ->title('無法修改')
                ->body('此帳號的管理員權限受到保護，無法被撤銷。')
                ->danger()
                ->send();

            $this->halt();
        }
    }
}
