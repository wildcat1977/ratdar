@php
    $navCls = fn (string $route): string => Route::is($route)
        ? 'text-xs text-slate-200 font-medium'
        : 'text-xs text-slate-400 hover:text-slate-200 transition-colors';
@endphp

{{-- ── 頂部導覽列 ── --}}
<header class="flex items-center justify-between border-b border-white/10 px-4 py-3">
    <a href="{{ route('home') }}" class="flex items-center gap-2 text-sm font-semibold text-slate-200">
        <span class="text-red-500">◎</span> Rat Radar
    </a>

    <div class="flex items-center gap-3">
        @include('partials.locale-switcher')

        <a href="{{ route('reports') }}" class="{{ $navCls('reports') }}">{{ __('通報清單') }}</a>

        <a href="{{ route('leaderboard') }}" class="hidden sm:inline {{ $navCls('leaderboard') }}">{{ __('英雄榜') }}</a>

        <a href="{{ route('transparency') }}" class="{{ $navCls('transparency') }}">{{ __('透明度報告') }}</a>

        <a href="{{ route('stats') }}" class="hidden sm:inline {{ $navCls('stats') }}">{{ __('統計地圖') }}</a>

        <button type="button"
                onclick="window.dispatchEvent(new CustomEvent('open-contact-form'))"
                class="hidden sm:inline text-xs text-slate-400 hover:text-slate-200 transition-colors">{{ __('聯絡管理員') }}</button>

        @auth
            <a href="{{ route('profile') }}" class="{{ $navCls('profile') }}">{{ __('我的回報') }}</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="rounded-full bg-white/10 px-3 py-1.5 text-xs text-slate-300 hover:bg-white/20 transition-colors">{{ __('登出') }}</button>
            </form>
        @else
            <a href="{{ route('auth.redirect', ['provider' => 'google']) }}"
               class="rounded-full bg-white/10 px-3 py-1.5 text-xs text-slate-300 hover:bg-white/20 transition-colors">{{ __('登入') }}</a>
        @endauth
    </div>
</header>

{{-- 聯絡表單 Modal（由 open-contact-form 事件觸發）--}}
<livewire:contact-form />
