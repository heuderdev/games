<?php

namespace App\Services;

use App\Events\GameRoomUpdated;
use App\Models\BotDifficultyRule;
use App\Models\GameRoom;
use App\Models\GameRoomMove;
use App\Models\GameRoomParticipant;
use App\Models\GameRoomTransaction;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class GameRoomService
{
    public function __construct(
        protected DatabaseManager $db,
        protected MinimaxBotService $bot,
        protected BotDecisionService $botDecision,
    ) {}

    protected function botUserId(): int
    {
        return User::query()->where('email', 'bot@game.local')->value('id');
    }

    protected function dispatchRoomUpdatedAfterCommit(int $roomId): void
    {
        DB::afterCommit(function () use ($roomId) {
            GameRoomUpdated::dispatch($roomId);
        });
    }

    public function assertUserHasNoOpenRoom(User $user): void
    {
        $hasOpenAsCreator = GameRoom::query()
            ->where('creator_id', $user->id)
            ->where(function ($q) {
                $q->where('status', 'waiting')
                    ->orWhere('status', 'active');
            })
            ->exists();

        if ($hasOpenAsCreator) {
            throw ValidationException::withMessages([
                'room' => 'Você já possui uma sala em andamento como criador. Finalize ou cancele antes de entrar em outra.',
            ]);
        }

        $hasOpenAsPlayer = GameRoomParticipant::query()
            ->where('user_id', $user->id)
            ->where('role', 'player')
            ->whereHas('room', function ($q) {
                $q->whereIn('status', ['waiting', 'active']);
            })
            ->exists();

        if ($hasOpenAsPlayer) {
            throw ValidationException::withMessages([
                'room' => 'Você já está participando de uma sala em andamento. Finalize ou cancele antes de entrar em outra.',
            ]);
        }
    }

    public function createRoom(User $creator, float $betAmount, float $platformFeePercent = null): GameRoom
    {
        $this->assertUserHasNoOpenRoom($creator);

        $platformFeePercent ??= config('game.platform_fee_percent');

        if (! $creator->hasGameBalance($betAmount)) {
            throw ValidationException::withMessages([
                'credit' => 'Créditos insuficientes para criar a sala.',
            ]);
        }

        return $this->db->transaction(function () use ($creator, $betAmount, $platformFeePercent) {
            $room = GameRoom::create([
                'creator_id'           => $creator->id,
                'bet_amount'           => $betAmount,
                'platform_fee_percent' => $platformFeePercent,
                'status'               => 'waiting',
                'mode'                 => 'human_vs_human',
            ]);

            GameRoomParticipant::create([
                'game_room_id' => $room->id,
                'user_id'      => $creator->id,
                'role'         => 'player',
                'symbol'       => 'X',
                'joined_at'    => Carbon::now(),
            ]);

            $creator->decrement('carteira_game', $betAmount);

            GameRoomTransaction::create([
                'game_room_id' => $room->id,
                'user_id'      => $creator->id,
                'type'         => 'entry_bet',
                'amount'       => $betAmount,
                'reference'    => 'room:' . $room->id,
            ]);

            $this->dispatchRoomUpdatedAfterCommit($room->id);

            return $room;
        });
    }

    public function createRoomAgainstBot(
        User $creator,
        float $betAmount,
        float $platformFeePercent = null
    ): GameRoom {
        $this->assertUserHasNoOpenRoom($creator);

        $platformFeePercent ??= config('game.platform_fee_percent');

        if (! $creator->hasGameBalance($betAmount)) {
            throw ValidationException::withMessages([
                'credit' => 'Créditos insuficientes para criar a sala.',
            ]);
        }

        $botUserId = $this->botUserId();
        $bot = User::findOrFail($botUserId);

        if (! $bot->hasGameBalance($betAmount)) {
            throw ValidationException::withMessages([
                'bot' => 'Plataforma sem saldo suficiente para jogar. Não se preocupe, o administrador vai ser notificado, para fazer a recarga.',
            ]);
        }

        return $this->db->transaction(function () use ($creator, $bot, $betAmount, $platformFeePercent) {
            $botSymbol = random_int(0, 1) === 1 ? 'X' : 'O';

            $room = GameRoom::create([
                'creator_id'           => $creator->id,
                'bet_amount'           => $betAmount,
                'platform_fee_percent' => $platformFeePercent,
                'status'               => 'active',
                'mode'                 => 'human_vs_bot',
            ]);

            GameRoomParticipant::create([
                'game_room_id' => $room->id,
                'user_id'      => $creator->id,
                'role'         => 'player',
                'symbol'       => $botSymbol === 'X' ? 'O' : 'X',
                'joined_at'    => Carbon::now(),
            ]);

            GameRoomParticipant::create([
                'game_room_id' => $room->id,
                'user_id'      => $bot->id,
                'role'         => 'player',
                'symbol'       => $botSymbol,
                'joined_at'    => Carbon::now(),
            ]);

            $creator->decrement('carteira_game', $betAmount);

            GameRoomTransaction::create([
                'game_room_id' => $room->id,
                'user_id'      => $creator->id,
                'type'         => 'entry_bet',
                'amount'       => $betAmount,
                'reference'    => 'room:' . $room->id,
            ]);

            $bot->decrement('carteira_game', $betAmount);

            GameRoomTransaction::create([
                'game_room_id' => $room->id,
                'user_id'      => $bot->id,
                'type'         => 'entry_bet',
                'amount'       => $betAmount,
                'reference'    => 'room:' . $room->id,
            ]);

            if ($botSymbol === 'X') {
                $this->playBotMove($room);
                $room->refresh();
            }

            $this->dispatchRoomUpdatedAfterCommit($room->id);

            return $room;
        });
    }

    public function joinRoomAsPlayer(GameRoom $room, User $user): GameRoomParticipant
    {
        if ($room->mode !== 'human_vs_human') {
            throw ValidationException::withMessages([
                'room' => 'Esta sala não aceita outro jogador humano.',
            ]);
        }

        $this->assertUserHasNoOpenRoom($user);

        if (! $room->isWaiting()) {
            throw ValidationException::withMessages([
                'room' => 'Sala não está mais disponível para jogadores.',
            ]);
        }

        if ($room->participants()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'room' => 'Você já está nessa sala.',
            ]);
        }

        if ($room->players()->count() >= 2) {
            throw ValidationException::withMessages([
                'room' => 'Sala já possui dois jogadores.',
            ]);
        }

        if (! $user->hasGameBalance($room->bet_amount)) {
            throw ValidationException::withMessages([
                'credit' => 'Créditos insuficientes para entrar na sala.',
            ]);
        }

        return $this->db->transaction(function () use ($room, $user) {
            $lockedRoom = GameRoom::query()
                ->lockForUpdate()
                ->findOrFail($room->id);

            if (! $lockedRoom->isWaiting()) {
                throw ValidationException::withMessages([
                    'room' => 'Sala não está mais disponível para jogadores.',
                ]);
            }

            if ($lockedRoom->participants()->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages([
                    'room' => 'Você já está nessa sala.',
                ]);
            }

            $players = $lockedRoom->players()->lockForUpdate()->get();

            if ($players->count() >= 2) {
                throw ValidationException::withMessages([
                    'room' => 'Sala já possui dois jogadores.',
                ]);
            }

            $creatorPlayer = $players->first();

            if (! $creatorPlayer || ! in_array($creatorPlayer->symbol, ['X', 'O'], true)) {
                throw ValidationException::withMessages([
                    'room' => 'Sala inválida para entrada.',
                ]);
            }

            $symbol = $creatorPlayer->symbol === 'X' ? 'O' : 'X';

            $participant = GameRoomParticipant::create([
                'game_room_id' => $lockedRoom->id,
                'user_id'      => $user->id,
                'role'         => 'player',
                'symbol'       => $symbol,
                'joined_at'    => Carbon::now(),
            ]);

            $user->decrement('carteira_game', $lockedRoom->bet_amount);

            GameRoomTransaction::create([
                'game_room_id' => $lockedRoom->id,
                'user_id'      => $user->id,
                'type'         => 'entry_bet',
                'amount'       => $lockedRoom->bet_amount,
                'reference'    => 'room:' . $lockedRoom->id,
            ]);

            if ($players->count() + 1 === 2) {
                $lockedRoom->update([
                    'status'     => 'active',
                    'started_at' => Carbon::now(),
                ]);
            }

            $this->dispatchRoomUpdatedAfterCommit($lockedRoom->id);

            return $participant;
        });
    }

    public function joinRoomAsSpectator(GameRoom $room, User $user): GameRoomParticipant
    {
        if ($room->participants()->where('user_id', $user->id)->exists()) {
            return $room->participants()->where('user_id', $user->id)->first();
        }

        $participant = GameRoomParticipant::create([
            'game_room_id' => $room->id,
            'user_id'      => $user->id,
            'role'         => 'spectator',
            'symbol'       => null,
            'joined_at'    => Carbon::now(),
        ]);

        $this->dispatchRoomUpdatedAfterCommit($room->id);

        return $participant;
    }

    public function playMove(GameRoom $room, User $user, int $cell): GameRoomMove
    {
        if (! $room->isActive()) {
            throw ValidationException::withMessages([
                'room' => 'Sala não está ativa.',
            ]);
        }

        if ($cell < 0 || $cell > 8) {
            throw ValidationException::withMessages([
                'cell' => 'Posição inválida.',
            ]);
        }

        $participant = $room->participants()
            ->where('user_id', $user->id)
            ->where('role', 'player')
            ->first();

        if (! $participant) {
            throw ValidationException::withMessages([
                'user' => 'Você não é um jogador desta sala.',
            ]);
        }

        return $this->db->transaction(function () use ($room, $user, $participant, $cell) {
            $lockedRoom = GameRoom::query()
                ->lockForUpdate()
                ->findOrFail($room->id);

            if (! $lockedRoom->isActive()) {
                throw ValidationException::withMessages([
                    'room' => 'Sala não está ativa.',
                ]);
            }

            $lockedParticipant = $lockedRoom->participants()
                ->where('user_id', $user->id)
                ->where('role', 'player')
                ->first();

            if (! $lockedParticipant) {
                throw ValidationException::withMessages([
                    'user' => 'Você não é um jogador desta sala.',
                ]);
            }

            $lastMove = $lockedRoom->moves()->orderByDesc('turn_number')->lockForUpdate()->first();
            $nextTurn = $lastMove ? $lastMove->turn_number + 1 : 1;

            $expectedSymbol = $nextTurn % 2 === 1 ? 'X' : 'O';

            if ($lockedParticipant->symbol !== $expectedSymbol) {
                throw ValidationException::withMessages([
                    'turn' => 'Não é a sua vez.',
                ]);
            }

            if ($lockedRoom->moves()->where('cell', $cell)->exists()) {
                throw ValidationException::withMessages([
                    'cell' => 'Casa já ocupada.',
                ]);
            }

            $move = GameRoomMove::create([
                'game_room_id' => $lockedRoom->id,
                'user_id'      => $user->id,
                'cell'         => $cell,
                'turn_number'  => $nextTurn,
            ]);

            $board = $this->buildBoard($lockedRoom);
            $winnerSymbol = $this->checkWinner($board);

            if ($winnerSymbol !== null) {
                $winnerParticipant = $lockedRoom->participants()
                    ->where('role', 'player')
                    ->where('symbol', $winnerSymbol)
                    ->first();

                $this->finishRoom($lockedRoom, $winnerParticipant?->user);
                $this->dispatchRoomUpdatedAfterCommit($lockedRoom->id);

                return $move;
            }

            if ($this->isBoardFull($board)) {
                $this->finishRoom($lockedRoom, null);
                $this->dispatchRoomUpdatedAfterCommit($lockedRoom->id);

                return $move;
            }

            if ($lockedRoom->mode === 'human_vs_bot') {
                $this->playBotMove($lockedRoom);
            }

            $this->dispatchRoomUpdatedAfterCommit($lockedRoom->id);

            return $move;
        });
    }

    protected function determineBotDifficultyForToday(): string
    {
        $botId = $this->botUserId();

        $todayStart = Carbon::today();
        $todayEnd = Carbon::today()->endOfDay();

        $botWinnerPayoutToday = GameRoomTransaction::query()
            ->where('user_id', $botId)
            ->where('type', 'winner_payout')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->sum('amount');

        $target = (float) config('game.bot_daily_target', 1000);
        $targetRatio = (float) config('game.bot_target_ratio', 0.6);
        $targetAmount = $target * $targetRatio;

        if ($targetAmount <= 0) {
            return 'medium';
        }

        $progress = $botWinnerPayoutToday / $targetAmount;

        if ($progress < 0.3) {
            return 'hardcore';
        }

        if ($progress < 0.7) {
            return 'hard';
        }

        if ($progress < 1.0) {
            return 'medium';
        }

        return 'idiot';
    }

    protected function resolveBotDifficulty(): string
    {
        $now = Carbon::now();

        $time = $now->format('H:i:s');
        $todayDate = $now->toDateString();
        $weekday = (int) $now->dayOfWeek;

        $query = BotDifficultyRule::query()
            ->where('active', true)
            ->where(function ($q) use ($todayDate) {
                $q->whereNull('date')
                    ->orWhere('date', $todayDate);
            })
            ->where(function ($q) use ($weekday) {
                $q->whereNull('weekday')
                    ->orWhere('weekday', $weekday);
            })
            ->where('start_time', '<=', $time)
            ->where('end_time', '>=', $time)
            ->orderBy('priority', 'asc')
            ->orderBy('id', 'asc');

        $rule = $query->first();

        if ($rule) {
            Log::info('BotDifficulty: found rule', ['rule' => $rule->toArray()]);
            return $rule->difficulty;
        }

        return $this->determineBotDifficultyForToday();
    }

    protected function playBotMove(GameRoom $room): void
    {
        $room->loadMissing(['participants', 'moves']);

        $botParticipant = $room->participants
            ->where('role', 'player')
            ->firstWhere('user_id', $this->botUserId());

        if (! $botParticipant || ! $botParticipant->symbol) {
            return;
        }

        $botSymbol = $botParticipant->symbol;
        $board = $this->buildBoard($room);

        $winnerSymbol = $this->checkWinner($board);

        if ($winnerSymbol !== null || $this->isBoardFull($board)) {
            return;
        }

        $perfectMove = $this->bot->bestMove($board, $botSymbol);

        if ($perfectMove === null) {
            return;
        }

        $difficulty = $this->resolveBotDifficulty();

        $allowedDifficulties = ['idiot', 'easy', 'medium', 'hard', 'hardcore'];

        if (! in_array($difficulty, $allowedDifficulties, true)) {
            $difficulty = 'easy';
        }

        $bestMove = $this->botDecision->pickMoveWithDifficulty(
            $board,
            $botSymbol,
            $perfectMove,
            $difficulty
        );

        if ($bestMove === null) {
            return;
        }

        $lastMove = $room->moves()->orderByDesc('turn_number')->lockForUpdate()->first();
        $nextTurn = $lastMove ? $lastMove->turn_number + 1 : 1;

        GameRoomMove::create([
            'game_room_id' => $room->id,
            'user_id'      => $botParticipant->user_id,
            'cell'         => $bestMove,
            'turn_number'  => $nextTurn,
        ]);

        $board[$bestMove] = $botSymbol;

        $winnerSymbol = $this->checkWinner($board);

        if ($winnerSymbol !== null) {
            $winnerParticipant = $room->participants
                ->where('role', 'player')
                ->firstWhere('symbol', $winnerSymbol);

            $this->finishRoom($room, $winnerParticipant?->user);
        } elseif ($this->isBoardFull($board)) {
            $this->finishRoom($room, null);
        }
    }

    protected function buildBoard(GameRoom $room): array
    {
        $board = array_fill(0, 9, null);

        foreach ($room->moves()->orderBy('turn_number')->get() as $move) {
            $symbol = $room->participants()
                ->where('user_id', $move->user_id)
                ->value('symbol');

            $board[$move->cell] = $symbol;
        }

        return $board;
    }

    protected function isBoardFull(array $board): bool
    {
        return ! in_array(null, $board, true);
    }

    protected function checkWinner(array $board): ?string
    {
        $wins = [
            [0, 1, 2],
            [3, 4, 5],
            [6, 7, 8],
            [0, 3, 6],
            [1, 4, 7],
            [2, 5, 8],
            [0, 4, 8],
            [2, 4, 6],
        ];

        foreach ($wins as $combo) {
            [$a, $b, $c] = $combo;

            if (
                $board[$a] !== null &&
                $board[$a] === $board[$b] &&
                $board[$b] === $board[$c]
            ) {
                return $board[$a];
            }
        }

        return null;
    }

    public function finishRoom(GameRoom $room, ?User $winner): void
    {
        $this->db->transaction(function () use ($room, $winner) {
            $lockedRoom = GameRoom::query()
                ->lockForUpdate()
                ->findOrFail($room->id);

            if ($lockedRoom->isFinished()) {
                return;
            }

            $players = $lockedRoom->players()->with('user')->lockForUpdate()->get();

            if ($players->count() !== 2) {
                $lockedRoom->update([
                    'status'      => 'finished',
                    'winner_id'   => null,
                    'finished_at' => Carbon::now(),
                ]);

                $this->dispatchRoomUpdatedAfterCommit($lockedRoom->id);

                return;
            }

            $betAmount = $lockedRoom->bet_amount;

            if ($winner) {
                $freshWinner = User::query()
                    ->lockForUpdate()
                    ->findOrFail($winner->id);

                $totalPot = $betAmount * 2;
                $platformFee = round($totalPot * ($lockedRoom->platform_fee_percent / 100), 2);
                $winnerPayout = $totalPot - $platformFee;

                $freshWinner->increment('winnings_balance', $winnerPayout);

                GameRoomTransaction::create([
                    'game_room_id' => $lockedRoom->id,
                    'user_id'      => $freshWinner->id,
                    'type'         => 'winner_payout',
                    'amount'       => $winnerPayout,
                    'reference'    => 'room:' . $lockedRoom->id,
                ]);

                GameRoomTransaction::create([
                    'game_room_id' => $lockedRoom->id,
                    'user_id'      => null,
                    'type'         => 'platform_fee',
                    'amount'       => $platformFee,
                    'reference'    => 'room:' . $lockedRoom->id,
                ]);
            } else {
                foreach ($players as $participant) {
                    $playerUser = $participant->user;

                    if (! $playerUser) {
                        continue;
                    }

                    $freshUser = User::query()
                        ->lockForUpdate()
                        ->findOrFail($playerUser->id);

                    $freshUser->increment('carteira_game', $betAmount);

                    GameRoomTransaction::create([
                        'game_room_id' => $lockedRoom->id,
                        'user_id'      => $freshUser->id,
                        'type'         => 'draw_refund',
                        'amount'       => $betAmount,
                        'reference'    => 'room:' . $lockedRoom->id,
                    ]);
                }
            }

            $lockedRoom->update([
                'status'      => 'finished',
                'winner_id'   => $winner?->id,
                'finished_at' => Carbon::now(),
            ]);

            $this->dispatchRoomUpdatedAfterCommit($lockedRoom->id);
        });
    }

    public function cancelRoom(GameRoom $room, Authenticatable $user): void
    {
        if ($room->creator_id !== $user->id) {
            throw ValidationException::withMessages([
                'room' => ['Apenas o criador pode cancelar a sala.'],
            ]);
        }

        DB::transaction(function () use ($room, $user) {
            $lockedRoom = GameRoom::query()
                ->lockForUpdate()
                ->findOrFail($room->id);

            if (! $lockedRoom->isWaiting()) {
                throw ValidationException::withMessages([
                    'room' => ['Esta sala não pode mais ser cancelada.'],
                ]);
            }

            if ($lockedRoom->bet_amount > 0) {
                $freshUser = User::query()
                    ->lockForUpdate()
                    ->findOrFail($user->id);

                $freshUser->increment('carteira_game', $lockedRoom->bet_amount);
            }

            $lockedRoom->update([
                'status'      => 'finished',
                'finished_at' => now(),
            ]);

            $this->dispatchRoomUpdatedAfterCommit($lockedRoom->id);
        });
    }
}
