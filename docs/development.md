# 本地開發

## 一鍵啟動

```bash
composer dev
```

`composer.json` 內建腳本會用 [concurrently](https://www.npmjs.com/package/concurrently) 同時跑：

- `php artisan serve` — Laravel server
- `php artisan queue:listen` — Queue worker
- `php artisan pail` — 即時 log 監看
- `npm run dev` — Vite HMR

## 個別命令

```bash
php artisan serve              # 8000 port
npm run dev                    # Vite, 5173 port
php artisan horizon            # Production-like queue dashboard
php artisan filament:upgrade   # Filament 升級後執行
php artisan livewire:upgrade   # Livewire 升級後執行
```

## 撰寫 Livewire 4 組件

本專案使用 single-file component (SFC) 寫法。檔名一律以閃電 emoji 開頭：

```
resources/views/components/⚡component-name.blade.php
```

```php
<?php

use Livewire\Component;

new class extends Component
{
    public string $msg = 'Hi';
}; ?>

<div>{{ $msg }}</div>
```

引用：

```blade
<livewire:component-name />
```

## 測試

```bash
php artisan test
```

## 程式碼風格

```bash
./vendor/bin/pint
```

## Tip：手機實機測試

開 Vite 時用區網 IP：

```bash
php artisan serve --host=0.0.0.0
npm run dev -- --host
```

於 `.env`：

```
APP_URL=http://192.168.x.x:8000
```

地理位置 API 需要 HTTPS 或 `localhost`。實機測試時可用 `mkcert` 做本地憑證，或透過 `ngrok http 8000` 取得 https URL。
