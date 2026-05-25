<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $creator_id
 * @property numeric $bet_amount
 * @property numeric $platform_fee_percent
 * @property string $status
 * @property string $mode
 * @property int|null $winner_id
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GameRoomMove> $moves
 * @property-read int|null $moves_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GameRoomParticipant> $participants
 * @property-read int|null $participants_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GameRoomParticipant> $players
 * @property-read int|null $players_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GameRoomParticipant> $spectators
 * @property-read int|null $spectators_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GameRoomTransaction> $transactions
 * @property-read int|null $transactions_count
 * @property-read \App\Models\User|null $winner
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoom availableForJoin()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoom newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoom newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoom query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoom whereBetAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoom whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoom whereCreatorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoom whereFinishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoom whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoom whereMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoom wherePlatformFeePercent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoom whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoom whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoom whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameRoom whereWinnerId($value)
 * @mixin \Eloquent
 */
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
