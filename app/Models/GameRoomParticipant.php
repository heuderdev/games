<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $game_room_id
 * @property int $user_id
 * @property string $role
 * @property string|null $symbol
 * @property \Illuminate\Support\Carbon|null $joined_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\GameRoom $room
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomParticipant newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomParticipant newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomParticipant query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomParticipant whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomParticipant whereGameRoomId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomParticipant whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomParticipant whereJoinedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomParticipant whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomParticipant whereSymbol($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomParticipant whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomParticipant whereUserId($value)
 * @mixin \Eloquent
 */
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
