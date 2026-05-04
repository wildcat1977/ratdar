<?php

namespace App\Models;

use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    protected static function booted(): void
    {
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
