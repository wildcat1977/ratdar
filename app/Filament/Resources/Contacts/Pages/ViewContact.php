<?php

namespace App\Filament\Resources\Contacts\Pages;

use App\Filament\Resources\Contacts\ContactResource;
use App\Models\Contact;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class ViewContact extends EditRecord
{
    protected static string $resource = ContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // 儲存時若狀態為已讀且 reply 有填內容，自動設為已回覆
        if (!empty($data['reply']) && $data['status'] === Contact::STATUS_READ) {
            $data['status'] = Contact::STATUS_REPLIED;
        }
        if (!empty($data['reply']) && empty($data['replied_at'])) {
            $data['replied_at'] = now();
        }
        return $data;
    }

    protected function afterSave(): void
    {
        // 進入編輯頁時自動標為已讀
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // 打開時自動標為已讀（若原本是未讀）
        if ($this->getRecord()->status === Contact::STATUS_UNREAD) {
            $this->getRecord()->update(['status' => Contact::STATUS_READ]);
            $data['status'] = Contact::STATUS_READ;
        }
        return $data;
    }
}
