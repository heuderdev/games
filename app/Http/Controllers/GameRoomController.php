<?php

namespace App\Http\Controllers;

use App\Models\GameRoom;
use App\Services\GameRoomService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class GameRoomController extends Controller
{
    public function index()
    {
        return view('games.tic-tac-toe');
    }

    public function cancel(GameRoom $room): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        try {
            app(GameRoomService::class)->cancelRoom($room, $user);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('dashboard')->with('success', 'Sala cancelada com sucesso.');
    }
}
