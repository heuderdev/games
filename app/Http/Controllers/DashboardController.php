<?php

namespace App\Http\Controllers;

use App\Models\GameRoom;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $availableRooms = GameRoom::query()
            ->availableForJoin()
            ->with([
                'creator:id,name',
            ])
            ->withCount('players')
            ->latest()
            ->paginate(10);

        return view('dashboard', [
            'availableRooms' => $availableRooms,
        ]);
    }
}
