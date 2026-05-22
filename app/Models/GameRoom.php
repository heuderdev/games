<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameRoom extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'bet_amount' => 'decimal:2',
        'platform_fee_percent' => 'decimal:2',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(GameRoomParticipant::class);
    }

    public function players(): HasMany
    {
        return $this->participants()->where('role', 'player');
    }

    public function spectators(): HasMany
    {
        return $this->participants()->where('role', 'spectator');
    }

    public function moves(): HasMany
    {
        return $this->hasMany(GameRoomMove::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(GameRoomTransaction::class);
    }

    public function isWaiting(): bool
    {
        return $this->status === 'waiting';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isFinished(): bool
    {
        return $this->status === 'finished';
    }

    public function scopeAvailableForJoin($query)
    {
        return $query
            ->where('status', 'waiting');
    }
}
