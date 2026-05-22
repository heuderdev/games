<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class BotDecisionService
{
    /**
     * Decide o movimento final do bot, aplicando dificuldade (chance/tipo de erro).
     *
     * @param array<int, null|string> $board      Board atual (0..8)
     * @param string                  $botSymbol  'X' ou 'O'
     * @param int|null                $perfectMove Índice sugerido pelo Minimax
     * @param string                  $difficulty 'idiot', 'easy', 'medium', 'hard', 'hardcore'
     *
     * @return int|null Movimento escolhido (0..8) ou null se não houver jogadas
     */
    public function pickMoveWithDifficulty(
        array $board,
        string $botSymbol,
        ?int $perfectMove,
        string $difficulty = 'medium'
    ): ?int {
        if ($perfectMove === null) {
            Log::info('BotDecision: no perfect move available', [
                'difficulty' => $difficulty,
                'bot'        => $botSymbol,
            ]);

            return null;
        }

        // hardcore: sempre joga perfeito, sem nenhum erro
        if ($difficulty === 'hardcore') {
            Log::info('BotDecision: hardcore mode, using perfect move', [
                'difficulty'  => $difficulty,
                'bot'         => $botSymbol,
                'perfectMove' => $perfectMove,
            ]);

            return $perfectMove;
        }

        $mistakeChance = $this->mistakeChanceFor($difficulty);

        // sem erro configurado -> sempre perfeito
        if ($mistakeChance <= 0) {
            Log::info('BotDecision: no mistake chance, using perfect move', [
                'difficulty'    => $difficulty,
                'bot'           => $botSymbol,
                'perfectMove'   => $perfectMove,
                'mistakeChance' => $mistakeChance,
            ]);

            return $perfectMove;
        }

        $random = mt_rand(1, 100);

        Log::info('BotDecision: decision roll', [
            'difficulty'    => $difficulty,
            'bot'           => $botSymbol,
            'perfectMove'   => $perfectMove,
            'random'        => $random,
            'mistakeChance' => $mistakeChance,
        ]);

        // decide se vai errar de propósito
        if ($random <= $mistakeChance) {
            $chosen = $this->pickMistakeMove($board, $botSymbol, $perfectMove, $difficulty);

            Log::info('BotDecision: mistake move chosen', [
                'difficulty'    => $difficulty,
                'bot'           => $botSymbol,
                'perfectMove'   => $perfectMove,
                'chosenMove'    => $chosen,
                'random'        => $random,
                'mistakeChance' => $mistakeChance,
            ]);

            return $chosen;
        }

        Log::info('BotDecision: using perfect move (no mistake this time)', [
            'difficulty'    => $difficulty,
            'bot'           => $botSymbol,
            'perfectMove'   => $perfectMove,
            'random'        => $random,
            'mistakeChance' => $mistakeChance,
        ]);

        // padrão: joga a jogada perfeita
        return $perfectMove;
    }

    /**
     * Define a chance de erro por dificuldade (em %).
     */
    protected function mistakeChanceFor(string $difficulty): int
    {
        return match ($difficulty) {
            // extremamente burro: quase nunca usa o movimento perfeito
            'idiot'   => 95,
            // burro: erra muito
            'easy'    => 75,
            // intermediário: erra às vezes
            'medium'  => 40,
            // difícil: erra raramente
            'hard'    => 10,
            default   => 0, // incl. hardcore
        };
    }

    /**
     * Escolhe um movimento "errado" de acordo com a dificuldade.
     *
     * @param array<int, null|string> $board
     */
    protected function pickMistakeMove(
        array $board,
        string $botSymbol,
        int $perfectMove,
        string $difficulty
    ): int {
        $availableCells = array_keys(array_filter($board, fn($v) => $v === null));

        if (empty($availableCells)) {
            return $perfectMove;
        }

        return match ($difficulty) {
            'idiot'   => $this->pickWorstPossibleMove($board, $botSymbol, $availableCells),
            'easy'    => $this->pickBadMovePreferably($board, $botSymbol, $availableCells),
            'medium'  => $this->pickRandomMove($availableCells),
            'hard'    => $this->pickSlightlySuboptimalMove($board, $botSymbol, $availableCells, $perfectMove),
            default   => $this->pickRandomMove($availableCells),
        };
    }

    /**
     * Movimento totalmente aleatório (base para medium).
     *
     * @param int[] $availableCells
     */
    protected function pickRandomMove(array $availableCells): int
    {
        return $availableCells[array_rand($availableCells)];
    }

    /**
     * Para "easy": tenta escolher um movimento ruim (não bloqueia, não ganha) se possível.
     *
     * @param array<int, null|string> $board
     * @param int[]                   $availableCells
     */
    protected function pickBadMovePreferably(
        array $board,
        string $botSymbol,
        array $availableCells
    ): int {
        $humanSymbol = $botSymbol === 'X' ? 'O' : 'X';

        $badMoves = [];

        foreach ($availableCells as $cell) {
            $boardCopy = $board;
            $boardCopy[$cell] = $botSymbol;

            if (
                ! $this->wouldBotWin($boardCopy, $botSymbol)
                && ! $this->wouldBlockImmediateHumanWin($board, $cell, $humanSymbol)
            ) {
                $badMoves[] = $cell;
            }
        }

        if (! empty($badMoves)) {
            return $badMoves[array_rand($badMoves)];
        }

        return $this->pickRandomMove($availableCells);
    }

    /**
     * Para "idiot": usa a mesma lógica do easy por enquanto.
     *
     * @param array<int, null|string> $board
     * @param int[]                   $availableCells
     */
    protected function pickWorstPossibleMove(
        array $board,
        string $botSymbol,
        array $availableCells
    ): int {
        return $this->pickBadMovePreferably($board, $botSymbol, $availableCells);
    }

    /**
     * Para "hard": escolhe algo levemente pior que o perfeito, mas ainda razoável.
     *
     * @param array<int, null|string> $board
     * @param int[]                   $availableCells
     */
    protected function pickSlightlySuboptimalMove(
        array $board,
        string $botSymbol,
        array $availableCells,
        int $perfectMove
    ): int {
        $options = array_values(array_diff($availableCells, [$perfectMove]));

        if (empty($options)) {
            return $perfectMove;
        }

        return $options[array_rand($options)];
    }

    /**
     * Checa se o bot ganharia com o board atual.
     *
     * @param array<int, null|string> $board
     */
    protected function wouldBotWin(array $board, string $botSymbol): bool
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

        foreach ($wins as [$a, $b, $c]) {
            if (
                isset($board[$a], $board[$b], $board[$c]) &&
                $board[$a] === $botSymbol &&
                $board[$b] === $botSymbol &&
                $board[$c] === $botSymbol
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica se jogar nessa célula bloquearia uma vitória imediata do humano.
     *
     * @param array<int, null|string> $board
     */
    protected function wouldBlockImmediateHumanWin(
        array $board,
        int $cell,
        string $humanSymbol
    ): bool {
        $boardCopy = $board;
        $boardCopy[$cell] = $humanSymbol;

        return $this->wouldHumanWin($boardCopy, $humanSymbol);
    }

    /**
     * Checa se o humano venceria com o board atual.
     *
     * @param array<int, null|string> $board
     */
    protected function wouldHumanWin(array $board, string $humanSymbol): bool
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

        foreach ($wins as [$a, $b, $c]) {
            if (
                isset($board[$a], $board[$b], $board[$c]) &&
                $board[$a] === $humanSymbol &&
                $board[$b] === $humanSymbol &&
                $board[$c] === $humanSymbol
            ) {
                return true;
            }
        }

        return false;
    }
}
