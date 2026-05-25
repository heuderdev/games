<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $date
 * @property int|null $weekday
 * @property string $start_time
 * @property string $end_time
 * @property string $difficulty
 * @property int $priority
 * @property bool $active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BotDifficultyRule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BotDifficultyRule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BotDifficultyRule query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BotDifficultyRule whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BotDifficultyRule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BotDifficultyRule whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BotDifficultyRule whereDifficulty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BotDifficultyRule whereEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BotDifficultyRule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BotDifficultyRule wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BotDifficultyRule whereStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BotDifficultyRule whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BotDifficultyRule whereWeekday($value)
 * @mixin \Eloquent
 */
class BotDifficultyRule extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'date'     => 'date',
        'active'   => 'boolean',
    ];
}
