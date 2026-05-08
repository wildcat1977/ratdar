@extends('layouts.app')

@section('title', __('捕鼠英雄榜 · Rat Radar'))

@section('content')
<div class="min-h-dvh bg-[#0d1117]">
    {{-- 頂部導覽列 --}}
    <header class="flex items-center justify-between border-b border-white/10 px-4 py-3">
        <a href="{{ route('home') }}" class="flex items-center gap-2 text-sm font-semibold text-slate-200">
            <span class="text-red-500">◎</span> Rat Radar
        </a>
        <div class="flex items-center gap-3">
            @include('partials.locale-switcher')
            @auth
                <a href="{{ route('profile') }}" class="text-xs text-slate-400 hover:text-slate-200">{{ __('我的回報') }}</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="rounded-full bg-white/10 px-3 py-1.5 text-xs text-slate-300 hover:bg-white/20">{{ __('登出') }}</button>
                </form>
            @endauth
        </div>
    </header>

    <livewire:leaderboard />
</div>
@endsection
