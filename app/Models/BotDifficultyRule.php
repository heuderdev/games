<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotDifficultyRule extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'date'     => 'date',
        'active'   => 'boolean',
    ];
}
