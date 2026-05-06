{{--
  同帳號回報展開 modal
  Props: $reports (Collection<Report>), $currentId (int)
--}}
@php
use Illuminate\Support\Facades\Storage;
use App\Filament\Resources\Reports\ReportResource;

$statusColor = fn (string $s) => match($s) {
    "approved"      => "bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300",
    "reported_1999" => "bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300",
    "resolved"      => "bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300",
    "rejected"      => "bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400",
    default         => "bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300",
};

$reasonsJson = json_encode(\App\Models\Report::REJECTION_REASONS, JSON_UNESCAPED_UNICODE);
@endphp

<div class="divide-y divide-gray-100 dark:divide-gray-700/60">
    @foreach($reports as $report)
        <div
            class="flex gap-3 py-3 first:pt-1 {{ $report->id === $currentId ? 'rounded bg-primary-50/60 px-2 dark:bg-primary-950/20' : '' }}"
            x-data="{
                rejectOpen: false,
                reason: '',
                newStatus: '{{ $report->status }}',
                newReason: '{{ $report->rejection_reason }}',
                reasons: {!! $reasonsJson !!},
                statusLabel(s) {
                    return { pending:'待審核', approved:'🔴 已核准', rejected:'❌ 已拒絕', reported_1999:'🟡 通報 1999', resolved:'🟢 已處理' }[s] ?? s;
                }
            }"
        >
            @if($report->image_path)
                <a href="{{ Storage::disk('public')->url($report->image_path) }}" target="_blank" class="flex-shrink-0">
                    <img src="{{ Storage::disk('public')->url($report->image_path) }}"
                         alt="回報照片"
                         class="h-16 w-16 rounded object-cover ring-1 ring-black/10">
                </a>
            @else
                <div class="flex h-16 w-16 flex-shrink-0 items-center justify-center rounded bg-gray-100 text-center text-[10px] leading-tight text-gray-400 dark:bg-gray-800">
                    無照片
                </div>
            @endif

            <div class="flex flex-1 min-w-0 flex-col gap-1">
                <div class="flex flex-wrap items-center gap-1.5 text-xs">
                    <span class="font-mono font-semibold text-gray-700 dark:text-gray-300">#{{ $report->id }}</span>
                    @if($report->id === $currentId)
                        <span class="rounded bg-primary-100 px-1.5 py-0.5 text-[10px] font-medium text-primary-700 dark:bg-primary-900 dark:text-primary-300">當前</span>
                    @endif
                    <span>{{ $report->type === 'rat' ? '🐀 鼠蹤' : '☠️ 毒餌' }}</span>
                    <span class="ml-auto text-gray-400">{{ $report->created_at->format('m/d H:i') }}</span>
                </div>

                <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="rounded px-1.5 py-0.5 text-[11px] font-medium {{ $statusColor($report->status) }}"
                          x-text="statusLabel(newStatus)"></span>
                    <span x-show="newReason" class="text-[10px] text-gray-500"
                          x-text="reasons[newReason] ?? newReason"></span>
                </div>

                @if($report->address)
                    <div class="truncate text-[11px] text-gray-500">📍 {{ $report->address }}</div>
                @endif
                @if($report->description)
                    <div class="truncate text-[11px] text-gray-600 dark:text-gray-400">
                        {{ \Illuminate\Support\Str::limit($report->description, 50) }}
                    </div>
                @endif
            </div>

            <div class="flex flex-shrink-0 flex-col items-end gap-1.5">
                <button
                    wire:click="updateReportStatus({{ $report->id }}, 'approved')"
                    x-on:click="newStatus = 'approved'; newReason = ''; rejectOpen = false"
                    x-bind:disabled="newStatus === 'approved'"
                    x-bind:class="newStatus === 'approved' ? 'opacity-40 cursor-default' : 'hover:bg-red-600'"
                    class="rounded bg-red-500 px-2.5 py-1 text-xs font-medium text-white transition active:scale-95">
                    核准
                </button>
                <button
                    x-on:click="rejectOpen = !rejectOpen"
                    x-bind:class="newStatus === 'rejected' ? 'bg-gray-400 dark:bg-gray-600' : 'bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600'"
                    class="rounded px-2.5 py-1 text-xs font-medium text-gray-700 dark:text-gray-200 transition active:scale-95">
                    拒絕
                </button>
                <a href="{{ ReportResource::getUrl('edit', ['record' => $report->id]) }}"
                   target="_blank"
                   class="rounded border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 transition">
                    編輯
                </a>
            </div>
        </div>

        <div x-show="rejectOpen" x-cloak
             class="flex flex-wrap items-center gap-2 bg-gray-50 px-3 py-2 text-xs dark:bg-gray-800/50">
            <span class="text-gray-500">原因：</span>
            <select x-model="reason"
                    class="flex-1 rounded border border-gray-300 bg-white px-2 py-1 text-xs dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                <option value="">選擇原因…</option>
                @foreach(\App\Models\Report::REJECTION_REASONS as $rKey => $rLabel)
                    <option value="{{ $rKey }}">{{ $rLabel }}</option>
                @endforeach
            </select>
            <button
                x-on:click="if(reason){ \$wire.updateReportStatus({{ $report->id }}, 'rejected', reason); newStatus='rejected'; newReason=reason; rejectOpen=false }"
                class="rounded bg-gray-600 px-2.5 py-1 font-medium text-white hover:bg-gray-700 disabled:cursor-not-allowed disabled:opacity-40 active:scale-95">
                確認
            </button>
            <button x-on:click="rejectOpen = false" class="text-gray-400 hover:text-gray-600">取消</button>
        </div>
    @endforeach
</div>
