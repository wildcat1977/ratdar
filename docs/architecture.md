# 系統架構

```
┌──────────────────────────────────────────────────────────────┐
│                        Browser (Mobile-first)                │
│  ┌──────────────┐  ┌─────────────────┐  ┌────────────────┐   │
│  │ Leaflet Map  │  │ Livewire Modals │  │ Geolocation API│   │
│  │ + heat layer │  │ (Report / Auth) │  │ + Camera input │   │
│  └──────┬───────┘  └────────┬────────┘  └────────┬───────┘   │
└─────────┼───────────────────┼────────────────────┼───────────┘
          │ HTTP/Vite          │ Livewire (XHR)    │ navigator.*
          ▼                    ▼                    ▼
┌──────────────────────────────────────────────────────────────┐
│                     Laravel 13 Application                   │
│  ┌─────────────┐ ┌──────────────┐ ┌─────────────────────┐    │
│  │ web routes  │→│ Livewire SFCs│→│ Eloquent (Report)   │    │
│  │             │ │ radar-map /  │ │ + WithFileUploads → │    │
│  │ socialite   │ │ report-form /│ │   storage/public    │    │
│  │ /auth/...   │ │ auth-onboard │ │                     │    │
│  └─────────────┘ └──────────────┘ └─────────────────────┘    │
│  ┌─────────────┐ ┌──────────────┐ ┌─────────────────────┐    │
│  │ Horizon     │ │ Filament v5  │ │ Socialite (Google,  │    │
│  │ (Redis Q)   │ │ /admin Panel │ │  LINE)              │    │
│  └─────────────┘ └──────────────┘ └─────────────────────┘    │
└────────┬────────────────────┬─────────────────────────────┘
         ▼                    ▼
   ┌──────────┐          ┌─────────┐
   │  Redis   │          │  MySQL  │  (or PostgreSQL)
   │ Queue/Cache│        │         │
   └──────────┘          └─────────┘
```

## 關鍵流程

### 1. 進入首頁 → 看到熱力圖
1. `routes/web.php` `/` 渲染 `pages/radar.blade.php`
2. `<livewire:radar-map />` 把 `Report::visible()` 的 (lat,lng) 序列化進 `data-heat-points`
3. `resources/js/app.js` 在 `livewire:initialized` 時，用 Leaflet + leaflet.heat 渲染暗黑底圖與熱力圖
4. 同時呼叫 `navigator.geolocation` 計算「周邊 1km」總數

### 2. 點擊「立即回報」
1. 按鈕 dispatch `mouseradar:report-clicked` (DOM Event)
2. JS 監聽後呼叫 `Livewire.dispatch('open-report-form')`
3. `ReportForm` 元件 `show()`：
   - 已登入 → `open=true` 顯示 modal
   - 未登入 → 改 dispatch `open-auth-onboarding`，由 `AuthOnboarding` 顯示登入引導

### 3. Socialite 登入
1. 點 LINE / Google → `GET /auth/{provider}/redirect` → OAuth
2. Provider 回呼 `GET /auth/{provider}/callback`
3. `SocialiteController` 比對 `provider+provider_id` → 找不到才用 email fallback；否則建立新使用者
4. `Auth::login()` 後 `redirect('home')->with('open_report_form', true)`
5. 首頁偵測 flash 後 dispatch `open-report-form`，自動開啟回報 modal

### 4. 通報送出
1. `ReportForm::submit()` 驗證欄位，將圖片存至 `storage/app/public/reports/`
2. 寫入 `reports` table（status 預設 `pending`）
3. dispatch `report-submitted` → `RadarMap::refreshHeat()` 抓最新點，再 dispatch DOM event `mouseradar:heat-updated`
4. JS 呼叫 `heatLayer.setLatLngs(points)` 即時刷新

## 為何用 Livewire 4 而非 3
Filament 5 強制 livewire/livewire ^4.1。Livewire 4 的 single-file component (SFC) 約定是 `resources/views/components/⚡{name}.blade.php`，PHP 與 Blade 同檔案。

## 為何在前端做距離計算
為避免後端針對「周邊 1km」做 DB 查詢造成熱點抖動，我們把全部 approved 點位（限 5000）一次送到前端，再以 Haversine 公式即時計算。當資料量超過 5000 時應改為：
- DB 端 spatial index（PostGIS / MySQL ST_Distance_Sphere）
- 或 server-side bbox query + 前端 cluster
