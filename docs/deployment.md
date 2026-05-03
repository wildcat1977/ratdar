# 部署 & 第三方設定

## 系統需求

- PHP 8.3+（已測 PHP 8.5）
- Composer 2.x
- Node.js 20+
- Redis 6+
- MySQL 8+ / PostgreSQL 15+
- 必要 PHP extensions: `pdo_mysql`（或 `pdo_pgsql`）, `mbstring`, `gd`/`imagick`, `redis`, `intl`, `bcmath`

## 環境變數重點

`.env` 中需要實際填寫的：

```dotenv
APP_URL=https://mouseradar.example.com   # 必須含 https，影響 OAuth redirect

DB_CONNECTION=mysql
DB_DATABASE=mouse_radar
DB_USERNAME=...
DB_PASSWORD=...

REDIS_HOST=127.0.0.1

# Google Cloud Console → OAuth 2.0 Client (Web application)
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"

# LINE Developers → LINE Login Channel
LINE_CLIENT_ID=...      # Channel ID
LINE_CLIENT_SECRET=...  # Channel secret
LINE_REDIRECT_URI="${APP_URL}/auth/line/callback"
```

## Socialite Provider 設定

### Google
1. 前往 <https://console.cloud.google.com/apis/credentials>
2. 建立 OAuth 2.0 Client ID（Web application）
3. Authorized redirect URI 填入 `${APP_URL}/auth/google/callback`

### LINE
1. 前往 <https://developers.line.biz/console/>
2. 建立 LINE Login Channel
3. Callback URL 填入 `${APP_URL}/auth/line/callback`
4. 啟用 `profile` scope（預設）；若要 email 需開啟 `openid email` 並送審

## 部署步驟

```bash
git clone <repo> mouse_radar && cd mouse_radar
composer install --no-dev --optimize-autoloader
cp .env.example .env && nano .env
php artisan key:generate
php artisan migrate --force
php artisan storage:link
npm ci && npm run build

# Filament admin
php artisan make:filament-user

# Cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:cache-components
```

### Horizon (Supervisor / systemd)

```ini
[program:mouseradar-horizon]
process_name=%(program_name)s
command=php /var/www/html/mouse_radar/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/mouseradar-horizon.log
stopwaitsecs=3600
```

### Nginx 範例（主要路徑）

```nginx
server {
    listen 443 ssl http2;
    server_name mouseradar.example.com;
    root /var/www/html/mouse_radar/public;

    index index.php;
    client_max_body_size 12M;   # 允許照片上傳

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
}
```

## Filament Admin 訪問控制

預設 Filament v5 會在 `production` 環境拒絕未實作 `FilamentUser` 的使用者。
若你的 admin 想限定特定 email：

```php
// app/Models/User.php
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    public function canAccessPanel(Panel $panel): bool
    {
        return str_ends_with($this->email, '@mouseradar.tw');
    }
}
```

## 安全注意事項

- `image_path` 來自 `storage/app/public`，已透過 `php artisan storage:link` 暴露為 `/storage/...`，請在前端使用 `Storage::url()`
- 上傳檔案類型強制由 Livewire `image` 規則檢查，最大 8MB
- Socialite redirect 受 `whereIn('provider', [...])` 限制，避免任意 provider 注入
- `provider+provider_id` 唯一鍵，避免帳戶綁架
