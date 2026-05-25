<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $game_room_id
 * @property int|null $user_id
 * @property string $type
 * @property numeric $amount
 * @property string|null $reference
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\GameRoom $room
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomTransaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomTransaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomTransaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomTransaction whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomTransaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomTransaction whereGameRoomId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomTransaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomTransaction whereReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomTransaction whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomTransaction whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomTransaction whereUserId($value)
 * @mixin \Eloquent
 */
class GameRoomTransaction extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'integer',
        'game_room_id' => 'integer',
        'user_id' => 'integer',
        'type' => 'string',
        'amount' => 'decimal:2',
        'reference' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(GameRoom::class, 'game_room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
