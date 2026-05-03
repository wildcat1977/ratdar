<?php

use App\Models\Report;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Validate('required|string|max:50')]
    public string $displayName = '';

    public bool $saved = false;

    public function mount(): void
    {
        $this->displayName = Auth::user()->name;
    }

    public function save(): void
    {
        $this->validate();
        Auth::user()->update(['name' => $this->displayName]);
        $this->saved = true;
    }

    public function getReportsProperty()
    {
        return Auth::user()
            ->reports()
            ->latest()
            ->paginate(9);
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.user-profile', ['reports' => $this->reports]);
    }
}; ?>

<div class="mx-auto max-w-2xl px-4 py-10">

    {{-- 個人資訊卡片 --}}
    <div class="rounded-2xl bg-white/5 p-6 ring-1 ring-white/10">
        <div class="flex items-center gap-5">
            @if (Auth::user()->avatar)
                <img src="{{ Auth::user()->avatar }}"
                     alt="頭像"
                     class="h-16 w-16 rounded-full ring-2 ring-red-500/40 object-cover">
            @else
                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-red-600/20 text-2xl ring-2 ring-red-500/40">
                    👤
                </div>
            @endif

            <div class="flex-1">
                <p class="text-xs text-slate-500">{{ Auth::user()->email }}</p>
                <p class="mt-1 text-sm text-slate-400">
                    已回報 <span class="font-bold text-red-400">{{ Auth::user()->reports()->count() }}</span> 筆
                    ／核准 <span class="font-bold text-green-400">{{ Auth::user()->reports()->where('status', 'approved')->count() }}</span> 筆
                </p>
            </div>
        </div>

        {{-- 暱稱編輯 --}}
        <div class="mt-5 border-t border-white/10 pt-5">
            <label class="block text-xs font-medium text-slate-400">顯示暱稱</label>
            <div class="mt-2 flex gap-3">
                <input wire:model="displayName"
                       type="text"
                       maxlength="50"
                       class="flex-1 rounded-lg border border-white/10 bg-black/30 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-red-500">
                <button wire:click="save"
                        wire:loading.attr="disabled"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500 disabled:opacity-50">
                    <span wire:loading.remove wire:target="save">儲存</span>
                    <span wire:loading wire:target="save">儲存中…</span>
                </button>
            </div>
            @error('displayName')
                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
            @enderror
            @if ($saved)
                <p class="mt-1 text-xs text-green-400" x-data x-init="setTimeout(() => $wire.set('saved', false), 2500)">
                    暱稱已更新！
                </p>
            @endif
        </div>

        {{-- 分享成就卡 --}}
        <div class="mt-5 border-t border-white/10 pt-5"
             x-data="{
                 copied: false,
                 shareUrl: '{{ route('share.show', Auth::id()) }}',
                 doShare() {
                     if (navigator.share) {
                         navigator.share({
                             title: 'Rat Radar 捕鼠成就',
                             text: '我在 Rat Radar 累積通報了老鼠蹤跡，快來看看！',
                             url: this.shareUrl,
                         }).catch(() => {});
                     } else {
                         navigator.clipboard.writeText(this.shareUrl).then(() => {
                             this.copied = true;
                             setTimeout(() => this.copied = false, 2500);
                         });
                     }
                 }
             }">
            <button x-on:click="doShare()"
                    class="flex w-full items-center justify-center gap-2 rounded-xl
                           bg-white/5 py-2.5 text-sm text-slate-300 ring-1 ring-white/10
                           hover:bg-white/10 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M15 8a3 3 0 10-2.977-2.63l-4.94 2.47a3 3 0 100 4.319l4.94 2.47A3 3 0 1015 12a3 3 0 00-2.977-2.63L7.083 6.9a3 3 0 000-1.8l4.94-2.47A3 3 0 0015 8z"/>
                </svg>
                <span x-show="!copied">分享我的成就卡</span>
                <span x-show="copied" x-cloak class="text-green-400">連結已複製！</span>
            </button>
            <p class="mt-1.5 text-center text-[11px] text-slate-600">
                分享後可預覽你的成就勳章圖片
            </p>
        </div>
    </div>

    {{-- 我的回報清單 --}}
    <h2 class="mt-8 text-base font-bold text-slate-200">我的回報紀錄</h2>

    @if ($reports->isEmpty())
        <div class="mt-4 rounded-2xl bg-white/5 p-8 text-center text-slate-500">
            還沒有任何回報，快去發現老鼠吧！
        </div>
    @else
        <div class="mt-4 flex flex-col gap-3">
            @foreach ($reports as $report)
                @php
                    $badge = match($report->status) {
                        'approved' => ['text' => '已核准', 'class' => 'bg-green-500/20 text-green-400'],
                        'rejected' => ['text' => '已拒絕', 'class' => 'bg-red-500/20 text-red-400'],
                        default     => ['text' => '待審核', 'class' => 'bg-yellow-500/20 text-yellow-400'],
                    };
                    $mapsUrl = 'https://www.google.com/maps?q=' . $report->latitude . ',' . $report->longitude;
                @endphp
                <div class="flex gap-4 rounded-xl bg-white/5 p-3 ring-1 ring-white/10">
                    {{-- 縮圖 --}}
                    <div class="h-20 w-20 shrink-0 overflow-hidden rounded-lg">
                        @if ($report->image_path)
                            <img src="{{ asset('storage/' . $report->image_path) }}"
                                 alt="回報照片"
                                 class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full w-full items-center justify-center bg-black/30 text-2xl">🐀</div>
                        @endif
                    </div>

                    {{-- 內容 --}}
                    <div class="flex min-w-0 flex-1 flex-col justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-medium {{ $badge['class'] }}">
                                    {{ $badge['text'] }}
                                </span>
                                <span class="text-[11px] text-slate-500">{{ $report->created_at->diffForHumans() }}</span>
                            </div>
                            @if ($report->description)
                                <p class="mt-1 line-clamp-2 text-sm text-slate-300">{{ $report->description }}</p>
                            @else
                                <p class="mt-1 text-sm text-slate-600 italic">（無描述）</p>
                            @endif
                        </div>
                        <a href="{{ $mapsUrl }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="mt-2 inline-flex items-center gap-1 text-[11px] text-slate-500 hover:text-blue-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                            </svg>
                            {{ number_format($report->latitude, 5) }}, {{ number_format($report->longitude, 5) }}
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $reports->links() }}
        </div>
    @endif
</div>
