<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @php
        $ogTitle = $user->name . ' ' . __('的捕鼠成就 · Rat Radar');
        $ogDesc  = __(':name 在 Rat Radar 已累積 :count 件核准通報，目前排名第 :rank 名！', ['name' => $user->name, 'count' => $approvedCount, 'rank' => $rank]);
        $ogImage = route('share.image', $user);
        $ogUrl   = route('share.show', $user);
    @endphp

    <title>{{ $ogTitle }}</title>
    <meta name="description" content="{{ $ogDesc }}">
    <link rel="canonical" href="{{ $ogUrl }}">

    <meta property="og:title"       content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDesc }}">
    <meta property="og:image"       content="{{ $ogImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:type"        content="profile">
    <meta property="og:url"         content="{{ $ogUrl }}">

    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="{{ $ogTitle }}">
    <meta name="twitter:description" content="{{ $ogDesc }}">
    <meta name="twitter:image"       content="{{ $ogImage }}">

    <meta name="theme-color" content="#0d1117">

    @include('partials.gtag')

    @vite(['resources/css/app.css'])
</head>
<body class="min-h-dvh bg-[#0d1117] text-slate-100 antialiased flex flex-col items-center justify-center px-4 py-12">

    {{-- 成就卡 --}}
    <div class="w-full max-w-sm rounded-3xl bg-gradient-to-br from-[#1a1f2e] to-[#0d1117]
                ring-1 ring-white/10 shadow-2xl overflow-hidden">

        {{-- 頂部紅色橫條 --}}
        <div class="h-1.5 w-full bg-gradient-to-r from-red-600 via-red-500 to-orange-500"></div>

        <div class="px-8 pt-8 pb-6">

            {{-- 品牌 --}}
            <p class="text-xs font-semibold tracking-widest text-slate-500 uppercase">Rat Radar</p>

            {{-- 頭像 + 名稱 --}}
            <div class="mt-5 flex items-center gap-4">
                @if ($user->avatar)
                    <img src="{{ $user->avatar }}" alt="{{ $user->name }}"
                         class="h-14 w-14 rounded-full object-cover ring-2 ring-red-500/50">
                @else
                    <div class="flex h-14 w-14 items-center justify-center rounded-full
                                bg-red-600/20 text-2xl ring-2 ring-red-500/30">👤</div>
                @endif
                <div>
                    <p class="text-lg font-bold text-slate-100">{{ $user->name }}</p>
                    <p class="text-xs text-slate-500">{{ __('捕鼠英雄') }}</p>
                </div>
            </div>

            {{-- 主數字 --}}
            <div class="mt-8 flex items-end gap-3">
                <span class="text-7xl font-black leading-none text-red-500">{{ $approvedCount }}</span>
                <span class="mb-2 text-base text-slate-400">{{ __('件核准通報') }}</span>
            </div>

            {{-- 副數據 --}}
            <div class="mt-4 grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-white/5 px-4 py-3">
                    <p class="text-xs text-slate-500">{{ __('總回報數') }}</p>
                    <p class="mt-1 text-xl font-bold text-slate-200">{{ $totalCount }}</p>
                </div>
                <div class="rounded-xl bg-white/5 px-4 py-3">
                    <p class="text-xs text-slate-500">{{ __('目前排名') }}</p>
                    <p class="mt-1 text-xl font-bold
                        {{ $rank <= 3 ? 'text-yellow-400' : 'text-slate-200' }}">
                        @if ($rank === 1) 🥇 @elseif ($rank === 2) 🥈 @elseif ($rank === 3) 🥉 @endif
                        {{ __('第 :rank 名', ['rank' => $rank]) }}
                    </p>
                </div>
            </div>

        </div>

        {{-- 底部 CTA --}}
        <div class="border-t border-white/10 px-8 py-5 flex flex-col gap-3">
            <a href="{{ route('leaderboard') }}"
               class="block w-full rounded-xl bg-red-600 py-3 text-center text-sm font-semibold
                      text-white hover:bg-red-500 transition-colors">
                {{ __('查看英雄榜') }}
            </a>
            <a href="{{ route('home') }}"
               class="block w-full rounded-xl bg-white/5 py-3 text-center text-sm text-slate-400
                      hover:bg-white/10 transition-colors ring-1 ring-white/10">
                {{ __('前往通報老鼠') }}
            </a>
        </div>
    </div>

    {{-- 下方 OG 預覽圖說明 --}}
    <div class="mt-6 text-center text-xs text-slate-600">
        <p>{{ __('分享此頁面將自動產生成就卡預覽圖') }}</p>
        <a href="{{ route('share.image', $user) }}"
           target="_blank"
           class="mt-1 inline-block underline underline-offset-2 hover:text-slate-400">
            {{ __('查看成就卡圖片') }}
        </a>
    </div>

</body>
</html>
