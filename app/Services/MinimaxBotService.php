<?php

namespace App\Services;

class MinimaxBotService
{
    /**
     * Calcula o melhor movimento para o bot usando Minimax.
     *
     * @param array<int, null|string> $board  Board com 9 posições (0..8), valores: null, 'X', 'O'
     * @param string                  $bot     Símbolo do bot, 'X' ou 'O'
     *
     * @return int|null Índice da casa que o bot deve jogar (0..8) ou null se não houver jogadas
     */
    public function bestMove(array $board, string $bot): ?int
    {
        $bot = strtoupper($bot);
        $human = $bot === 'X' ? 'O' : 'X';

        $bestScore = PHP_INT_MIN;
        $bestMove = null;

        foreach ($this->availableMoves($board) as $index) {
            $board[$index] = $bot;
            $score = $this->minimax($board, false, $bot, $human);
            $board[$index] = null;

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMove = $index;
            }
        }

        return $bestMove;
    }

    /**
     * Implementação do algoritmo Minimax.
     *
     * @param array<int, null|string> $board
     * @param bool                    $isMaximizing
     * @param string                  $bot
     * @param string                  $human
     *
     * @return int
     */
    protected function minimax(array $board, bool $isMaximizing, string $bot, string $human): int
    {
        $winner = $this->checkWinner($board);

        if ($winner === $bot) {
            return 10;
        }

        if ($winner === $human) {
            return -10;
        }

        if ($this->isBoardFull($board)) {
            return 0;
        }

        if ($isMaximizing) {
            $bestScore = PHP_INT_MIN;

            foreach ($this->availableMoves($board) as $index) {
                $board[$index] = $bot;
                $score = $this->minimax($board, false, $bot, $human);
                $board[$index] = null;

                $bestScore = max($bestScore, $score);
            }

            return $bestScore;
        }

        // Minimizing (jogador humano)
        $bestScore = PHP_INT_MAX;

        foreach ($this->availableMoves($board) as $index) {
            $board[$index] = $human;
            $score = $this->minimax($board, true, $bot, $human);
            $board[$index] = null;

            $bestScore = min($bestScore, $score);
        }

        return $bestScore;
    }

    /**
     * Retorna o vencedor atual do board.
     *
     * @param array<int, null|string> $board
     *
     * @return string|null 'X', 'O' ou null
     */
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
                isset($board[$a], $board[$b], $board[$c]) &&
                $board[$a] !== null &&
                $board[$a] === $board[$b] &&
                $board[$b] === $board[$c]
            ) {
                return $board[$a];
            }
        }

        return null;
    }

    /**
     * @param array<int, null|string> $board
     *
     * @return bool
     */
    protected function isBoardFull(array $board): bool
    {
        foreach ($board as $cell) {
            if ($cell === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, null|string> $board
     *
     * @return int[] índices das casas vazias
     */
    protected function availableMoves(array $board): array
    {
        $moves = [];

        foreach ($board as $index => $cell) {
            if ($cell === null) {
                $moves[] = $index;
            }
        }

        return $moves;
    }
}
