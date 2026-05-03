<?php

namespace App\Filament\Pages\Auth;

use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;

class Login extends BaseLogin
{
    protected function getFormActions(): array
    {
        return [
            $this->getAuthenticateFormAction(),
            $this->getGoogleLoginAction(),
        ];
    }

    protected function getGoogleLoginAction(): Action
    {
        return Action::make('google')
            ->label('使用 Google 帳號登入')
            ->color('gray')
            ->icon('heroicon-o-arrow-right-start-on-rectangle')
            ->url(route('admin.auth.google.redirect'));
    }
}
