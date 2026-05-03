<?php

use App\Models\Report;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $filterStatus = '';
    public string $search       = '';

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = Report::query()
            ->with('user:id,name')
            ->whereNotIn('status', [Report::STATUS_REJECTED]);

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->search) {
            $query->where('description', 'ilike', '%' . $this->search . '%');
        }

        $reports = $query->latest()->paginate(15);

        return view('livewire.report-list', compact('reports'));
    }
}; ?>

@php
$statusConfig = [
    'pending'       => ['label' => '待審核',    'class' => 'bg-amber-500/20 text-amber-400'],
    'approved'      => ['label' => '已審核',    'class' => 'bg-green-500/20 text-green-400'],
    'reported_1999' => ['label' => '已通報1999','class' => 'bg-blue-500/20 text-blue-400'],
    'resolved'      => ['label' => '已處理',    'class' => 'bg-slate-500/20 text-slate-400'],
];
@endphp

<div class="mx-auto max-w-4xl px-4 py-6">

    {{-- 標題列 --}}
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-bold text-slate-100">通報紀錄</h2>
        <a href="{{ route('reports.export') }}"
           class="flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-emerald-500">
            ↓ 匯出 CSV
        </a>
    </div>

    {{-- 篩選列 --}}
    <div class="mb-4 flex flex-wrap gap-2">
        <input wire:model.live.debounce.300ms="search"
               type="search"
               placeholder="搜尋說明…"
               class="flex-1 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-red-500 min-w-[160px]">

        <select wire:model.live="filterStatus"
                class="rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-1 focus:ring-red-500">
            <option value="">全部狀態</option>
            @foreach($statusConfig as $key => $cfg)
                <option value="{{ $key }}" class="bg-[#161b22]">{{ $cfg['label'] }}</option>
            @endforeach
        </select>
    </div>

    {{-- 資料表格 --}}
    <div class="overflow-x-auto rounded-xl border border-white/10">
        <table class="w-full text-sm">
            <thead class="border-b border-white/10 bg-white/5 text-left text-xs text-slate-400">
                <tr>
                    <th class="w-28 px-4 py-3 font-medium">通報時間</th>
                    <th class="w-32 px-4 py-3 font-medium">地點</th>
                    <th class="w-24 px-4 py-3 font-medium">狀態</th>
                    <th class="w-16 px-4 py-3 font-medium">照片</th>
                    <th class="px-4 py-3 font-medium">說明</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @forelse($reports as $report)
                    <tr class="transition hover:bg-white/5">
                        <td class="whitespace-nowrap px-4 py-3 text-slate-400">
                            {{ $report->created_at->format('Y-m-d') }}<br>
                            <span class="text-xs text-slate-600">{{ $report->created_at->format('H:i') }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-300">
                            @if($report->address)
                                {{ $report->address }}
                            @else
                                <a href="https://maps.google.com/?q={{ $report->latitude }},{{ $report->longitude }}"
                                   target="_blank" rel="noopener"
                                   class="text-slate-500 hover:text-slate-300">
                                    {{ number_format($report->latitude, 4) }}, {{ number_format($report->longitude, 4) }}
                                </a>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @php $cfg = $statusConfig[$report->status] ?? ['label' => $report->status, 'class' => 'bg-white/10 text-slate-400']; @endphp
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $cfg['class'] }}">
                                {{ $cfg['label'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @if($report->image_path)
                                <a href="{{ Storage::disk('public')->url($report->image_path) }}" target="_blank" rel="noopener">
                                    <img src="{{ Storage::disk('public')->url($report->image_path) }}"
                                         alt="通報照片"
                                         class="h-12 w-12 rounded-lg object-cover ring-1 ring-white/10 transition hover:ring-red-500/60">
                                </a>
                            @else
                                <span class="text-slate-600">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-300">
                            {{ $report->description ?? '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-slate-500">尚無通報紀錄</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- 分頁 --}}
    @if($reports->hasPages())
        <div class="mt-4">
            {{ $reports->links() }}
        </div>
    @endif

    {{-- 資料筆數 --}}
    <p class="mt-3 text-right text-xs text-slate-600">
        共 {{ $reports->total() }} 筆通報
    </p>
</div>
