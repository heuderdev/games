<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $game_room_id
 * @property int $user_id
 * @property int $cell
 * @property int $turn_number
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\GameRoom $room
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomMove newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomMove newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomMove query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomMove whereCell($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomMove whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomMove whereGameRoomId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomMove whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomMove whereTurnNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomMove whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoomMove whereUserId($value)
 * @mixin \Eloquent
 */
class GameRoomMove extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'game_room_id' => 'integer',
        'user_id' => 'integer',
        'cell' => 'integer',
        'turn_number' => 'integer',
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
