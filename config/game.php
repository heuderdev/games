<?php
return [
    'platform_fee_percent' => (float) env('GAME_PLATFORM_FEE_PERCENT', 20),
    'bot_difficulty'       =>  env('BOT_DIFFICULTY', 'easy'),
    'bot_daily_target'     => (float) env('BOT_DAILY_TARGET', 1000),
    'bot_target_ratio'     => (float) env('BOT_TARGET_RATIO', 0.6),
];
