<?php

use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\GmailAuthController;
use App\Http\Controllers\ShareController;
use App\Http\Middleware\SetLocale;
use App\Models\Report;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/locale/{locale}', function (string $locale) {
    if (in_array($locale, SetLocale::SUPPORTED, true)) {
        session(['locale' => $locale]);
    }

    return redirect()->back();
})->name('locale.set');

Route::view('/', 'pages.radar')->name('home');

Route::view('/reports', 'pages.reports')->name('reports');

Route::get('/reports/export', function () {
    $reports = Report::query()
        ->with('user:id,name')
        ->whereNotIn('status', [Report::STATUS_REJECTED])
        ->latest()
        ->get();

    $statusLabels = [
        Report::STATUS_PENDING => '待審核',
        Report::STATUS_APPROVED => '已審核',
        Report::STATUS_REPORTED_1999 => '已通報1999',
        Report::STATUS_RESOLVED => '已處理',
    ];

    $typeLabels = [
        Report::TYPE_RAT => '鼠蹤',
        Report::TYPE_POISON => '毒餌',
    ];

    $filename = '鼠蹤通報清單_'.now()->format('Ymd_His').'.csv';

    $headers = [
        'Content-Type' => 'text/csv; charset=UTF-8',
        'Content-Disposition' => 'attachment; filename="'.$filename.'"',
    ];

    $callback = function () use ($reports, $statusLabels, $typeLabels) {
        $handle = fopen('php://output', 'w');
        fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
        fputcsv($handle, ['通報時間', '類型', '地點', '狀態', '說明', '照片網址', '緯度', '經度']);

        foreach ($reports as $report) {
            fputcsv($handle, [
                $report->created_at->format('Y-m-d H:i'),
                $typeLabels[$report->type] ?? $report->type,
                $report->address ?: '',
                $statusLabels[$report->status] ?? $report->status,
                $report->description ?? '',
                $report->image_path ? Storage::disk('public')->url($report->image_path) : '',
                (string) $report->latitude,
                (string) $report->longitude,
            ]);
        }

        fclose($handle);
    };

    return response()->stream($callback, 200, $headers);
})->name('reports.export');

Route::middleware('auth')->group(function () {
    Route::view('/profile', 'pages.profile')->name('profile');

    // Gmail OAuth2 授權（管理員用）
    Route::get('/admin/gmail/authorize', [GmailAuthController::class, 'authorize'])
        ->name('admin.gmail.authorize');
    Route::get('/admin/gmail/callback', [GmailAuthController::class, 'callback'])
        ->name('admin.gmail.callback');
});

Route::view('/leaderboard', 'pages.leaderboard')->name('leaderboard');

Route::get('/transparency', function () {
    $stats = Cache::remember('transparency.stats', 3600, function () {
        $rejectionBreakdown = DB::table('reports')
            ->where('status', 'rejected')
            ->whereNotNull('rejection_reason')
            ->selectRaw('rejection_reason, COUNT(*) as c')
            ->groupBy('rejection_reason')
            ->orderByDesc('c')
            ->pluck('c', 'rejection_reason')
            ->all(); // plain array to avoid Collection serialization issues

        // 週統計：每週 ISO 起始日 + 通報總數
        $weekly = DB::table('reports')
            ->selectRaw("TO_CHAR(DATE_TRUNC('week', created_at AT TIME ZONE 'UTC' AT TIME ZONE 'Asia/Taipei'), 'YYYY-MM-DD') AS label, COUNT(*) AS total")
            ->groupByRaw("DATE_TRUNC('week', created_at AT TIME ZONE 'UTC' AT TIME ZONE 'Asia/Taipei')")
            ->orderByRaw("DATE_TRUNC('week', created_at AT TIME ZONE 'UTC' AT TIME ZONE 'Asia/Taipei')")
            ->get()
            ->map(fn ($r) => ['label' => $r->label, 'total' => (int) $r->total])
            ->values()
            ->all();

        // 日統計：近 90 天每日通報數
        $daily = DB::table('reports')
            ->selectRaw("TO_CHAR((created_at AT TIME ZONE 'UTC' AT TIME ZONE 'Asia/Taipei')::date, 'YYYY-MM-DD') AS label, COUNT(*) AS total")
            ->where('created_at', '>=', now()->subDays(90))
            ->groupByRaw("(created_at AT TIME ZONE 'UTC' AT TIME ZONE 'Asia/Taipei')::date")
            ->orderByRaw("(created_at AT TIME ZONE 'UTC' AT TIME ZONE 'Asia/Taipei')::date")
            ->get()
            ->map(fn ($r) => ['label' => $r->label, 'total' => (int) $r->total])
            ->values()
            ->all();

        // 地圖散點資料：已公開的通報（lat/lng/type/timestamp），用於時間軸地圖
        $mapPoints = DB::table('reports')
            ->whereIn('status', ['approved', 'reported_1999', 'resolved'])
            ->selectRaw('latitude::float AS lat, longitude::float AS lng, type, EXTRACT(EPOCH FROM created_at)::int AS ts')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($r) => [(float) $r->lat, (float) $r->lng, $r->type, (int) $r->ts])
            ->values()
            ->all();

        return [
            'total' => Report::count(),
            'approved' => Report::where('status', Report::STATUS_APPROVED)->count(),
            'pending' => Report::where('status', Report::STATUS_PENDING)->count(),
            'rejected' => Report::where('status', Report::STATUS_REJECTED)->count(),
            'reported_1999' => Report::where('status', Report::STATUS_REPORTED_1999)->count(),
            'resolved' => Report::where('status', Report::STATUS_RESOLVED)->count(),
            'ai_auto' => (int) ($rejectionBreakdown['ai_auto'] ?? 0),
            'avg_review_hrs' => (float) (DB::table('reports')
                ->whereNotNull('reviewed_at')
                ->selectRaw('AVG(EXTRACT(EPOCH FROM (reviewed_at - created_at))/3600) AS avg_hrs')
                ->value('avg_hrs') ?? 0),
            'rejection_breakdown' => $rejectionBreakdown,
            'weekly' => $weekly,
            'daily' => $daily,
            'map_points' => $mapPoints,
        ];
    });

    return view('pages.transparency', compact('stats'));
})->name('transparency');

Route::get('/share/{user}', [ShareController::class, 'show'])->name('share.show');
Route::get('/share/{user}/og.jpg', [ShareController::class, 'image'])->name('share.image');

Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('home');
})->name('logout');

Route::middleware('guest')->group(function () {
    Route::get('/auth/{provider}/redirect', [SocialiteController::class, 'redirect'])
        ->whereIn('provider', ['google', 'line'])
        ->name('auth.redirect');

    Route::get('/auth/{provider}/callback', [SocialiteController::class, 'callback'])
        ->whereIn('provider', ['google', 'line'])
        ->name('auth.callback');

    // LIFF 內建瀏覽器專用 callback（不需 Socialite OAuth，直接用 access token）
    Route::get('/auth/line/liff-callback', [SocialiteController::class, 'liffCallback'])
        ->name('auth.line.liff');

    // 後台專用 Google OAuth 入口（共用同一個 callback URL，以 session flag 區分）
    Route::get('/admin/auth/google', [SocialiteController::class, 'adminRedirect'])
        ->name('admin.auth.google.redirect');
});
