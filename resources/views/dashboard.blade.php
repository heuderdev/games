<x-app-layout>

    <div class="max-w-5xl mx-auto py-8 space-y-6">
        <h1 class="text-2xl font-bold">Dashboard</h1>
        <a class="mt-4 inline-block px-4 py-2 rounded bg-blue-600 text-white"
            href="{{ route('games.tic-tac-toe') }}">Criar minha sala</a>
        <section class="space-y-3">
            <h2 class="text-lg font-semibold">Salas disponíveis</h2>

            @if ($availableRooms->isEmpty())
            <p class="text-sm text-gray-500">
                Nenhuma sala disponível no momento.
            </p>
            @else
            <div class="space-y-3">
                @foreach ($availableRooms as $room)
                <div class="p-4 border rounded flex items-center justify-between">
                    <div>
                        <div class="font-semibold">
                            Sala #{{ $room->id }}
                        </div>
                        <div class="text-sm text-gray-600">
                            Aposta: R$ {{ number_format($room->bet_amount, 2, ',', '.') }}
                            · Jogadores: {{ $room->players_count }}/2 - Jogador: {{ $room->creator->name ??
                            'Desconhecido' }}
                        </div>
                    </div>

                    <div class="space-x-2">
                        <a href="{{ route('games.tic-tac-toe', ['roomId' => $room->id]) }}"
                            class="px-4 py-2 rounded bg-blue-600 text-white text-sm">
                            Entrar
                        </a>

                        <a href="{{ route('games.tic-tac-toe', ['roomId' => $room->id, 'spectator' => 1]) }}"
                            class="px-4 py-2 rounded bg-gray-600 text-white text-sm">
                            Assistir
                        </a>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $availableRooms->links() }}
            </div>
            @endif
        </section>
    </div>

</x-app-layout>