@once
    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    @endpush
    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin="" defer></script>
    @endpush
@endonce

@php
    $lat = (float) ($record?->latitude  ?? 25.0330);
    $lng = (float) ($record?->longitude ?? 121.5654);
@endphp

<div
    x-data="{
        map: null,
        marker: null,
        init() {
            // Leaflet 可能因 defer 還沒載入，用輪詢等待
            const tryInit = () => {
                if (typeof L === 'undefined') { setTimeout(tryInit, 100); return; }

                this.map = L.map(this.$refs.mapEl, {
                    zoomControl: true,
                    attributionControl: false,
                }).setView([{{ $lat }}, {{ $lng }}], 17);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    subdomains: 'abc',
                    maxZoom: 19,
                }).addTo(this.map);

                this.marker = L.marker([{{ $lat }}, {{ $lng }}], { draggable: true }).addTo(this.map);

                this.marker.on('dragend', () => {
                    const ll = this.marker.getLatLng();
                    const lat = parseFloat(ll.lat.toFixed(7));
                    const lng = parseFloat(ll.lng.toFixed(7));
                    $wire.set('data.latitude',  lat);
                    $wire.set('data.longitude', lng);
                });
            };
            tryInit();
        }
    }"
    class="mb-1"
    wire:ignore
>
    <div x-ref="mapEl"
         style="height: 280px; border-radius: 0.5rem; overflow: hidden; border: 1px solid rgba(156,163,175,0.3); z-index: 0;">
    </div>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
        拖曳圖釘調整正確位置，儲存後系統自動重建行政區地址。
    </p>
</div>
