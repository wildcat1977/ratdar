# Mouse Radar 見鼠地圖

> 一個基於地理位置的城市鼠患通報 Web App。極簡操作、暗黑風雷達熱力圖。

## 技術堆疊

| 領域       | 套件 / 版本                                            |
| ---------- | ------------------------------------------------------ |
| 後端框架   | Laravel 13.7 / PHP 8.5.5                               |
| 前端互動   | Livewire 4.1 (SFC 格式)                                |
| CSS        | Tailwind CSS 4 + Vite 8                                |
| 地圖       | Leaflet 1.9 + leaflet.heat + leaflet-control-geocoder  |
| 認證       | Laravel Socialite 5 (Google OAuth + LINE LIFF)         |
| 後台       | Filament 5                                             |
| 佇列/排程  | Laravel Horizon 5 (Redis)                              |
| 資料庫     | PostgreSQL（預設，`.env` 中 `DB_CONNECTION=pgsql`）    |
| 圖片處理   | intervention/image 4（GdDriver）                       |
| AI 審核    | Claude Haiku 4.5 Vision API（Guzzle 呼叫）             |

## 快速啟動

```bash
cd /var/www/html/mouse_radar

# 1. 環境變數
cp .env.example .env
php artisan key:generate
# 必填：DB_* / GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET / GOOGLE_REDIRECT_URI
# 選填：LINE_CLIENT_ID / LINE_CLIENT_SECRET / LINE_REDIRECT_URI / LIFF_ID
# 選填：CLAUDE_API_KEY（未填時照片自動通過審核）

# 2. 資料庫
php artisan migrate

# 3. Filament 後台帳號
php artisan make:filament-user

# 4. 前端資產
npm install
chmod +x node_modules/.bin/*   # Linux 環境可能需要
npm run build                  # 或 npm run dev（本機開發）

# 5. 啟動
php artisan serve
php artisan horizon            # 另一 terminal（選用，處理佇列）
```

開啟：

- 主畫面    <http://localhost:8000>
- 通報清單  <http://localhost:8000/reports>
- 英雄榜    <http://localhost:8000/leaderboard>
- 後台      <http://localhost:8000/admin>
- 佇列監控  <http://localhost:8000/horizon>

## 目錄總覽

```
app/
├── Console/Commands/
│   └── BackfillReportAddresses.php  # 對既有通報批次補跑 reverse geocode
├── Filament/Resources/
│   ├── Reports/                     # 通報後台（含地圖選點 + 儲存後自動更新地址）
│   └── Users/
├── Http/Controllers/Auth/
│   └── SocialiteController.php      # Google / LINE OAuth + LINE LIFF callback
├── Models/
│   ├── Report.php                   # visible scope / 狀態常數
│   └── User.php
├── Services/
│   └── ImageModerationService.php   # Claude Haiku Vision API 圖片審核
└── Providers/AppServiceProvider     # 註冊 LINE Socialite Provider

resources/
├── css/app.css                      # Tailwind 4 + Leaflet geocoder + 雷達動畫
├── js/app.js                        # Leaflet / heatmap / geocoder / LIFF 偵測
└── views/
    ├── layouts/app.blade.php        # 全域 layout（含 LIFF SDK 條件載入）
    ├── pages/
    │   ├── radar.blade.php          # 主雷達地圖頁
    │   ├── reports.blade.php        # 公開通報清單頁
    │   ├── leaderboard.blade.php    # 英雄榜
    │   ├── profile.blade.php        # 個人頁
    │   └── share.blade.php          # 社群分享 OG 頁
    ├── livewire/
    │   ├── radar-map.blade.php      # 地圖 Livewire SFC
    │   ├── report-form.blade.php    # 通報表單（照片選填 / GPS / AI 審核）
    │   ├── report-list.blade.php    # 通報清單（分頁 + 篩選 + CSV 匯出）
    │   ├── auth-onboarding.blade.php# 登入引導（Google + LINE）
    │   ├── leaderboard.blade.php
    │   └── user-profile.blade.php
    └── filament/components/
        └── map-picker.blade.php     # Filament 後台地圖選點元件

config/
├── radar.php                        # 預設地圖中心、雷達半徑、自動審核開關
└── services.php                     # Google / LINE / LIFF / Claude 設定

database/migrations/                 # 含 address 欄位（reports 表）

routes/web.php                       # 含 CSV 匯出路由、LINE LIFF callback 路由
docs/
```

## 常用 Artisan 指令

```bash
# 對缺少地址的通報批次補跑 reverse geocode（Nominatim，1 req/sec）
php artisan reports:backfill-addresses

# 強制覆蓋所有通報的地址
php artisan reports:backfill-addresses --force
```

## 環境變數說明

| 變數                  | 說明                                          | 必填 |
| --------------------- | --------------------------------------------- | ---- |
| `DB_CONNECTION`       | `pgsql`（PostgreSQL）                         | ✓    |
| `GOOGLE_CLIENT_ID`    | Google OAuth Client ID                        | ✓    |
| `GOOGLE_CLIENT_SECRET`| Google OAuth Secret                           | ✓    |
| `GOOGLE_REDIRECT_URI` | 須與 Google Console 設定一致                  | ✓    |
| `LINE_CLIENT_ID`      | LINE Login Channel ID                         |      |
| `LINE_CLIENT_SECRET`  | LINE Login Channel Secret                     |      |
| `LINE_REDIRECT_URI`   | LINE callback URL                             |      |
| `LIFF_ID`             | LINE LIFF App ID（in-app 瀏覽器自動登入用）   |      |
| `CLAUDE_API_KEY`      | Anthropic API Key（未填時照片直接通過審核）   |      |
| `RADAR_AUTO_APPROVE`  | `true` 可讓通報跳過人工審核直接上架           |      |

## 文件索引

- [docs/architecture.md](docs/architecture.md)
- [docs/database-schema.md](docs/database-schema.md)
- [docs/features.md](docs/features.md)
- [docs/deployment.md](docs/deployment.md)
