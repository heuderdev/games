<?php

namespace App\Livewire\Games;

use App\Models\GameRoom;
use App\Services\GameRoomService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class TicTacToe extends Component
{
    #[Url]
    public ?int $roomId = null;

    public bool $vsBot = false;
    public ?GameRoom $room = null;

    public array $board = [];
    public ?string $winnerSymbol = null;
    public bool $isDraw = false;

    public ?string $errorMessage = null;
    public ?float $betAmount = null;

    public function mount(): void
    {
        if ($this->roomId) {
            $this->loadRoom();
        }
    }

    public function render()
    {
        return view('livewire.games.tic-tac-toe', [])->title('Sala - Jogo da Velha');
    }

    public function getListeners(): array
    {
        if (! $this->roomId) {
            return [];
        }

        return [
            "echo:game-room.{$this->roomId},.room.updated" => 'refreshRoom',
        ];
    }

    protected function service(): GameRoomService
    {
        return App::make(GameRoomService::class);
    }

    public function createRoom(): void
    {
        $this->resetErrorBag();
        $this->errorMessage = null;

        $this->validate([
            'betAmount' => ['required', 'numeric', 'min:0'],
        ]);

        /** @var Authenticatable|null $user */
        $user = Auth::user();

        if (! $user) {
            $this->errorMessage = 'Você precisa estar logado.';
            return;
        }

        try {
            $room = $this->vsBot
                ? $this->service()->createRoomAgainstBot($user, (float) $this->betAmount)
                : $this->service()->createRoom($user, (float) $this->betAmount);
        } catch (ValidationException $e) {
            $this->setErrorFromException($e);
            return;
        }

        $this->roomId = $room->id;
        $this->loadRoom();
        // preciso redirecionar para a URL da sala para que os outros jogadores possam entrar usando o link
        $this->redirectRoute('games.tic-tac-toe', ['roomId' => $room->id]);
    }

    public function joinRoom(): void
    {
        $this->resetErrorBag();
        $this->errorMessage = null;

        if (! $this->room) {
            $this->errorMessage = 'Sala não encontrada.';
            return;
        }

        if ($this->room->mode === 'human_vs_bot') {
            $this->errorMessage = 'Esta sala é contra o bot e não aceita outro jogador humano.';
            return;
        }

        /** @var Authenticatable|null $user */
        $user = Auth::user();

        if (! $user) {
            $this->errorMessage = 'Você precisa estar logado.';
            return;
        }

        try {
            $this->service()->joinRoomAsPlayer($this->room, $user);
        } catch (ValidationException $e) {
            $this->setErrorFromException($e);
            return;
        }

        $this->loadRoom();
    }

    public function joinAsSpectator(): void
    {
        $this->resetErrorBag();
        $this->errorMessage = null;

        if (! $this->room) {
            $this->errorMessage = 'Sala não encontrada.';
            return;
        }

        /** @var Authenticatable|null $user */
        $user = Auth::user();

        if (! $user) {
            $this->errorMessage = 'Você precisa estar logado.';
            return;
        }

        try {
            $this->service()->joinRoomAsSpectator($this->room, $user);
        } catch (ValidationException $e) {
            $this->setErrorFromException($e);
            return;
        }

        $this->loadRoom();
    }

    public function play(int $cell): void
    {
        $this->resetErrorBag();
        $this->errorMessage = null;

        if (! $this->room) {
            $this->errorMessage = 'Sala não encontrada.';
            return;
        }

        if ($this->room->isFinished()) {
            return;
        }

        /** @var Authenticatable|null $user */
        $user = Auth::user();

        if (! $user) {
            $this->errorMessage = 'Você precisa estar logado.';
            return;
        }

        try {
            $this->service()->playMove($this->room, $user, $cell);
        } catch (ValidationException $e) {
            $this->setErrorFromException($e);
            return;
        }

        $this->loadRoom();
    }

    #[Computed]
    public function existingOpenRoom(): ?GameRoom
    {
        $userId = Auth::id();

        if (! $userId) {
            return null;
        }

        return GameRoom::query()
            ->where('creator_id', $userId)
            ->whereIn('status', ['waiting', 'active'])
            ->latest()
            ->first();
    }

    public function enterExistingRoom(): void
    {
        $room = $this->existingOpenRoom;

        if (! $room) {
            $this->errorMessage = 'Sala não encontrada.';
            return;
        }

        $this->roomId = $room->id;
        $this->loadRoom();
    }

    public function cancelRoom(): void
    {
        $this->resetErrorBag();
        $this->errorMessage = null;

        /** @var Authenticatable|null $user */
        $user = Auth::user();

        if (! $user) {
            $this->errorMessage = 'Você precisa estar logado.';
            return;
        }

        try {
            $this->service()->cancelRoom($this->existingOpenRoom, $user);
        } catch (ValidationException $e) {
            $this->setErrorFromException($e);
            return;
        }

        unset($this->existingOpenRoom);

        $this->roomId = null;
        $this->room = null;
        $this->board = array_fill(0, 9, null);
        $this->winnerSymbol = null;
        $this->isDraw = false;

        session()->flash('success', 'Sala cancelada com sucesso.');
    }

    public function refreshRoom(): void
    {
        if (! $this->roomId) {
            return;
        }

        $this->loadRoom();
    }

    public function getPlayersProperty()
    {
        if (! $this->room) {
            return collect();
        }

        return $this->room->participants
            ->where('role', 'player')
            ->values();
    }

    public function getSpectatorsProperty()
    {
        if (! $this->room) {
            return collect();
        }

        return $this->room->participants
            ->where('role', 'spectator')
            ->values();
    }

    public function getCurrentTurnSymbolProperty(): ?string
    {
        if (! $this->room || ! $this->room->isActive()) {
            return null;
        }

        $turnNumber = $this->room->moves->count() + 1;

        return $turnNumber % 2 === 1 ? 'X' : 'O';
    }

    public function getAuthenticatedPlayerProperty()
    {
        if (! $this->room) {
            return null;
        }

        $userId = Auth::id();

        if (! $userId) {
            return null;
        }

        return $this->room->participants
            ->where('role', 'player')
            ->firstWhere('user_id', $userId);
    }

    public function getCanPlayProperty(): bool
    {
        if (! $this->room || ! $this->room->isActive() || $this->room->isFinished()) {
            return false;
        }

        $player = $this->authenticatedPlayer;

        if (! $player) {
            return false;
        }

        return $player->symbol === $this->currentTurnSymbol;
    }

    public function getUserIsOwnerProperty(): bool
    {
        return $this->room && $this->room->creator_id === Auth::id();
    }

    public function getUserIsInRoomProperty(): bool
    {
        if (! $this->room) {
            return false;
        }

        $userId = Auth::id();

        if (! $userId) {
            return false;
        }

        return $this->room->participants->contains('user_id', $userId);
    }

    public function getUserIsPlayerProperty(): bool
    {
        if (! $this->room) {
            return false;
        }

        $userId = Auth::id();

        if (! $userId) {
            return false;
        }

        return $this->room->participants
            ->where('role', 'player')
            ->contains('user_id', $userId);
    }

    public function getUserIsSpectatorProperty(): bool
    {
        if (! $this->room) {
            return false;
        }

        $userId = Auth::id();

        if (! $userId) {
            return false;
        }

        return $this->room->participants
            ->where('role', 'spectator')
            ->contains('user_id', $userId);
    }

    protected function loadRoom(): void
    {
        $this->room = GameRoom::query()
            ->with([
                'winner',
                'participants.user',
                'moves.user',
            ])
            ->find($this->roomId);

        if (! $this->room) {
            $this->errorMessage = 'Sala não encontrada.';
            $this->board = array_fill(0, 9, null);
            $this->winnerSymbol = null;
            $this->isDraw = false;
            return;
        }

        $this->rebuildBoard();
    }

    protected function rebuildBoard(): void
    {
        $this->board = array_fill(0, 9, null);
        $this->winnerSymbol = null;
        $this->isDraw = false;

        if (! $this->room) {
            return;
        }

        $participantsByUser = $this->room->participants
            ->where('role', 'player')
            ->keyBy('user_id');

        foreach ($this->room->moves->sortBy('turn_number') as $move) {
            $participant = $participantsByUser->get($move->user_id);

            if (! $participant || ! $participant->symbol) {
                continue;
            }

            $this->board[$move->cell] = $participant->symbol;
        }

        $this->winnerSymbol = $this->checkWinner($this->board);

        if (! $this->winnerSymbol && ! in_array(null, $this->board, true)) {
            $this->isDraw = true;
        }
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

    protected function setErrorFromException(ValidationException $e): void
    {
        $errors = $e->errors();
        $firstMessage = collect($errors)->flatten()->first();

        if ($firstMessage) {
            $this->errorMessage = $firstMessage;
        } else {
            $this->errorMessage = 'Ocorreu um erro ao processar a solicitação.';
        }
    }

    public function getTurnMessageProperty(): ?string
    {
        if (! $this->room || ! $this->room->isActive() || $this->room->isFinished()) {
            return null;
        }

        $currentSymbol = $this->currentTurnSymbol;

        if (! $currentSymbol) {
            return null;
        }

        $currentPlayer = $this->players->firstWhere('symbol', $currentSymbol);
        $currentPlayerName = $currentPlayer?->user?->name ?? "Jogador {$currentSymbol}";

        if ($this->canPlay) {
            return "É a sua vez de jogar ({$currentSymbol}).";
        }

        return "É a vez de {$currentPlayerName} jogar ({$currentSymbol}).";
    }
}
