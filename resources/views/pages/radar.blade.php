@extends('layouts.app')

@section('content')
<div class="relative min-h-dvh">
    {{-- 全螢幕地圖 --}}
    <livewire:radar-map />

    {{-- 戰術網格疊層（純 CSS，不干擾地圖互動）--}}
    <div class="pointer-events-none fixed inset-0 z-[1] radar-tactical-grid"></div>

    {{-- 懸浮 UI --}}
    <div class="pointer-events-none fixed inset-0 z-[500] flex flex-col">
        {{-- 頂部：周邊統計 --}}
        <header class="pointer-events-auto flex items-center justify-between p-4">
            <div class="flex items-center gap-2 rounded-full bg-black/60 px-3 py-2 backdrop-blur">
                <span class="inline-block h-2 w-2 animate-pulse rounded-full bg-red-500"></span>
                <span class="text-xs font-semibold text-slate-100">
                    周邊 {{ config('radar.nearby_radius_km') }} km：
                    <span id="nearby-count" class="text-red-400">--</span> 筆通報
                </span>
            </div>

            @auth
                <div class="flex items-center gap-2">
                    <a href="{{ route('profile') }}"
                       class="flex items-center gap-2 rounded-full bg-black/60 px-3 py-2 backdrop-blur hover:bg-black/80">
                        @if (auth()->user()->avatar)
                            <img src="{{ auth()->user()->avatar }}" alt="" class="h-5 w-5 rounded-full object-cover">
                        @endif
                        <span class="text-xs text-slate-200">{{ auth()->user()->name }}</span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="rounded-full bg-black/60 px-2 py-2 text-xs text-slate-400 backdrop-blur hover:text-slate-200">登出</button>
                    </form>
                </div>
            @endauth
        </header>

        <div class="flex-1"></div>

        {{-- 底部：立即回報 + Footer --}}
        <div class="pointer-events-auto flex flex-col gap-3 p-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
            {{-- 跑馬燈廣播 --}}
            <div class="mx-auto w-full max-w-md overflow-hidden rounded-full bg-black/50 px-4 py-1.5 backdrop-blur">
                <p id="radar-ticker-text"
                   class="radar-ticker truncate text-center text-[11px] font-medium text-slate-400">
                </p>
            </div>

            <button type="button"
                    onclick="window.dispatchEvent(new CustomEvent('mouseradar:report-clicked'))"
                    class="mx-auto flex w-full max-w-md items-center justify-center gap-2 rounded-2xl bg-red-600 px-6 py-4 text-base font-bold text-white shadow-[0_0_30px_rgba(239,68,68,0.6)] transition active:scale-95 hover:bg-red-500">
                <span class="text-xl">⚠️</span>
                <span>立即回報</span>
            </button>

            <footer class="mx-auto flex w-full max-w-md items-center justify-between text-[11px] text-slate-500">
                <a href="{{ route('leaderboard') }}" class="hover:text-slate-300">回報榜</a>
                <a href="{{ route('reports') }}" class="hover:text-slate-300">通報清單</a>
                @auth
                    <a href="{{ route('profile') }}" class="hover:text-slate-300">我的回報</a>
                @else
                    <span class="text-slate-700">© {{ date('Y') }} Rat Radar</span>
                @endauth
                <button type="button"
                        onclick="window.dispatchEvent(new CustomEvent('open-contact-form'))"
                        class="hover:text-slate-300">聯絡管理員</button>
            </footer>
        </div>
    </div>

    {{-- 登入引導 / 回報表單 (Livewire Modal) --}}
    <livewire:auth-onboarding />
    <livewire:report-form />
    <livewire:contact-form />
</div>

@if (session('open_report_form'))
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.dispatch('open-report-form');
        });
    </script>
@endif
@endsection
