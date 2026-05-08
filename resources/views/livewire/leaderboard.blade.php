<?php

use App\Models\Report;
use App\Models\User;
use Livewire\Component;

new class extends Component
{
    public function getLeaderboardProperty(): \Illuminate\Support\Collection
    {
        return User::query()
            ->withCount(['reports as approved_count' => fn ($q) => $q->where('status', Report::STATUS_APPROVED)])
            ->orderByDesc('approved_count')
            ->limit(20)
            ->get()
            ->filter(fn ($u) => $u->approved_count > 0)
            ->values();
    }

    public function getTotalApprovedProperty(): int
    {
        return Report::where('status', Report::STATUS_APPROVED)->count();
    }
}; ?>

<div class="mx-auto max-w-2xl px-4 py-10">

    {{-- 標題區 --}}
    <div class="mb-8 text-center">
        <div class="inline-flex items-center justify-center rounded-full bg-red-600/20 p-4 text-4xl">🏆</div>
        <h1 class="mt-3 text-2xl font-bold">{{ __('捕鼠英雄榜') }}</h1>
        <p class="mt-1 text-sm text-slate-400">
            {{ __('全平台已核准通報 :count 筆', ['count' => $this->totalApproved]) }}
        </p>
    </div>

    {{-- 前三名大卡片 --}}
    @php $top3 = $this->leaderboard->take(3); @endphp
    @if ($top3->count())
        <div class="mb-6 grid grid-cols-3 items-end gap-3">
            @foreach ([1, 0, 2] as $idx)   {{-- 順序：2nd, 1st, 3rd --}}
                @php $user = $top3->get($idx); @endphp
                @if ($user)
                    @php
                        $rank   = $idx + 1;
                        $medal  = match($rank) { 1 => '🥇', 2 => '🥈', 3 => '🥉' };
                        $height = match($rank) { 1 => 'pt-0', 2 => 'pt-6', 3 => 'pt-10' };
                    @endphp
                    <div class="flex flex-col items-center {{ $height }}">
                        <span class="text-2xl">{{ $medal }}</span>
                        @if ($user->avatar)
                            <img src="{{ $user->avatar }}" alt="{{ $user->name }}"
                                 class="mt-1 h-12 w-12 rounded-full object-cover ring-2 {{ $rank === 1 ? 'ring-yellow-400' : 'ring-white/20' }}">
                        @else
                            <div class="mt-1 flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-xl ring-2 {{ $rank === 1 ? 'ring-yellow-400' : 'ring-white/20' }}">👤</div>
                        @endif
                        <p class="mt-2 max-w-full truncate px-1 text-center text-xs font-semibold text-slate-200">{{ $user->name }}</p>
                        <p class="text-[11px] text-red-400 font-bold">{{ $user->approved_count }} {{ __('筆') }}</p>
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    {{-- 完整排行榜列表 --}}
    <div class="space-y-2">
        @foreach ($this->leaderboard as $i => $user)
            <div class="flex items-center gap-4 rounded-xl px-4 py-3
                        {{ $i === 0 ? 'bg-yellow-500/10 ring-1 ring-yellow-500/30' : 'bg-white/5' }}">
                <span class="w-6 text-center text-sm font-bold {{ $i < 3 ? 'text-yellow-400' : 'text-slate-500' }}">
                    {{ $i + 1 }}
                </span>

                @if ($user->avatar)
                    <img src="{{ $user->avatar }}" alt="{{ $user->name }}"
                         class="h-9 w-9 rounded-full object-cover">
                @else
                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-base">👤</div>
                @endif

                <span class="flex-1 truncate text-sm font-medium text-slate-200">{{ $user->name }}</span>

                <span class="text-sm font-bold text-red-400">{{ $user->approved_count }}</span>
                <span class="text-xs text-slate-600">{{ __('筆') }}</span>
            </div>
        @endforeach

        @if ($this->leaderboard->isEmpty())
            <div class="rounded-2xl bg-white/5 p-8 text-center text-slate-500">
                {{ __('還沒有核准的通報，成為第一名吧！') }}
            </div>
        @endif
    </div>
</div>
