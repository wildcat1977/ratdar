<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    /**
     * 將使用者導向 OAuth 提供者
     */
    public function redirect(string $provider): RedirectResponse
    {
        return Socialite::driver($provider)->redirect();
    }

    /**
     * 後台專用：標記 session 後導向 Google OAuth
     */
    public function adminRedirect(): RedirectResponse
    {
        session(['admin_oauth' => true]);

        return Socialite::driver('google')->redirect();
    }

    /**
     * 處理 OAuth 回呼，建立或登入使用者，再導回首頁並請前端開啟回報表單
     */
    public function callback(string $provider): RedirectResponse
    {
        try {
            $oauthUser = Socialite::driver($provider)->user();
        } catch (\Throwable $e) {
            if (session()->pull('admin_oauth')) {
                return redirect('/admin/login')->withErrors(['email' => '登入失敗，請再試一次']);
            }

            return redirect()->route('home')->with('auth_error', '登入失敗，請再試一次');
        }

        $user = User::where('provider', $provider)
            ->where('provider_id', $oauthUser->getId())
            ->first();

        if (! $user && $oauthUser->getEmail()) {
            $user = User::where('email', $oauthUser->getEmail())->first();
        }

        if ($user) {
            $user->fill([
                'provider' => $provider,
                'provider_id' => $oauthUser->getId(),
                'avatar' => $oauthUser->getAvatar(),
            ])->save();
        } else {
            $user = User::create([
                'name' => $oauthUser->getName() ?: ($oauthUser->getNickname() ?: '匿名捕鼠人'),
                'email' => $oauthUser->getEmail() ?: $provider.'_'.$oauthUser->getId().'@ratdar.local',
                'provider' => $provider,
                'provider_id' => $oauthUser->getId(),
                'avatar' => $oauthUser->getAvatar(),
            ]);
        }

        // 後台 OAuth 流程
        if (session()->pull('admin_oauth')) {
            if (! $user->is_admin) {
                return redirect('/admin/login')->withErrors(['email' => '此帳號沒有後台管理權限。']);
            }

            Auth::login($user, remember: true);

            return redirect('/admin');
        }

        Auth::login($user, remember: true);

        return redirect()->route('home')->with('open_report_form', true);
    }

    /**
     * LIFF 內建瀏覽器專用：用 access token 向 LINE API 取得使用者資料，再車回一般登入流程
     */
    public function liffCallback(): RedirectResponse
    {
        $token    = request()->string('access_token')->toString();
        $redirect = request()->string('redirect', '/')->toString();

        if (empty($token)) {
            return redirect($redirect)->with('auth_error', 'LIFF 登入失敗，請再試一次');
        }

        // 向 LINE Profile API 取得使用者基本資料
        try {
            $resp = (new \GuzzleHttp\Client())->get('https://api.line.me/v2/profile', [
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'timeout' => 8,
            ]);
            $profile = json_decode((string) $resp->getBody(), true);
        } catch (\Throwable) {
            return redirect($redirect)->with('auth_error', 'LIFF 登入失敗，請再試一次');
        }

        $providerId = $profile['userId'] ?? null;
        if (! $providerId) {
            return redirect($redirect)->with('auth_error', 'LIFF 登入失敗，請再試一次');
        }

        $user = User::where('provider', 'line')->where('provider_id', $providerId)->first()
            ?? User::create([
                'name'        => $profile['displayName'] ?? '匿名捕鼠人',
                'email'       => 'line_' . $providerId . '@ratdar.local',
                'provider'    => 'line',
                'provider_id' => $providerId,
                'avatar'      => $profile['pictureUrl'] ?? null,
            ]);

        // 更新頭像
        if (isset($profile['pictureUrl'])) {
            $user->avatar = $profile['pictureUrl'];
            $user->save();
        }

        Auth::login($user, remember: true);

        // 加上 liff_authed flag 防止前端御牧迴賦
        $separator = str_contains($redirect, '?') ? '&' : '?';
        return redirect($redirect . $separator . 'liff_authed=1');
    }
}
