<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '見鼠地圖 Rat Radar | 台北城任務一起尋找老鼠')</title>

    {{-- SEO / OG --}}
    <meta name="description" content="城市防衛啟動！立刻開啟雷達，通報台北市各角落的鼠患蹤跡。">
    <meta name="theme-color" content="#0d1117">
    <link rel="canonical" href="@yield('canonical_url', url()->current())">

    <meta property="og:title" content="見鼠地圖 | 台北城任務一起尋找老鼠">
    <meta property="og:description" content="城市防衛啟動！立刻開啟雷達，通報台北市各角落的鼠患蹤跡，為城市安全盡一份心力。">
    <meta property="og:image" content="https://ratdar.taipei/og-image.png">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary_large_image">

    @include('partials.gtag')

    {{-- Leaflet CSS --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" crossorigin="">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" crossorigin="">

    {{-- i18n for JavaScript --}}
    <script>
        window.i18n = {
            statusApproved:         @js(__('🔴 已回報')),
            statusReported1999:     @js(__('🟡 已通報 1999')),
            statusResolved:         @js(__('🟢 已處理完畢')),
            poisonReport:           @js(__('☠️ 毒餌通報')),
            reportPhoto:            @js(__('回報照片')),
            searchPlaceholderRadar: @js(__('搜尋地標或路名…(尚不支援詳細地址)')),
            searchPlaceholderPin:   @js(__('搜尋地標或路名…(暫不支援門牌號碼)')),
            myLocation:             @js(__('📍 我的位置')),
            getGPS:                 @js(__('取得目前 GPS 位置')),
            locating:               @js(__('定位中…')),
            ratLayerLabel:          @js(__('🐀 鼠蹤熱區')),
            poisonLayerLabel:       @js(__('☠️ 毒餌分佈')),
        };
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    {{-- LIFF SDK（僅在設定了 LIFF_ID 時載入） --}}
    @if(config('services.line.liff_id'))
    <script charset="utf-8" src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
    <script>
        window.__LIFF_ID__ = @js(config('services.line.liff_id'));
        window.__AUTHED__  = @js(auth()->check());
    </script>
    @endif
</head>
<body class="bg-[#0d1117] text-slate-100 antialiased min-h-dvh">
    {{ $slot ?? '' }}
    @yield('content')

    @livewireScripts
</body>
</html>
