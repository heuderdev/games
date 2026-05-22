<?php

namespace App\Services;

use App\Models\BotDifficultyRule;
use App\Models\GameRoom;
use App\Models\GameRoomMove;
use App\Models\GameRoomParticipant;
use App\Models\GameRoomTransaction;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class GameRoomService
{
    public function __construct(
        protected DatabaseManager $db,
        protected MinimaxBotService $bot,
        protected BotDecisionService $botDecision, // novo
    ) {}

    protected function botUserId(): int
    {
        return User::query()->where('email', 'bot@game.local')->value('id');
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

            // debita da carteira_game (saldo jogável)
            $creator->decrement('carteira_game', $betAmount);

            GameRoomTransaction::create([
                'game_room_id' => $room->id,
                'user_id'      => $creator->id,
                'type'         => 'entry_bet',
                'amount'       => $betAmount,
                'reference'    => 'room:' . $room->id,
            ]);

            return $room;
        });
    }

    public function createRoomAgainstBot(
        User $creator,
        float $betAmount,
        float $platformFeePercent = null,
        string $botSymbol = 'O'
    ): GameRoom {
        $this->assertUserHasNoOpenRoom($creator);

        $platformFeePercent ??= config('game.platform_fee_percent');

        // verifica saldo do criador
        if (! $creator->hasGameBalance($betAmount)) {
            throw ValidationException::withMessages([
                'credit' => 'Créditos insuficientes para criar a sala.',
            ]);
        }

        // carrega o usuário BOT
        $botUserId = $this->botUserId();
        $bot       = User::findOrFail($botUserId);

        // verifica saldo do BOT
        if (! $bot->hasGameBalance($betAmount)) {
            throw ValidationException::withMessages([
                'bot' => 'Plataforma sem saldo suficiente para jogar. Não se preocupe, o administrador vai ser notificado, para fazer a recarga.',
            ]);
        }

        return $this->db->transaction(function () use ($creator, $bot, $betAmount, $platformFeePercent, $botSymbol) {
            $room = GameRoom::create([
                'creator_id'           => $creator->id,
                'bet_amount'           => $betAmount,
                'platform_fee_percent' => $platformFeePercent,
                'status'               => 'active',
                'mode'                 => 'human_vs_bot',
            ]);

            // humano
            GameRoomParticipant::create([
                'game_room_id' => $room->id,
                'user_id'      => $creator->id,
                'role'         => 'player',
                'symbol'       => $botSymbol === 'X' ? 'O' : 'X',
                'joined_at'    => Carbon::now(),
            ]);

            // bot
            GameRoomParticipant::create([
                'game_room_id' => $room->id,
                'user_id'      => $bot->id,
                'role'         => 'player',
                'symbol'       => strtoupper($botSymbol),
                'joined_at'    => Carbon::now(),
            ]);

            // debita criador
            $creator->decrement('carteira_game', $betAmount);

            GameRoomTransaction::create([
                'game_room_id' => $room->id,
                'user_id'      => $creator->id,
                'type'         => 'entry_bet',
                'amount'       => $betAmount,
                'reference'    => 'room:' . $room->id,
            ]);

            // debita BOT
            $bot->decrement('carteira_game', $betAmount);

            GameRoomTransaction::create([
                'game_room_id' => $room->id,
                'user_id'      => $bot->id,
                'type'         => 'entry_bet',
                'amount'       => $betAmount,
                'reference'    => 'room:' . $room->id,
            ]);

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
            $playersCount = $room->players()->lockForUpdate()->count();

            if ($playersCount >= 2) {
                throw ValidationException::withMessages([
                    'room' => 'Sala já possui dois jogadores.',
                ]);
            }

            $symbol = $playersCount === 0 ? 'X' : 'O';

            $participant = GameRoomParticipant::create([
                'game_room_id' => $room->id,
                'user_id'      => $user->id,
                'role'         => 'player',
                'symbol'       => $symbol,
                'joined_at'    => Carbon::now(),
            ]);

            // debita da carteira_game
            $user->decrement('carteira_game', $room->bet_amount);

            GameRoomTransaction::create([
                'game_room_id' => $room->id,
                'user_id'      => $user->id,
                'type'         => 'entry_bet',
                'amount'       => $room->bet_amount,
                'reference'    => 'room:' . $room->id,
            ]);

            if ($room->players()->count() === 2) {
                $room->update([
                    'status'     => 'active',
                    'started_at' => Carbon::now(),
                ]);
            }

            return $participant;
        });
    }

    public function joinRoomAsSpectator(GameRoom $room, User $user): GameRoomParticipant
    {
        if ($room->participants()->where('user_id', $user->id)->exists()) {
            return $room->participants()->where('user_id', $user->id)->first();
        }

        return GameRoomParticipant::create([
            'game_room_id' => $room->id,
            'user_id'      => $user->id,
            'role'         => 'spectator',
            'symbol'       => null,
            'joined_at'    => Carbon::now(),
        ]);
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
            $lastMove = $room->moves()->orderByDesc('turn_number')->lockForUpdate()->first();
            $nextTurn = $lastMove ? $lastMove->turn_number + 1 : 1;

            $expectedSymbol = $nextTurn % 2 === 1 ? 'X' : 'O';

            if ($participant->symbol !== $expectedSymbol) {
                throw ValidationException::withMessages([
                    'turn' => 'Não é a sua vez.',
                ]);
            }

            if ($room->moves()->where('cell', $cell)->exists()) {
                throw ValidationException::withMessages([
                    'cell' => 'Casa já ocupada.',
                ]);
            }

            $move = GameRoomMove::create([
                'game_room_id' => $room->id,
                'user_id'      => $user->id,
                'cell'         => $cell,
                'turn_number'  => $nextTurn,
            ]);

            $board        = $this->buildBoard($room);
            $winnerSymbol = $this->checkWinner($board);

            if ($winnerSymbol !== null) {
                $winnerParticipant = $room->participants()
                    ->where('role', 'player')
                    ->where('symbol', $winnerSymbol)
                    ->first();

                $this->finishRoom($room, $winnerParticipant?->user);

                return $move;
            }

            if ($this->isBoardFull($board)) {
                $this->finishRoom($room, null);

                return $move;
            }

            if ($room->mode === 'human_vs_bot') {
                $this->playBotMove($room);
            }

            return $move;
        });
    }

    protected function determineBotDifficultyForToday(): string
    {
        $botId = $this->botUserId();

        $todayStart = Carbon::today();
        $todayEnd   = Carbon::today()->endOfDay();

        // soma dos payouts do bot no dia
        $botWinnerPayoutToday = GameRoomTransaction::query()
            ->where('user_id', $botId)
            ->where('type', 'winner_payout')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->sum('amount');

        $target       = (float) config('game.bot_daily_target', 1000);     // meta de caixa diária
        $targetRatio  = (float) config('game.bot_target_ratio', 0.6);      // 60%
        $targetAmount = $target * $targetRatio;

        // evita divisão por zero
        if ($targetAmount <= 0) {
            // fallback simples: usa medium
            return 'medium';
        }

        // porcentagem do target atingida
        $progress = $botWinnerPayoutToday / $targetAmount; // ex.: 0.5 => 50% do target

        /**
         * Estratégia:
         * - progress < 0.3  => bot está "pobre" -> hard/hardcore pra recuperar
         * - 0.3 <= p < 0.7  => medium / hard
         * - 0.7 <= p < 1.0  => easy
         * - p >= 1.0        => idiot (bem burro pra devolver)
         */
        if ($progress < 0.3) {
            return 'hardcore'; // casa muito abaixo da meta, bot joga sério
        }

        if ($progress < 0.7) {
            return 'hard'; // chegando na meta, mas ainda abaixo
        }

        if ($progress < 1.0) {
            return 'medium'; // perto da meta, neutraliza
        }

        // acima da meta (bot já bateu target) -> deixa burro
        return 'idiot';
    }

    protected function resolveBotDifficulty(): string
    {
        $now = Carbon::now();

        $time = $now->format('H:i:s');
        $todayDate = $now->toDateString();
        $weekday   = (int) $now->dayOfWeek;

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

        // 2) se não existir regra manual, usa regra dinâmica baseada no caixa diário
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
        $board     = $this->buildBoard($room);

        $winnerSymbol = $this->checkWinner($board);

        if ($winnerSymbol !== null || $this->isBoardFull($board)) {
            return;
        }

        // movimento perfeito via Minimax
        $perfectMove = $this->bot->bestMove($board, $botSymbol);

        if ($perfectMove === null) {
            return;
        }

        $difficulty = $this->resolveBotDifficulty();

        $allowedDifficulties = ['idiot', 'easy', 'medium', 'hard', 'hardcore'];
        if (! in_array($difficulty, $allowedDifficulties, true)) {
            $difficulty = 'easy';
        }

        // delega para o BotDecisionService escolher o movimento final
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
            if ($room->isFinished()) {
                return;
            }

            // pega jogadores envolvidos
            $players = $room->players()->with('user')->lockForUpdate()->get();

            if ($players->count() !== 2) {
                // segurança: não faz nada se não tiver 2 players
                $room->update([
                    'status'      => 'finished',
                    'winner_id'   => null,
                    'finished_at' => Carbon::now(),
                ]);

                return;
            }

            $betAmount = $room->bet_amount;

            if ($winner) {
                // -------------------------------
                // CENÁRIO: TEM VENCEDOR
                // -------------------------------
                $totalPot     = $betAmount * 2;
                $platformFee  = round($totalPot * ($room->platform_fee_percent / 100), 2);
                $winnerPayout = $totalPot - $platformFee;

                // credita saldo na carteira_game e acumula ganhos
                // $winner->increment('carteira_game', $winnerPayout);
                $winner->increment('winnings_balance', $winnerPayout);

                // registra pagamento do vencedor
                GameRoomTransaction::create([
                    'game_room_id' => $room->id,
                    'user_id'      => $winner->id,
                    'type'         => 'winner_payout',
                    'amount'       => $winnerPayout,
                    'reference'    => 'room:' . $room->id,
                ]);

                // registra taxa da plataforma
                GameRoomTransaction::create([
                    'game_room_id' => $room->id,
                    'user_id'      => null, // ou id da conta da plataforma
                    'type'         => 'platform_fee',
                    'amount'       => $platformFee,
                    'reference'    => 'room:' . $room->id,
                ]);
            } else {
                // -------------------------------
                // CENÁRIO: EMPATE
                // -------------------------------
                foreach ($players as $participant) {
                    $user = $participant->user;

                    if (! $user) {
                        continue;
                    }

                    // devolve exatamente o bet_amount na carteira_game
                    $user->increment('carteira_game', $betAmount);

                    GameRoomTransaction::create([
                        'game_room_id' => $room->id,
                        'user_id'      => $user->id,
                        'type'         => 'draw_refund',
                        'amount'       => $betAmount,
                        'reference'    => 'room:' . $room->id,
                    ]);
                }

                // sem platform_fee em empate
            }

            $room->update([
                'status'      => 'finished',
                'winner_id'   => $winner?->id,
                'finished_at' => Carbon::now(),
            ]);
        });
    }
}
