{{--
  同帳號回報展開 modal
  Props: $reports (Collection<Report>), $currentId (int)
--}}
@php
use Illuminate\Support\Facades\Storage;
use App\Filament\Resources\Reports\ReportResource;

$statusLabel = fn (string $s) => match($s) {
    'pending'       => '待審核',
    'approved'      => '🔴 已核准',
    'rejected'      => '❌ 已拒絕',
    'reported_1999' => '🟡 通報 1999',
    'resolved'      => '🟢 已處理',
    default         => $s,
};
$statusColor = fn (string $s) => match($s) {
    'pending'       => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    'approved'      => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    'reported_1999' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    'resolved'      => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    'rejected'      => 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400',
    default         => 'bg-gray-100 text-gray-600',
};
@endphp

<div class="divide-y divide-gray-100 dark:divide-gray-700/60">
    @foreach($reports as $report)
        <div
            class="flex gap-3 py-3 first:pt-1 last:pb-1 {{ $report->id === $currentId ? 'rounded bg-primary-50/60 px-2 dark:bg-primary-950/20' : '' }}"
            x-data="{ rejectOpen: false, reason: '', newStatus: '{{ $report->status }}', newReason: '{{ $report->rejection_reason }}' }"
        >
            {{-- 縮圖 --}}
            @if($report->image_path)
                <a href="{{ Storage::disk('public')->url($report->image_path) }}" target="_blank" class="flex-shrink-0">
                    <img
                        src="{{ Storage::disk('public')->url($report->image_path) }}"
                        alt="回報照片"
                        class="h-16 w-16 rounded object-cover ring-1 ring-black/10"
                    >
                </a>
            @else
                <div class="flex h-16 w-16 flex-shrink-0 items-center justify-center rounded bg-gray-100 text-center text-[10px] leading-tight text-gray-400 dark:bg-gray-800">
                    無照片
                </div>
            @endif

            {{-- 主要資訊 --}}
            <div class="flex flex-1 min-w-0 flex-col gap-1">
                {{-- 第一行：ID、類型、時間 --}}
                <div class="flex flex-wrap items-center gap-1.5 text-xs">
                    <span class="font-mono font-semibold text-gray-700 dark:text-gray-300">#{{ $report->id }}</span>
                    @if($report->id === $currentId)
                        <span class="rounded bg-primary-100 px-1.5 py-0.5 text-[10px] font-medium text-primary-700 dark:bg-primary-900 dark:text-primary-300">
                            當前
                        </span>
                    @endif
                    <span>{{ $report->type === 'rat' ? '🐀 鼠蹤' : '☠️ 毒餌' }}</span>
                    <span class="ml-auto text-gray-400">{{ $report->created_at->format('m/d H:i') }}</span>
                </div>

                {{-- 第二行：狀態badge（動態更新） --}}
                <div class="flex items-center gap-1.5 flex-wrap">
                    <span
                        class="rounded px-1.5 py-0.5 text-[11px] font-medium {{ $statusColor($report->status) }}"
                        x-text="{
                            pending: '待審核',
                            approved: '🔴 已核准',
                            rejected: '❌ 已拒絕',
                            reported_1999: '🟡 通報 1999',
                            resolved: '🟢 已處理',
                        }[newStatus] ?? newStatus"
                    ></span>
                    <template x-if="newReason">
                        <span class="text-[10px] text-gray-500" x-text="({!! collect(\App\Models\Report::REJECTION_REASONS)->map(fn($v,$k) => "'$k': " . json_encode($v))->implode(', ') !!}})[newReason] ?? newReason"></span>
                    </template>
                </div>

                {{-- 地址 --}}
                @if($report->address)
                    <div class="truncate text-[11px] text-gray-500">📍 {{ $report->address }}</div>
                @endif

                {{-- 描述 --}}
                @if($report->description)
                    <div class="truncate text-[11px] text-gray-600 dark:text-gray-400">
                        {{ \Illuminate\Support\Str::limit($report->description, 50) }}
                    </div>
                @endif
            </div>

            {{-- 操作按鈕群（右側） --}}
            <div class="flex flex-shrink-0 flex-col items-end gap-1.5">
                {{-- 核准 --}}
                <button
                    wire:click="updateReportStatus({{ $report->id }}, 'approved')"
                    x-on:click="newStatus = 'approved'; newReason = ''; rejectOpen = false"
                    x-bind:class="newStatus === 'approved' ? 'opacity-40 cursor-default' : 'hover:bg-red-600'"
                    x-bind:disabled="newStatus === 'approved'"
                    class="rounded bg-red-500 px-2.5 py-1 text-xs font-medium text-white transition active:scale-95"
                >
                    核准
                </button>

                {{-- 拒絕切換 --}}
                <button
                    x-on:click="rejectOpen = !rejectOpen"
                    x-bind:class="newStatus === 'rejected' ? 'bg-gray-400 dark:bg-gray-600' : 'bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600'"
                    class="rounded px-2.5 py-1 text-xs font-medium text-gray-700 dark:text-gray-200 transition active:scale-95"
                >
                    拒絕
                </button>

                {{-- 編輯 --}}
                <a
                    href="{{ ReportResource::getUrl('edit', ['record' => $report->id]) }}"
                    target="_blank"
                    class="rounded border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 transition"
                >
                    編輯
                </a>
            </div>
        </div>

        {{-- 拒絕原因選擇（展開在該列下方） --}}
        <div
            x-show="rejectOpen"
            x-cloak
            class="flex flex-wrap items-center gap-2 bg-gray-50 px-3 py-2 dark:bg-gray-800/50"
        >
            <span class="text-xs text-gray-500">拒絕原因：</span>
            <select
                x-model="reason"
                class="flex-1 rounded border border-gray-300 bg-white px-2 py-1 text-xs dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
            >
                <option value="">選擇原因…</option>
                @foreach(\App\Models\Report::REJECTION_REASONS as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <button
                x-on:click="if(reason){ $wire.updateReportStatus({{ $report->id }}, 'rejected', reason); newStatus = 'rejected'; newReason = reason; rejectOpen = false }"
                x-bind:disabled="!reason"
                class="rounded bg-gray-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-gray-700 disabled:cursor-not-allowed disabled:opacity-40 active:scale-95"
            >
                確認
            </button>
            <button
                x-on:click="rejectOpen = false"
                class="text-xs text-gray-400 hover:text-gray-600"
            >
                取消
            </button>
        </div>
    @endforeach
</div>

        <div
            class="flex gap-3 rounded-lg border p-3 {{ $report->id === $currentId ? 'border-primary-400 bg-primary-50 dark:bg-primary-950/30' : 'border-gray-200 dark:border-gray-700' }}"
            x-data="{ rejectOpen: false, reason: '', done: false, doneLabel: '' }"
        >
            {{-- 縮圖 --}}
            @if($report->image_path)
                <a href="{{ Storage::disk('public')->url($report->image_path) }}" target="_blank" class="flex-shrink-0">
                    <img
                        src="{{ Storage::disk('public')->url($report->image_path) }}"
                        alt="回報照片"
                        class="h-20 w-20 rounded object-cover"
                    >
                </a>
            @else
                <div class="flex h-20 w-20 flex-shrink-0 items-center justify-center rounded bg-gray-100 text-center text-xs text-gray-400 dark:bg-gray-800">
                    無<br>照片
                </div>
            @endif

            {{-- 內容 --}}
            <div class="flex flex-1 flex-col gap-1 min-w-0">
                {{-- 標頭列 --}}
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="font-mono text-xs text-gray-500">#{{ $report->id }}</span>
                    @if($report->id === $currentId)
                        <span class="rounded bg-primary-100 px-1.5 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-900 dark:text-primary-300">
                            當前查看
                        </span>
                    @endif
                    <span class="text-xs">{{ $report->type === 'rat' ? '🐀 鼠蹤' : '☠️ 毒餌' }}</span>
                    <span class="ml-auto flex-shrink-0 text-xs text-gray-500">
                        {{ $report->created_at->format('Y-m-d H:i') }}
                    </span>
                </div>

                {{-- 狀態 --}}
                <div class="text-xs">
                    狀態：<strong>{{ match($report->status) {
                        'pending'       => '待審核',
                        'approved'      => '🔴 已核准（地圖上架）',
                        'rejected'      => '已拒絕',
                        'reported_1999' => '🟡 已通報 1999',
                        'resolved'      => '🟢 已處理完畢',
                        default         => $report->status,
                    } }}</strong>
                    @if($report->rejection_reason)
                        <span class="ml-1 text-gray-500">
                            （{{ \App\Models\Report::REJECTION_REASONS[$report->rejection_reason] ?? $report->rejection_reason }}）
                        </span>
                    @endif
                </div>

                {{-- 地址 --}}
                @if($report->address)
                    <div class="truncate text-xs text-gray-500">📍 {{ $report->address }}</div>
                @endif

                {{-- 描述 --}}
                @if($report->description)
                    <div class="text-xs text-gray-600 dark:text-gray-400">
                        {{ \Illuminate\Support\Str::limit($report->description, 60) }}
                    </div>
                @endif

                {{-- 操作按鈕（只有待審核才顯示） --}}
                @if($report->status === 'pending')
                    {{-- 已完成操作後顯示確認文字 --}}
                    <div x-show="done" x-cloak class="mt-1 text-xs font-semibold text-green-600 dark:text-green-400">
                        ✓ <span x-text="doneLabel"></span>
                    </div>

                    {{-- 操作按鈕列 --}}
                    <div x-show="!done" class="mt-1 flex flex-wrap items-center gap-2">
                        <button
                            wire:click="updateReportStatus({{ $report->id }}, 'approved')"
                            x-on:click="done = true; doneLabel = '已核准上架'"
                            class="rounded bg-red-500 px-2.5 py-1 text-xs font-medium text-white hover:bg-red-600 active:scale-95"
                        >
                            核准上架
                        </button>

                        <button
                            x-on:click="rejectOpen = !rejectOpen"
                            class="rounded bg-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 active:scale-95"
                        >
                            拒絕 <span x-text="rejectOpen ? '▲' : '▼'" class="text-[10px]"></span>
                        </button>
                    </div>

                    {{-- 拒絕原因選擇（折疊） --}}
                    <div x-show="rejectOpen && !done" x-cloak class="mt-1 flex flex-wrap items-center gap-2">
                        <select
                            x-model="reason"
                            class="rounded border border-gray-300 bg-white px-2 py-1 text-xs dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                        >
                            <option value="">選擇拒絕原因…</option>
                            @foreach(\App\Models\Report::REJECTION_REASONS as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <button
                            x-on:click="if(reason){ $wire.updateReportStatus({{ $report->id }}, 'rejected', reason); done = true; doneLabel = '已拒絕' }"
                            x-bind:disabled="!reason"
                            class="rounded bg-gray-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-gray-700 disabled:cursor-not-allowed disabled:opacity-40 active:scale-95"
                        >
                            確認拒絕
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endforeach
</div>
