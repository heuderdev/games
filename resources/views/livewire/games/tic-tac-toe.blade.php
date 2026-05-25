<div class="max-w-3xl mx-auto p-6 space-y-6" wire:poll.3s="refreshRoom">
    <h1 class="text-2xl font-bold">Sala - Jogo da Velha</h1>

    @if ($errorMessage)
    <div class="p-3 rounded bg-red-100 text-red-800">
        {{ $errorMessage }}
    </div>
    @endif

    @error('betAmount')
    <div class="p-3 rounded bg-red-100 text-red-800">
        {{ $message }}
    </div>
    @enderror

    {{-- Criar sala --}}
    @if (!$room)
    @if ($this->existingOpenRoom)
    <div class="p-4 border border-yellow-300 rounded bg-yellow-50 space-y-3">
        <div class="font-semibold text-yellow-800">
            ⚠️ Você já possui uma sala aberta (Sala #{{ $this->existingOpenRoom->id }})
        </div>

        <div class="text-sm text-yellow-700">
            Status: <strong>{{ $this->existingOpenRoom->status }}</strong>
            —
            Aposta: <strong>R$ {{ number_format($this->existingOpenRoom->bet_amount, 2, ',', '.') }}</strong>
        </div>

        <div class="flex gap-3">
            <button type="button" wire:click="enterExistingRoom"
                class="px-4 py-2 rounded bg-blue-600 text-white text-sm">
                Entrar na minha sala
            </button>

            @if ($this->existingOpenRoom->isWaiting())
            <button type="button" wire:click="cancelRoom" class="px-4 py-2 rounded bg-red-600 text-white text-sm">
                Cancelar sala
            </button>
            @endif
        </div>
    </div>
    @else
    <div class="p-4 border rounded space-y-3">
        <h2 class="font-semibold">Criar nova sala</h2>

        <div class="flex items-center gap-4">
            <input type="number" step="0.01" min="1" wire:model.live="betAmount" class="border rounded px-3 py-2 w-40"
                placeholder="Aposta R$">

            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model.live="vsBot" class="rounded border-gray-300">
                Jogar contra bot
            </label>

            <button type="button" wire:click="createRoom" class="px-4 py-2 rounded bg-emerald-600 text-white">
                Criar sala
            </button>
        </div>
    </div>
    @endif
    @endif

    {{-- Info da sala --}}
    @if ($room)
    <div class="p-4 border rounded space-y-2">
        <div class="flex justify-between items-center">
            <div>
                <div class="font-semibold">
                    Sala #{{ $room->id }}
                    <span class="text-sm text-gray-500">
                        (status: {{ $room->status }})
                    </span>
                </div>
                <div class="text-sm text-gray-600">
                    Aposta: R$ {{ number_format($room->bet_amount, 2, ',', '.') }}
                    · Modo:
                    @if ($room->mode === 'human_vs_bot')
                    Bot
                    @else
                    Humano vs Humano
                    @endif
                </div>
            </div>


            <div class="space-x-2">
                @if (! $this->userIsOwner && ! $this->userIsPlayer)
                @if ($room->isWaiting() && $room->mode === 'human_vs_human')
                <button type="button" wire:click="joinRoom" class="px-4 py-2 rounded bg-blue-600 text-white">
                    Entrar como jogador
                </button>

                <button type="button" wire:click="joinAsSpectator" class="px-4 py-2 rounded bg-gray-600 text-white">
                    Assistir
                </button>
                @else
                <button type="button" wire:click="joinAsSpectator" class="px-4 py-2 rounded bg-gray-600 text-white">
                    Assistir
                </button>
                @endif
                @endif
            </div>

        </div>

        <div class="text-sm text-gray-600">
            Jogadores:
            @php
            $players = $room->players()->with('user')->get();
            @endphp
            @forelse ($players as $player)
            <span class="inline-flex items-center px-2 py-1 rounded bg-gray-100 mr-2">
                {{ $player->user->name ?? 'Jogador '.$player->id }}
                ({{ $player->symbol }})
            </span>
            @empty
            <span class="text-gray-500">Nenhum jogador ainda.</span>
            @endforelse
        </div>
    </div>

    {{-- Status do jogo --}}
    <div class="p-3 rounded bg-gray-50">
        @if ($room->isFinished())
        @if ($room->winner_id)
        <span class="font-semibold text-emerald-700">
            Partida encerrada. Vencedor: {{ optional($room->winner)->name ?? 'Jogador ' . ($winnerSymbol ?? '?') }}
        </span>
        @else
        <span class="font-semibold text-gray-700">
            Partida encerrada em empate.
        </span>
        @endif
        @else
        @if ($winnerSymbol)
        <span class="font-semibold text-emerald-700">
            {{ $winnerSymbol }} venceu! Aguardando fechamento da sala...
        </span>
        @elseif ($isDraw)
        <span class="font-semibold text-gray-700">
            Empate! Aguardando fechamento da sala...
        </span>
        @elseif ($this->turnMessage)
        <span class="font-semibold {{ $this->canPlay ? 'text-blue-700' : 'text-amber-700' }}">
            {{ $this->turnMessage }}
        </span>
        @else
        @if ($room->mode === 'human_vs_bot')
        <span>
            Sua vez contra o bot.
        </span>
        @else
        <span>
            Aguardando jogadas em tempo real...
        </span>
        @endif
        @endif
        @endif
    </div>

    {{-- Tabuleiro --}}
    <div class="grid grid-cols-3 gap-3 max-w-sm">
        @foreach ($board as $index => $cell)
        <button type="button" wire:click="play({{ $index }})" class="h-24 text-4xl font-bold rounded border
                        @if($cell === 'X') text-blue-700 @elseif($cell === 'O') text-rose-700 @endif
                        @if($room->isFinished()) bg-gray-100 cursor-not-allowed @else bg-white @endif"
            @disabled($room->isFinished() || $cell !== null)
            >
            {{ $cell }}
        </button>
        @endforeach
    </div>
    @endif
</div>