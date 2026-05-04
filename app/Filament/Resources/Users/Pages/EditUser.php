<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
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

        // Always restore the original is_admin value for protected accounts,
        // regardless of what was submitted in the form data.
        if (in_array($record->email, User::PROTECTED_ADMIN_EMAILS)) {
            $this->data['is_admin'] = $record->is_admin;
        }
    }
}
