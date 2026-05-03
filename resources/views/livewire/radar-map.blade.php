<?php

use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public function getHeatPointsProperty(): array
    {
        return Report::query()
            ->visible()
            ->select(['latitude', 'longitude'])
            ->limit(5000)
            ->get()
            ->map(fn ($r) => [(float) $r->latitude, (float) $r->longitude, 0.6])
            ->all();
    }

    public function getMarkersProperty(): array
    {
        return Report::query()
            ->visible()
            ->select(['latitude', 'longitude', 'image_path', 'description', 'created_at', 'status'])
            ->latest()
            ->limit(5000)
            ->get()
            ->map(fn ($r) => [
                'lat'    => (float) $r->latitude,
                'lng'    => (float) $r->longitude,
                'img'    => $r->image_path ? asset('storage/' . $r->image_path) : null,
                'desc'   => $r->description,
                'at'     => $r->created_at->diffForHumans(),
                'status' => $r->status,
            ])
            ->all();
    }

    public function getTickerMessagesProperty(): array
    {
        $messages = [];

        // 最近有描述的可見通報
        Report::query()
            ->visible()
            ->whereNotNull('description')
            ->latest()
            ->limit(6)
            ->get(['description', 'created_at'])
            ->each(function ($r) use (&$messages) {
                $messages[] = '📡 系統廣播：' . $r->created_at->diffForHumans()
                    . '，有市民通報：' . Str::limit($r->description, 25);
            });

        // 排行榜冠軍
        $top = User::withCount([
            'reports as n' => fn ($q) => $q->where('status', Report::STATUS_APPROVED),
        ])->orderByDesc('n')->first();
        if ($top && $top->n > 0) {
            $messages[] = '🏆 英雄榜冠軍 ' . $top->name . ' 已累計通報 ' . $top->n . ' 筆鼠蹤';
        }

        // 全站累積通報數
        $total = Report::query()->visible()->count();
        if ($total > 0) {
            $messages[] = '📊 鼠蹤雷達目前共收到 ' . $total . ' 筆市民通報，持續更新中';
        }

        return $messages ?: ['📡 系統運作中，等待通報資料…'];
    }

    #[On('report-submitted')]
    public function refreshHeat(): void
    {
        $this->dispatch('mouseradar:heat-updated',
            points:  $this->getHeatPointsProperty(),
            markers: $this->getMarkersProperty(),
        );
    }
}; ?>

<div
    id="radar-map"
    wire:ignore
    class="absolute inset-0 z-0"
    data-default-lat="{{ config('radar.default_lat') }}"
    data-default-lng="{{ config('radar.default_lng') }}"
    data-default-zoom="{{ config('radar.default_zoom') }}"
    data-nearby-radius="{{ config('radar.nearby_radius_km') }}"
    data-heat-points='@json($this->heatPoints)'
    data-markers='@json($this->markers)'
    data-ticker='@json($this->tickerMessages)'
></div>
