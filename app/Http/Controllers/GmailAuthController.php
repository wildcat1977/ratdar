<?php

namespace App\Http\Controllers;

use App\Services\GmailMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GmailAuthController extends Controller
{
    /** 重導至 Google 授權頁（僅管理員可用） */
    public function authorize(GmailMailer $mailer): RedirectResponse
    {
        $authUrl = $mailer->makeClient()->createAuthUrl();
        return redirect()->away($authUrl);
    }

    /** Google OAuth2 callback：儲存 token 後回到後台 */
    public function callback(Request $request, GmailMailer $mailer): RedirectResponse
    {
        $code = $request->query('code');

        if (! $code) {
            return redirect('/admin')->with('error', 'Gmail 授權失敗（未收到授權碼）');
        }

        $token = $mailer->makeClient()->fetchAccessTokenWithAuthCode((string) $code);

        if (isset($token['error'])) {
            return redirect('/admin')->with('error', 'Gmail 授權失敗：' . $token['error_description'] ?? $token['error']);
        }

        if (empty($token['refresh_token'])) {
            // 已授權過但未帶回 refresh_token：刪除舊 token 重新授權
            @unlink(storage_path('app/gmail_token.json'));
            return redirect('/admin/gmail/authorize')
                ->with('error', '未取得 refresh_token，已重置授權，請重新授權一次。');
        }

        file_put_contents(storage_path('app/gmail_token.json'), json_encode($token));

        return redirect('/admin')->with('success', 'Gmail 授權成功！現在可以從後台傳送回覆郵件。');
    }
}
