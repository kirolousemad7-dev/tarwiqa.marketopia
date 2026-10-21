<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupporterSession extends Model
{
    protected $fillable = ['user_id', 'started_at', 'ended_at', 'last_activity_at', 'ip_address'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOnline(): bool
    {
        if ($this->ended_at !== null) {
            return false;
        }
        $seen = $this->last_activity_at ?? $this->started_at;

        return $seen !== null && $seen->greaterThanOrEqualTo(now()->subMinutes(15));
    }

    public function durationMinutes(?\DateTimeInterface $now = null): int
    {
        if ($this->started_at === null) {
            return 0;
        }
        $end = $this->ended_at ?? $now ?? now();

        return (int) abs($this->started_at->diffInMinutes($end));
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }
}
