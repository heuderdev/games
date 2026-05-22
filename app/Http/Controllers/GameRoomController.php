<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class GameRoomController extends Controller
{
    public function index()
    {
        return view('games.tic-tac-toe');
    }
}
