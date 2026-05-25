<?php

namespace App\Events;

use App\Models\GameRoom;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameRoomUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $roomId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("game-room.{$this->roomId}"),
            new Channel('game-lobby'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'room.updated';
    }
}
