<?php

namespace App\Livewire\Games;

use App\Models\GameRoom;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class AvailableRoomsList extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    public function getListeners(): array
    {
        return [
            'echo:game-lobby,.room.updated' => 'refreshRooms',
        ];
    }

    public function refreshRooms(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $availableRooms = GameRoom::query()
            ->with(['creator'])
            ->withCount('players')
            ->where('status', 'waiting')
            ->where('mode', 'human_vs_human')
            ->latest('id')
            ->paginate(10);

        return view('livewire.games.available-rooms-list', [
            'availableRooms' => $availableRooms,
        ]);
    }
}
