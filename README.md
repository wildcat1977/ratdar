# Mouse Radar 見鼠地圖

> 一個基於地理位置的城市鼠患通報 Web App。極簡操作、暗黑風雷達熱力圖。

## 技術堆疊

| 領域       | 套件                                      |
| ---------- | ----------------------------------------- |
| 後端框架   | Laravel 13                                |
| 前端互動   | Livewire 4 (Filament 5 相依)              |
| CSS        | Tailwind CSS 4 + Vite 8                   |
| 地圖       | Leaflet 1.9 + leaflet.heat（純前端）      |
| 認證       | Laravel Socialite (Google + LINE)         |
| 後台       | Filament 5                                |
| 佇列/排程  | Laravel Horizon (Redis)                   |
| 資料庫     | MySQL（預設）/ PostgreSQL（可切換）       |

> 規格書原本要求 Livewire 3，但 Filament 5 強制依賴 Livewire 4，因此本專案以 Livewire 4 single-file component 實作。

## 快速啟動

```bash
cd /var/www/html/mouse_radar

# 1. 環境變數
cp .env.example .env
php artisan key:generate
# 填入 DB_* / GOOGLE_* / LINE_*

# 2. 資料庫
php artisan migrate

# 3. Filament 後台帳號
php artisan make:filament-user

# 4. 前端資產
npm install
npm run build       # 或 npm run dev (本機開發)

# 5. 啟動
php artisan serve
php artisan horizon  # 另一 terminal
```

開啟：

- 主畫面 <http://localhost:8000>
- 後台   <http://localhost:8000/admin>
- 佇列   <http://localhost:8000/horizon>

## 目錄總覽

```
app/
├── Filament/Resources/Reports/   # Filament 後台 (Reports)
├── Http/Controllers/Auth/        # Socialite 登入回呼
├── Models/{Report,User}.php
└── Providers/AppServiceProvider  # 註冊 LINE Socialite Provider

resources/
├── css/app.css                   # Tailwind + radar 動畫
├── js/app.js                     # Leaflet + leaflet.heat
└── views/
    ├── layouts/app.blade.php
    ├── pages/radar.blade.php
    └── components/
        ├── ⚡radar-map.blade.php
        ├── ⚡auth-onboarding.blade.php
        └── ⚡report-form.blade.php

config/radar.php                  # 預設地圖中心 / 雷達半徑
routes/web.php
docs/                             # 規格、架構、部署
```

## 文件索引

- [docs/architecture.md](docs/architecture.md)
- [docs/database-schema.md](docs/database-schema.md)
- [docs/features.md](docs/features.md)
- [docs/deployment.md](docs/deployment.md)
- [docs/development.md](docs/development.md)
