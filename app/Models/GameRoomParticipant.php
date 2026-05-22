<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameRoomParticipant extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'joined_at' => 'datetime',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(GameRoom::class, 'game_room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPlayer(): bool
    {
        return $this->role === 'player';
    }

    public function isSpectator(): bool
    {
        return $this->role === 'spectator';
    }
}
