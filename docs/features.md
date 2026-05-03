# 功能與 UI 動線

## Mobile-first 主畫面

```
┌────────────────────────────┐
│ 🔴 周邊 1km：12 筆通報   登出 │ ← 頂部懸浮 (z-500)
│                            │
│                            │
│       🗺  全螢幕暗黑地圖    │ ← Leaflet (z-0)
│        + 紅色熱力圖         │
│                            │
│                            │
│  ⚠️ 立即回報米奇            │ ← 底部主 CTA
│  功能說明  ©2026  回報紀錄  │ ← Footer
└────────────────────────────┘
```

## 動線

### A. 訪客看雷達
1. 進首頁，瀏覽器自動詢問定位
2. 看到 approved 通報的熱力分佈

### B. 訪客通報
1. 點「立即回報米奇」
2. 觸發 `AuthOnboarding` 抽屜（底部彈出）
3. 顯示 3 步驟說明 + 隱私權文案 + LINE / Google 登入按鈕
4. 登入成功 → 自動回首頁並開啟回報表單

### C. 已登入使用者通報
1. 點「立即回報米奇」 → 直接開啟 `ReportForm`
2. 表單欄位：
   - 📸 照片上傳（`accept="image/*" capture="environment"` 直接呼叫相機）
   - 📍 位置（小地圖，可拖曳圖釘微調）
   - 📝 備註（選填）
3. 送出 → 顯示「📡 通報成功」動畫 → 關閉 modal
4. 主畫面熱力圖即時刷新（前端 dispatch `heat-updated`）

## 設計守則

| 規則              | 細節                                   |
| ----------------- | -------------------------------------- |
| 主題色            | 背景 `#0d1117`，主強調紅 `#ef4444`     |
| 安全區域          | `pb-[max(1rem,env(safe-area-inset-bottom))]` 處理 iOS 圓角 |
| 動畫              | `radar-pulse` keyframes (CSS)          |
| Modal             | 手機從底滑入 (`items-end`)，平板置中   |
| 字體              | Inter + Noto Sans TC                   |

## SEO / OG

定義於 `resources/views/layouts/app.blade.php`：

- `og:title` 見鼠地圖 | 台北城任務一起尋找米奇
- `og:description` 城市防衛啟動！立刻開啟雷達，通報台北市各角落的鼠患蹤跡，為城市安全盡一份心力。
- `og:image` `/images/og-mickey-radar.jpg` （請放入 `public/images/`）
- `theme-color` `#0d1117`
