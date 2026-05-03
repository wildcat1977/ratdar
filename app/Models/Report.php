<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    public const STATUS_PENDING       = 'pending';
    public const STATUS_APPROVED      = 'approved';
    public const STATUS_REJECTED      = 'rejected';
    public const STATUS_REPORTED_1999 = 'reported_1999';
    public const STATUS_RESOLVED      = 'resolved';

    /** 地圖上可公開顯示的狀態（除 pending/rejected 以外皆顯示） */
    public const VISIBLE_STATUSES = [
        self::STATUS_APPROVED,
        self::STATUS_REPORTED_1999,
        self::STATUS_RESOLVED,
    ];

    protected $fillable = [
        'user_id',
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

    /**
     * Reports 可公開顯示於熱力圖（approved、已通報、已處理）
     */
    public function scopeVisible($query)
    {
        return $query->whereIn('status', self::VISIBLE_STATUSES);
    }
}
