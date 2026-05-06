<?php

namespace App\Models;

use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;
    public const STATUS_PENDING       = 'pending';
    public const STATUS_APPROVED      = 'approved';
    public const STATUS_REJECTED      = 'rejected';
    public const STATUS_REPORTED_1999 = 'reported_1999';
    public const STATUS_RESOLVED      = 'resolved';

    /** 通報類型 */
    public const TYPE_RAT    = 'rat';     // 發現鼠蹤
    public const TYPE_POISON = 'poison';  // 發現毒餌 / 老鼠藥

    /** 預設座標（未提供位置授權時使用） */
    public const DEFAULT_LATITUDE  = 25.0330000;
    public const DEFAULT_LONGITUDE = 121.5654000;

    public const TYPES = [
        self::TYPE_RAT,
        self::TYPE_POISON,
    ];

    /** 拒絕原因列表（key 儲存於 DB，value 顯示用） */
    public const REJECTION_REASONS = [
        'duplicate'      => '重複通報（鄰近已有相同回報）',
        'outdated'       => '資訊過時（時間過長）',
        'invalid_image'  => '照片不符（無法驗證鼠蹤／毒餌）',
        'wrong_location' => '位置異常（座標不合理）',
        'insufficient'   => '資料過少無法驗證，煩請補充地點資訊或照片後再次回報',
        'spam'           => '不實／惡意通報',
        'other'          => '其他',
    ];

    /** 地圖上可公開顯示的狀態（除 pending/rejected 以外皆顯示） */
    public const VISIBLE_STATUSES = [
        self::STATUS_APPROVED,
        self::STATUS_REPORTED_1999,
        self::STATUS_RESOLVED,
    ];

    protected $fillable = [
        'user_id',
        'type',
        'latitude',
        'longitude',
        'address',
        'image_path',
        'description',
        'status',
        'rejection_reason',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude'    => 'decimal:7',
            'longitude'   => 'decimal:7',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /** 同一使用者的所有回報（自我關聯，供 withCount 使用） */
    public function userReports(): HasMany
    {
        return $this->hasMany(static::class, 'user_id', 'user_id');
    }

    protected static function booted(): void
    {
        // 狀態從 pending 改為其他時，自動記錄審核時間
        static::updating(function (Report $report) {
            if ($report->isDirty('status') && $report->status !== self::STATUS_PENDING) {
                $report->reviewed_at = now();
            }
        });

        static::saved(function () {
            Cache::forget('report_list.all_coords');
            Cache::forget('report_list.district_stats');
        });
        static::deleted(function () {
            Cache::forget('report_list.all_coords');
            Cache::forget('report_list.district_stats');
        });
    }

    /**
     * Reports 可公開顯示於熱力圖（approved、已通報、已處理）
     */
    public function scopeVisible($query)
    {
        return $query->whereIn('status', self::VISIBLE_STATUSES);
    }

    public function scopeRats($query)
    {
        return $query->where('type', self::TYPE_RAT);
    }

    public function scopePoisons($query)
    {
        return $query->where('type', self::TYPE_POISON);
    }
}
