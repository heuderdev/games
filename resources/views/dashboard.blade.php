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

        <a class="mt-4 inline-block px-4 py-2 rounded bg-blue-600 text-white" href="{{ route('games.tic-tac-toe') }}">
            Criar minha sala
        </a>

        <livewire:games.available-rooms-list />
    </div>
</x-app-layout>