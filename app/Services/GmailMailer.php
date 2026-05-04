<?php

namespace App\Services;

use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;

class GmailMailer
{
    private string $tokenPath;

    public function __construct()
    {
        $this->tokenPath = storage_path('app/gmail_token.json');
    }

    /** 建立 Google Client（未授權） */
    public function makeClient(): Client
    {
        $client = new Client();
        $client->setClientId(config('services.gmail.client_id'));
        $client->setClientSecret(config('services.gmail.client_secret'));
        $client->setRedirectUri(url('/admin/gmail/callback'));
        $client->setScopes([Gmail::GMAIL_SEND]);
        $client->setAccessType('offline');
        $client->setPrompt('consent');   // 每次都要求 refresh_token

        return $client;
    }

    /** 是否已完成 OAuth2 授權 */
    public function isAuthorized(): bool
    {
        if (! file_exists($this->tokenPath)) {
            return false;
        }
        $token = json_decode(file_get_contents($this->tokenPath), true);
        return ! empty($token['refresh_token']);
    }

    /** 取得已授權且 token 有效的 Client */
    private function authorizedClient(): Client
    {
        $client  = $this->makeClient();
        $stored  = json_decode(file_get_contents($this->tokenPath), true);
        $client->setAccessToken($stored);

        if ($client->isAccessTokenExpired()) {
            $fresh  = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            $merged = array_merge($stored, $fresh);          // 保留 refresh_token
            file_put_contents($this->tokenPath, json_encode($merged));
            $client->setAccessToken($merged);
        }

        return $client;
    }

    /**
     * 傳送 HTML 郵件
     *
     * @throws \Google\Service\Exception
     */
    public function send(string $toEmail, string $toName, string $subject, string $htmlBody): void
    {
        $client  = $this->authorizedClient();
        $service = new Gmail($client);

        $fromEmail = config('services.gmail.from_email');
        $fromName  = config('app.name');

        // 建立 RFC 2822 格式訊息（主旨/寄件人/收件人以 UTF-8 Base64 編碼）
        $encName    = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $encTo      = '=?UTF-8?B?' . base64_encode($toName) . '?=';
        $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $raw = implode("\r\n", [
            "From: {$encName} <{$fromEmail}>",
            "To: {$encTo} <{$toEmail}>",
            "Subject: {$encSubject}",
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($htmlBody), 76, "\r\n"),
        ]);

        $encoded = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');

        $message = new Message();
        $message->setRaw($encoded);

        $service->users_messages->send('me', $message);
    }
}
