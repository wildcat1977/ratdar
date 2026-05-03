<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    protected $fillable = [
        'user_id', 'name', 'email', 'subject', 'message',
        'status', 'reply', 'replied_at',
    ];

    protected $casts = [
        'replied_at' => 'datetime',
    ];

    const STATUS_UNREAD  = 'unread';
    const STATUS_READ    = 'read';
    const STATUS_REPLIED = 'replied';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
