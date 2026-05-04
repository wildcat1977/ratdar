<?php

namespace App\Filament\Resources\Contacts\Pages;

use App\Filament\Resources\Contacts\ContactResource;
use App\Models\Contact;
use App\Services\GmailMailer;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\View;

class ViewContact extends EditRecord
{
    protected static string $resource = ContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendReply')
                ->label('發送回覆郵件')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('確認寄出回覆郵件')
                ->modalDescription(fn () => '將回覆內容寄送至 ' . $this->getRecord()->email)
                ->modalSubmitActionLabel('確認寄出')
                ->action(function () {
                    $contact = $this->getRecord()->fresh();

                    if (empty($contact->reply)) {
                        Notification::make()
                            ->title('請先填寫並儲存回覆內容')
                            ->body('請在「回覆內容」欄位填寫內容，按下「保存」後再寄出郵件。')
                            ->warning()
                            ->send();
                        return;
                    }

                    /** @var GmailMailer $mailer */
                    $mailer = app(GmailMailer::class);

                    if (! $mailer->isAuthorized()) {
                        Notification::make()
                            ->title('尚未授權 Gmail')
                            ->body('請前往 /admin/gmail/authorize 完成一次性授權後再試。')
                            ->danger()
                            ->send();
                        return;
                    }

                    try {
                        $html = View::make('mail.contact-reply', ['contact' => $contact])->render();
                        $mailer->send(
                            $contact->email,
                            $contact->name,
                            'Re: ' . $contact->subject,
                            $html,
                        );
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('郵件寄送失敗')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                        return;
                    }

                    $contact->update([
                        'status'     => Contact::STATUS_REPLIED,
                        'replied_at' => now(),
                    ]);

                    Notification::make()
                        ->title('回覆郵件已寄出')
                        ->body('已成功寄送至 ' . $contact->email)
                        ->success()
                        ->send();
                }),

            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // 儲存時若 reply 有填內容，自動設為已回覆（不論目前狀態）
        if (!empty($data['reply'])) {
            if ($data['status'] !== Contact::STATUS_REPLIED) {
                $data['status'] = Contact::STATUS_REPLIED;
            }
            if (empty($data['replied_at'])) {
                $data['replied_at'] = now();
            }
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
