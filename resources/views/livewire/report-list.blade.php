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

        // 所有可見通報的座標（給地圖 modal 顯示背景點）
        $allCoords = Report::query()
            ->whereNotIn('status', [Report::STATUS_REJECTED])
            ->get(['id', 'latitude', 'longitude', 'address', 'status']);

        return view('livewire.report-list', compact('reports', 'allCoords'));
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

<div
    x-data="{
        showMap: false,
        mapLat: 0,
        mapLng: 0,
        mapInfo: '',
        mapId: null,
        _map: null,
        allCoords: @js($allCoords->map(fn($r) => ['id' => $r->id, 'lat' => (float)$r->latitude, 'lng' => (float)$r->longitude, 'addr' => $r->address ?? ''])),

        openMap(id, lat, lng, info) {
            this.mapId   = id;
            this.mapLat  = lat;
            this.mapLng  = lng;
            this.mapInfo = info;
            this.showMap = true;
            this.$nextTick(() => this._initMap());
        },
        closeMap() {
            this.showMap = false;
            if (this._map) { this._map.remove(); this._map = null; }
        },
        _initMap() {
            if (this._map) { this._map.remove(); this._map = null; }
            const map = L.map(this.$refs.modalMapEl, { zoomControl: true, attributionControl: false })
                .setView([this.mapLat, this.mapLng], 17);
            L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
                subdomains: 'abcd', maxZoom: 20
            }).addTo(map);

            // 其他通報：小灰點
            this.allCoords.forEach(r => {
                if (r.id === this.mapId) return;
                L.circleMarker([r.lat, r.lng], {
                    radius: 5, color: '#94a3b8', fillColor: '#94a3b8',
                    fillOpacity: 0.5, weight: 1
                }).bindTooltip(r.addr || `${r.lat.toFixed(4)}, ${r.lng.toFixed(4)}`, { direction: 'top' })
                  .addTo(map);
            });

            // 目前選中：大紅圈
            L.circleMarker([this.mapLat, this.mapLng], {
                radius: 12, color: '#ef4444', fillColor: '#ef4444',
                fillOpacity: 0.85, weight: 2
            }).bindPopup(`<div style='color:#0f172a;font-size:13px;max-width:200px'>${this.mapInfo}</div>`, { maxWidth: 220 })
              .addTo(map)
              .openPopup();

            this._map = map;
        }
    }"
    class="mx-auto max-w-4xl px-4 py-6"
>

    {{-- 地圖 Modal --}}
    <div x-show="showMap"
         x-cloak
         class="fixed inset-0 z-[2000] flex items-center justify-center bg-black/75 backdrop-blur-sm p-4"
         @keydown.escape.window="closeMap()">
        <div class="relative w-full max-w-2xl rounded-2xl bg-[#161b22] shadow-2xl ring-1 ring-white/10 overflow-hidden"
             @click.outside="closeMap()">
            {{-- Modal 標題列 --}}
            <div class="flex items-center justify-between border-b border-white/10 px-4 py-3">
                <p class="text-sm font-semibold text-slate-200">
                    📍 <span x-text="mapInfo"></span>
                </p>
                <button @click="closeMap()" class="text-slate-400 hover:text-slate-200">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                </button>
            </div>
            {{-- 地圖本體 --}}
            <div x-ref="modalMapEl" wire:ignore style="height: 400px; z-index: 0;"></div>
            <p class="px-4 py-2 text-center text-[11px] text-slate-500">
                <span class="inline-block h-2 w-2 rounded-full bg-red-500 align-middle"></span> 本筆通報
                <span class="ml-3 inline-block h-2 w-2 rounded-full bg-slate-400 align-middle"></span> 其他通報（可縮放比對是否重複）
            </p>
        </div>
    </div>

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
                            <div class="flex items-start gap-2">
                                <button
                                    @click="openMap({{ $report->id }}, {{ (float)$report->latitude }}, {{ (float)$report->longitude }}, '{{ addslashes($report->address ?? number_format($report->latitude, 4).', '.number_format($report->longitude, 4)) }}')"
                                    title="在地圖上查看位置"
                                    class="mt-0.5 shrink-0 text-slate-500 transition hover:text-red-400">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3"/>
                                        <line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/>
                                        <line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/>
                                    </svg>
                                </button>
                                @if($report->address)
                                    {{ $report->address }}
                                @else
                                    <a href="https://maps.google.com/?q={{ $report->latitude }},{{ $report->longitude }}"
                                       target="_blank" rel="noopener"
                                       class="text-slate-500 hover:text-slate-300">
                                        {{ number_format($report->latitude, 4) }}, {{ number_format($report->longitude, 4) }}
                                    </a>
                                @endif
                            </div>
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
