<?php

use App\Models\Report;
use App\Services\ImageModerationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public bool $open = false;
    public bool $exifLocation = false;

    public string $type = 'rat';  // rat | poison

    public $photo = null;

    public ?float $latitude = null;

    public ?float $longitude = null;

    public string $address = '';

    public string $description = '';

    public bool $submitted = false;
    public bool $rejected  = false;

    public function rules(): array
    {
        return [
            'type'        => ['required', 'in:rat,poison'],
            'photo'       => ['nullable', 'image', 'max:20480'],
            'latitude'    => ['required', 'numeric', 'between:-90,90'],
            'longitude'   => ['required', 'numeric', 'between:-180,180'],
            'description' => $this->photo
                ? ['nullable', 'string', 'max:500']
                : ['required', 'string', 'min:20', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'description.required' => __('未上傳照片時，備註說明為必填。'),
            'description.min'      => __('未上傳照片時，備註說明至少需要 20 個字元。'),
            'photo.image'          => __('請上傳圖片檔案。'),
            'photo.max'            => __('照片檔案不得超過 20MB。'),
        ];
    }

    /**
     * 由首頁按鈕觸發；未登入則改開啟登入引導
     */
    #[On('open-report-form')]
    public function show(): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-auth-onboarding');
            return;
        }
        $this->reset(['photo', 'description', 'submitted', 'rejected']);
        $this->type = 'rat';
        $this->open = true;
    }

    public function close(): void
    {
        $this->open      = false;
        $this->submitted = false;
        $this->rejected  = false;
    }

    public function setLocation(float $lat, float $lng): void
    {
        $this->latitude = $lat;
        $this->longitude = $lng;
    }

    public function setAddress(string $address): void
    {
        $this->address = $address;
    }

    public function setLocationFromExif(float $lat, float $lng): void
    {
        $this->latitude = $lat;
        $this->longitude = $lng;
        $this->exifLocation = true;
    }

    public function submit(): void
    {
        if (Auth::user()?->is_banned) {
            $this->addError('description', __('您的帳號已被停用，無法提交通報。如有疑問請聯絡管理員。'));
            return;
        }

        $this->validate();

        $filename   = null;
        $hardReject = false;
        $status     = config('radar.auto_approve')
            ? Report::STATUS_APPROVED
            : Report::STATUS_PENDING;

        if ($this->photo) {
            // 有照片：重新編碼 JPEG + AI 審核
            $manager  = new ImageManager(new GdDriver());
            $image    = $manager->decode($this->photo->getRealPath());
            $filename = 'reports/' . Str::uuid() . '.jpg';
            $jpeg     = $image->encode(new JpegEncoder(quality: 85));
            Storage::disk('public')->put($filename, $jpeg);

            $moderation = app(ImageModerationService::class)
                ->moderate($this->photo->getRealPath(), $this->type);

            // 低把握度的拒絕（confidence < 0.8）送人工審核，不自動擋掉
            $hardReject = ! $moderation['is_valid'] && ($moderation['confidence'] ?? 0.0) >= 0.8;
            $status = match(true) {
                $hardReject                  => Report::STATUS_REJECTED,
                config('radar.auto_approve') => Report::STATUS_APPROVED,
                default                      => Report::STATUS_PENDING,
            };
        }

        Report::create([
            'user_id'          => Auth::id(),
            'type'             => $this->type,
            'latitude'         => $this->latitude,
            'longitude'        => $this->longitude,
            'address'          => $this->address ?: null,
            'image_path'       => $filename,
            'description'      => $this->description ?: null,
            'status'           => $status,
            'rejection_reason' => $hardReject ? 'ai_auto' : null,
        ]);

        if ($this->photo && isset($moderation) && $hardReject) {
            $this->rejected = true;
            return;
        }

        $this->submitted = true;
        Cache::forget('report_list.all_coords');
        Cache::forget('report_list.district_stats');
        $this->dispatch('report-submitted');
    }
}; ?>

