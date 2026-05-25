<x-app-layout>

    <div class="max-w-5xl mx-auto py-8 space-y-6 px-2">
        <h1 class="text-2xl font-bold">Dashboard</h1>

        @if (session('success'))
        <div class="p-3 rounded bg-green-100 text-green-800">
            ✅ {{ session('success') }}
        </div>
        @endif

        @if ($errors->any())
        <div class="p-3 rounded bg-red-100 text-red-800">
            {{ $errors->first() }}
        </div>
        @endif

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
                <div
                    class="p-4 border rounded flex items-start justify-between flex-col md:flex-row md:items-center gap-2">
                    <div>
                        <div class="font-semibold">Sala #{{ $room->id }}</div>
                        <div class="text-sm text-gray-600">
                            Aposta: R$ {{ number_format($room->bet_amount, 2, ',', '.') }}
                            · Jogadores: {{ $room->players_count }}/2
                            · Criador: {{ $room->creator->name ?? 'Desconhecido' }}
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <a href="{{ route('games.tic-tac-toe', ['roomId' => $room->id]) }}"
                            class="px-4 py-2 rounded bg-blue-600 text-white text-sm">
                            Entrar
                        </a>

                        @if (Auth::id() === $room->creator_id)
                        <form action="{{ route('games.room.cancel', $room->id) }}" method="POST"
                            onsubmit="return confirm('Cancelar a Sala #{{ $room->id }}? O valor apostado será estornado.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-4 py-2 rounded bg-red-600 text-white text-sm">
                                Cancelar
                            </button>
                        </form>
                        @endif
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