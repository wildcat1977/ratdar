<?php

use App\Models\Contact;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public bool $open = false;
    public bool $submitted = false;

    public string $name    = '';
    public string $email   = '';
    public string $subject = '';
    public string $message = '';

    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'max:100'],
            'email'   => ['required', 'email', 'max:200'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    #[On('open-contact-form')]
    public function show(): void
    {
        // 已登入者預填
        if (Auth::check()) {
            $this->name  = Auth::user()->name  ?? '';
            $this->email = Auth::user()->email ?? '';
        }
        $this->submitted = false;
        $this->open      = true;
    }

    public function close(): void
    {
        $this->reset(['open', 'submitted', 'name', 'email', 'subject', 'message']);
        $this->resetValidation();
    }

    public function submit(): void
    {
        $data = $this->validate();

        Contact::create([
            'user_id' => Auth::id(),
            'name'    => $data['name'],
            'email'   => $data['email'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'status'  => Contact::STATUS_UNREAD,
        ]);

        $this->submitted = true;
    }
}; ?>

<div>
    {{-- Backdrop --}}
    @if ($open)
        <div class="fixed inset-0 z-[600] flex items-end justify-center sm:items-center"
             x-data x-on:keydown.escape.window="$wire.close()">

            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"
                 wire:click="close"></div>

            {{-- Modal --}}
            <div class="relative z-10 w-full max-w-lg rounded-t-2xl sm:rounded-2xl bg-white shadow-xl
                        transform transition-all duration-300 ease-out"
                 style="max-height:90dvh;overflow-y:auto">

                {{-- Header --}}
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h2 class="text-base font-bold text-slate-800">
                        ✉️ 聯絡管理員
                    </h2>
                    <button type="button" wire:click="close"
                            class="rounded-full p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="px-5 py-5">
                    @if ($submitted)
                        {{-- 成功畫面 --}}
                        <div class="flex flex-col items-center gap-3 py-8 text-center">
                            <div class="text-5xl">📬</div>
                            <p class="text-lg font-bold text-slate-800">訊息已送出！</p>
                            <p class="text-sm text-slate-500">管理員收到後將盡快回覆您。</p>
                            <button type="button" wire:click="close"
                                    class="mt-2 rounded-xl bg-slate-800 px-6 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
                                關閉
                            </button>
                        </div>
                    @else
                        <form wire:submit="submit" class="flex flex-col gap-4">

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {{-- 姓名 --}}
                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold text-slate-600">
                                        姓名 <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" wire:model="name"
                                           placeholder="您的稱呼"
                                           class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-800
                                                  placeholder-slate-400 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200
                                                  @error('name') border-red-400 bg-red-50 @enderror">
                                    @error('name')
                                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Email --}}
                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold text-slate-600">
                                        Email <span class="text-red-500">*</span>
                                    </label>
                                    <input type="email" wire:model="email"
                                           placeholder="your@email.com"
                                           class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-800
                                                  placeholder-slate-400 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200
                                                  @error('email') border-red-400 bg-red-50 @enderror">
                                    @error('email')
                                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            {{-- 主旨 --}}
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-600">
                                    主旨 <span class="text-red-500">*</span>
                                </label>
                                <input type="text" wire:model="subject"
                                       placeholder="例：回報誤判 / 功能建議 / 其他問題"
                                       class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-800
                                              placeholder-slate-400 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200
                                              @error('subject') border-red-400 bg-red-50 @enderror">
                                @error('subject')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- 訊息 --}}
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-600">
                                    訊息內容 <span class="text-red-500">*</span>
                                </label>
                                <textarea wire:model="message" rows="5"
                                          placeholder="請詳細描述您的問題或建議…"
                                          class="w-full resize-none rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-800
                                                 placeholder-slate-400 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200
                                                 @error('message') border-red-400 bg-red-50 @enderror"></textarea>
                                @error('message')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <button type="submit"
                                    class="w-full rounded-xl bg-slate-800 py-3 text-sm font-bold text-white
                                           shadow-md transition active:scale-95 hover:bg-slate-700
                                           disabled:opacity-50"
                                    wire:loading.attr="disabled">
                                <span wire:loading.remove>📤 送出訊息</span>
                                <span wire:loading>傳送中…</span>
                            </button>

                        </form>
                    @endif
                </div>

            </div>
        </div>
    @endif
</div>
