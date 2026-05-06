{{--
  同帳號回報展開 modal
  Props: $reports (Collection<Report>), $currentId (int)
--}}
@php use Illuminate\Support\Facades\Storage; @endphp

<div class="space-y-3 py-2 text-sm">
    @foreach($reports as $report)
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
