<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutboundDispatch extends Model
{
    protected $fillable = ['user_id', 'channel', 'subject', 'body', 'status', 'error', 'created_by'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
