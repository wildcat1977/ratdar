<?php

use App\Models\Report;
use App\Services\ImageModerationService;
use Illuminate\Support\Facades\Auth;
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
            'description.required' => '未上傳照片時，備註說明為必填。',
            'description.min'      => '未上傳照片時，備註說明至少需要 20 個字元。',
            'photo.image'          => '請上傳圖片檔案。',
            'photo.max'            => '照片檔案不得超過 20MB。',
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
        $this->validate();

        $filename = null;
        $status   = config('radar.auto_approve')
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
                ->moderate($this->photo->getRealPath());

            $status = match(true) {
                ! $moderation['is_valid']    => Report::STATUS_REJECTED,
                config('radar.auto_approve') => Report::STATUS_APPROVED,
                default                      => Report::STATUS_PENDING,
            };
        }

        Report::create([
            'user_id'     => Auth::id(),
            'latitude'    => $this->latitude,
            'longitude'   => $this->longitude,
            'address'     => $this->address ?: null,
            'image_path'  => $filename,
            'description' => $this->description ?: null,
            'status'      => $status,
        ]);

        if ($this->photo && isset($moderation) && ! $moderation['is_valid']) {
            $this->rejected = true;
            return;
        }

        $this->submitted = true;
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
                        <h2 class="text-xl font-bold">通報成功</h2>
                        <p class="mt-2 text-sm text-slate-400">雷達已更新，感謝你的協助！</p>
                        <button wire:click="close"
                                class="mt-6 w-full rounded-xl bg-red-600 px-4 py-3 font-semibold">
                            關閉
                        </button>
                    </div>
                @elseif ($rejected)
                    <div class="py-10 text-center">
                        <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-yellow-600/20">
                            <span class="text-4xl">🚫</span>
                        </div>
                        <h2 class="text-xl font-bold text-yellow-400">圖片未通過審核</h2>
                        <p class="mt-2 text-sm text-slate-400">
                            AI 偵測到照片可能不符合通報規範<br>
                            （請上傳現場環境照，避免人臉或無關圖片）
                        </p>
                        <button wire:click="close"
                                class="mt-6 w-full rounded-xl bg-white/10 px-4 py-3 font-semibold text-slate-300 hover:bg-white/20">
                            關閉
                        </button>
                    </div>
                @else
                    <div class="flex items-start justify-between">
                        <h2 class="text-xl font-bold">回報鼠蹤</h2>
                        <button wire:click="close" class="text-slate-500 hover:text-slate-200">✕</button>
                    </div>

                    <form wire:submit="submit" class="mt-4 space-y-4">
                        <div x-data="{ preview: null, setPreview(files) { const f = files?.[0]; if (!f) return; const old = this.preview; this.preview = URL.createObjectURL(f); if (old) URL.revokeObjectURL(old); } }">
                            <label class="block text-xs font-medium text-slate-400">📸 現場照片</label>
                            {{-- 縮圖預覽 --}}
                            <div x-show="preview" x-cloak class="mt-2 flex items-center gap-3">
                                <img :src="preview" alt="預覽" class="h-20 w-20 rounded-lg object-cover ring-1 ring-white/20">
                                <button type="button"
                                        @click="preview = null"
                                        class="text-xs text-slate-500 hover:text-slate-300">✕ 重選</button>
                            </div>
                            <input type="file" accept="image/*"
                                   wire:model="photo"
                                   x-on:change="setPreview($el.files); window.onPhotoChange($el, $wire)"
                                   class="mt-2 w-full rounded-lg border border-white/10 bg-black/30 p-2 text-sm">
                            @error('photo') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-400">📍 發現位置</label>
                            <div wire:ignore
                                 x-data
                                 x-init="initPinMap($el, $wire)"
                                 class="mt-2 h-48 w-full overflow-hidden rounded-lg border border-white/10 bg-black/40"></div>
                            <p class="mt-1 text-[11px] text-slate-500">
                                @if ($exifLocation && $latitude && $longitude)
                                    📷 從照片 EXIF 取得：{{ number_format($latitude, 5) }}, {{ number_format($longitude, 5) }}
                                @elseif ($latitude && $longitude)
                                    已鎖定：{{ number_format($latitude, 5) }}, {{ number_format($longitude, 5) }}
                                @else
                                    正在抓取定位…
                                @endif
                            </p>
                            @error('latitude') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-400">📝 備註說明
                                <span class="text-slate-600">（未上傳照片時必填，至少 20 字）</span>
                            </label>
                            <textarea wire:model="description" rows="3"
                                      placeholder="例如：垃圾堆、騎樓、巷口…"
                                      class="mt-2 w-full rounded-lg border border-white/10 bg-black/30 p-2 text-sm placeholder:text-slate-600"></textarea>
                            @error('description') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <button type="submit"
                                wire:loading.attr="disabled"
                                class="w-full rounded-xl bg-red-600 px-4 py-3 font-bold text-white shadow-[0_0_20px_rgba(239,68,68,0.5)] active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed">
                            <span wire:loading.remove>送出通報</span>
                            <span wire:loading>⏳ 系統防衛網掃描中…</span>
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endif
</div>
