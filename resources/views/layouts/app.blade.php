<!DOCTYPE html>
<html lang="zh-Hant" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '見鼠地圖 Rat Radar | 台北城任務一起尋找老鼠')</title>

    {{-- SEO / OG --}}
    <meta name="description" content="城市防衛啟動！立刻開啟雷達，通報台北市各角落的鼠患蹤跡。">
    <meta name="theme-color" content="#0d1117">

    <meta property="og:title" content="見鼠地圖 | 台北城任務一起尋找老鼠">
    <meta property="og:description" content="城市防衛啟動！立刻開啟雷達，通報台北市各角落的鼠患蹤跡，為城市安全盡一份心力。">
    <meta property="og:image" content="https://ratdar.taipei/og-image.png">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary_large_image">

    {{-- Google Analytics --}}
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-6Z80TY5CE0"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-6Z80TY5CE0');
    </script>

    {{-- Leaflet CSS --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" crossorigin="">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" crossorigin="">

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