<div>
    @if ($open)
        <div class="fixed inset-0 z-[1000] flex items-end justify-center bg-black/70 backdrop-blur-sm sm:items-center"
             wire:click.self="close">
            <div class="w-full max-w-md rounded-t-3xl bg-[#161b22] p-6 text-slate-100 shadow-2xl ring-1 ring-white/10 sm:rounded-3xl">
                @if ($submitted)
                    <div class="py-10 text-center">
                        <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-red-600/20">
                            <span class="text-4xl">📡</span>
                        </div>
                        <h2 class="text-xl font-bold">{{ __('通報成功') }}</h2>
                        <p class="mt-2 text-sm text-slate-400">{{ __('雷達已更新，感謝你的協助！') }}</p>
                        <button wire:click="close"
                                class="mt-6 w-full rounded-xl bg-red-600 px-4 py-3 font-semibold">
                            {{ __('關閉') }}
                        </button>
                    </div>
                @elseif ($rejected)
                    <div class="py-10 text-center">
                        <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-yellow-600/20">
                            <span class="text-4xl">🚫</span>
                        </div>
                        <h2 class="text-xl font-bold text-yellow-400">{{ __('圖片未通過審核') }}</h2>
                        <p class="mt-2 text-sm text-slate-400">
                            {{ __('AI 偵測到照片可能不符合通報規範（請上傳現場環境照，避免人臉或無關圖片）') }}
                        </p>
                        <button wire:click="close"
                                class="mt-6 w-full rounded-xl bg-white/10 px-4 py-3 font-semibold text-slate-300 hover:bg-white/20">
                            {{ __('關閉') }}
                        </button>
                    </div>
                @else
                    <div class="flex items-start justify-between">
                        <h2 class="text-xl font-bold">
                            @if ($type === 'poison')
                                <span class="text-purple-300">{{ __('☠️ 回報毒餌') }}</span>
                            @else
                                <span>{{ __('🐀 回報鼠蹤') }}</span>
                            @endif
                        </h2>
                        <button wire:click="close" class="text-slate-500 hover:text-slate-200">✕</button>
                    </div>

                    <form wire:submit="submit" class="mt-4 space-y-4">
                        {{-- 通報類型切換 --}}
                        <div>
                            <label class="block text-xs font-medium text-slate-400">{{ __('類型') }}</label>
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <button type="button"
                                        wire:click="$set('type', 'rat')"
                                        class="flex items-center justify-center gap-2 rounded-xl border px-3 py-3 text-sm font-semibold transition
                                               {{ $type === 'rat'
                                                   ? 'border-red-500/60 bg-red-500/15 text-red-200 shadow-[0_0_18px_rgba(239,68,68,0.35)]'
                                                   : 'border-white/10 bg-black/30 text-slate-400 hover:border-white/20' }}">
                                    {{ __('🐀 發現鼠蹤') }}
                                </button>
                                <button type="button"
                                        wire:click="$set('type', 'poison')"
                                        class="flex items-center justify-center gap-2 rounded-xl border px-3 py-3 text-sm font-semibold transition
                                               {{ $type === 'poison'
                                                   ? 'border-purple-500/60 bg-purple-500/15 text-purple-200 shadow-[0_0_18px_rgba(168,85,247,0.35)]'
                                                   : 'border-white/10 bg-black/30 text-slate-400 hover:border-white/20' }}">
                                    {{ __('☠️ 發現毒餌') }}
                                </button>
                            </div>
                            @if ($type === 'poison')
                                <p class="mt-2 rounded-md bg-purple-500/10 px-3 py-2 text-[11px] leading-relaxed text-purple-200/80">
                                    {{ __('回報被隨意放置於盆栽、花圃、騎樓的老鼠藥/毒餌，提醒寵物飼主避開該區域與野生動物保育者關注。') }}
                                </p>
                                {{-- 毒餌辨識圖 + 說明文連結 --}}
                                <div x-data="{ lightbox: false }" class="mt-2 flex items-center gap-3">
                                    <button type="button" @click="lightbox = true" class="shrink-0 focus:outline-none">
                                        <img src="https://ratdar.taipei/rat-poison-demo.png"
                                             alt="{{ __('常見鼠藥外觀辨識') }}"
                                             class="h-16 w-16 rounded-lg object-cover ring-1 ring-purple-400/40 hover:ring-purple-400/80 transition">
                                    </button>
                                    <div class="text-[11px] text-purple-200/70 leading-relaxed">
                                        <span class="font-medium text-purple-300">{{ __('常見鼠藥外觀(點圖可放大)') }}</span><br>
                                        <a href="https://www.threads.com/@urmomovet/post/DX38LeFih8m"
                                           target="_blank" rel="noopener noreferrer"
                                           class="underline underline-offset-2 hover:text-purple-200">
                                            {{ __('→ 閱讀完整辨識說明文章') }}
                                        </a>
                                    </div>
                                    {{-- Lightbox --}}
                                    <div x-show="lightbox" x-cloak
                                         @click="lightbox = false"
                                         @keydown.escape.window="lightbox = false"
                                         class="fixed inset-0 z-[2000] flex items-center justify-center bg-black/85 backdrop-blur-sm p-4">
                                        <img src="https://ratdar.taipei/rat-poison-demo.png"
                                             alt="{{ __('常見鼠藥外觀辨識') }}"
                                             class="max-h-[80vh] max-w-full rounded-xl shadow-2xl"
                                             @click.stop>
                                        <button type="button" @click="lightbox = false"
                                                class="absolute top-4 right-4 text-white/60 hover:text-white text-2xl leading-none">✕</button>
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div x-data="{ preview: null, setPreview(files) { const f = files?.[0]; if (!f) return; const old = this.preview; this.preview = URL.createObjectURL(f); if (old) URL.revokeObjectURL(old); } }">
                            <label class="block text-xs font-medium text-slate-400">{{ __('📸 現場照片') }}</label>
                            {{-- 縮圖預覽 --}}
                            <div x-show="preview" x-cloak class="mt-2 flex items-center gap-3">
                                <img :src="preview" alt="預覽" class="h-20 w-20 rounded-lg object-cover ring-1 ring-white/20">
                                <button type="button"
                                        @click="preview = null"
                                        class="text-xs text-slate-500 hover:text-slate-300">{{ __('✕ 重選') }}</button>
                            </div>
                            <input type="file" accept="image/*"
                                   wire:model="photo"
                                   x-on:change="setPreview($el.files); window.onPhotoChange($el, $wire)"
                                   class="mt-2 w-full rounded-lg border border-white/10 bg-black/30 p-2 text-sm">
                            @error('photo') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-400">{{ __('📍 發現位置') }} <span class="text-slate-600">（{{ __('移動地圖即可調整') }}）</span></label>
                            <div wire:ignore
                                 x-data
                                 x-init="initPinMap($el, $wire)"
                                 class="mt-2 h-48 w-full overflow-hidden rounded-lg border border-white/10 bg-black/40"></div>
                            <p class="mt-1 text-[11px] text-slate-500">
                                @if ($exifLocation && $latitude && $longitude)
                                    {{ __('📷 從照片 EXIF 取得：:coords（可移動地圖微調）', ['coords' => number_format($latitude, 5).', '.number_format($longitude, 5)]) }}
                                @elseif ($latitude && $longitude)
                                    {{ __('已鎖定：:coords（可移動地圖調整）', ['coords' => number_format($latitude, 5).', '.number_format($longitude, 5)]) }}
                                @else
                                    {{ __('正在抓取定位…') }}
                                @endif
                            </p>
                            @error('latitude') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-400">{{ __('📝 備註說明') }}
                                <span class="text-slate-600">（{{ __('未上傳照片時必填，至少 20 字') }}）</span>
                            </label>
                            <textarea wire:model="description" rows="3"
                                      placeholder="{{ $type === 'poison' ? __('例如：放在公園盆栽下、花圍邊、騎樓地上…') : __('例如：垃圾堆、騎樓、巷口…') }}"
                                      class="mt-2 w-full rounded-lg border border-white/10 bg-black/30 p-2 text-sm placeholder:text-slate-600"></textarea>
                            @error('description') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <button type="submit"
                                wire:loading.attr="disabled"
                                class="w-full rounded-xl px-4 py-3 font-bold text-white active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed
                                       {{ $type === 'poison'
                                           ? 'bg-purple-600 shadow-[0_0_20px_rgba(168,85,247,0.5)]'
                                           : 'bg-red-600 shadow-[0_0_20px_rgba(239,68,68,0.5)]' }}">
                            <span wire:loading.remove>{{ __('送出通報') }}</span>
                            <span wire:loading>{{ __('⏳ 系統防衛網揃測中…') }}</span>
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endif
</div>
