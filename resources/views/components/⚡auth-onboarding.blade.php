<?php

use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public bool $open = false;

    #[On('open-auth-onboarding')]
    public function show(): void
    {
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
    }
}; ?>

<div>
    @if ($open)
        <div class="fixed inset-0 z-[1000] flex items-end justify-center bg-black/70 backdrop-blur-sm sm:items-center"
             wire:click.self="close">
            <div class="w-full max-w-md rounded-t-3xl bg-[#161b22] p-6 text-slate-100 shadow-2xl ring-1 ring-white/10 sm:rounded-3xl">
                <h2 class="text-xl font-bold">啟動雷達 · 加入捕鼠隊</h2>
                <p class="mt-2 text-sm text-slate-400">為了通報精準度，我們需要您協助：</p>

                <ol class="mt-4 space-y-3 text-sm">
                    <li class="flex gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-red-600/20 text-red-400">1</span>
                        <span>看到米奇先別怕，拿出手機<strong>拍張照片</strong>。</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-red-600/20 text-red-400">2</span>
                        <span>允許瀏覽器存取<strong>地理位置</strong>，標記發現點。</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-red-600/20 text-red-400">3</span>
                        <span>送出通報，雷達熱力圖即時更新。</span>
                    </li>
                </ol>

                <p class="mt-4 text-[11px] leading-relaxed text-slate-500">
                    登入即代表您同意我們儲存上傳之照片與當下的地理位置資訊，僅用於本平台之鼠患通報用途。
                </p>

                <div class="mt-6 space-y-3">
                    <span aria-disabled="true"
                          class="pointer-events-none flex w-full cursor-not-allowed items-center justify-center gap-2 rounded-xl bg-[#06C755] px-4 py-3 font-semibold text-white opacity-50">
                        使用 LINE 快速登入（施工中）
                    </span>
                    <a href="{{ route('auth.redirect', 'google') }}"
                       class="flex w-full items-center justify-center gap-2 rounded-xl bg-white px-4 py-3 font-semibold text-slate-900 transition hover:bg-slate-100">
                        使用 Google 登入
                    </a>
                </div>

                <button type="button" wire:click="close"
                        class="mt-4 block w-full text-center text-xs text-slate-500 hover:text-slate-300">
                    稍後再說
                </button>
            </div>
        </div>
    @endif
</div>
