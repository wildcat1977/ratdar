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
        $newIsAdmin = (bool) ($this->data['is_admin'] ?? $record->is_admin);

        if ($newIsAdmin !== (bool) $record->is_admin) {
            // Protected accounts may never have admin revoked
            if (! $newIsAdmin && in_array($record->email, User::PROTECTED_ADMIN_EMAILS)) {
                Notification::make()
                    ->title('無法撤銷管理員權限')
                    ->body('此帳號的管理員權限受到保護，無法被撤銷。')
                    ->danger()
                    ->send();
                $this->halt();
            }

            // An explicit confirmation checkbox must be checked before any admin change
            if (empty($this->data['is_admin_confirm'])) {
                Notification::make()
                    ->title('請確認管理員權限變更')
                    ->body('請勾選「確認變更管理員權限」後再儲存。')
                    ->warning()
                    ->send();
                $this->halt();
            }
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Strip the virtual confirmation field – it must remain dehydrated so
        // beforeSave() can read it, but it has no corresponding database column.
        unset($data['is_admin_confirm']);
        return $data;
    }
}
