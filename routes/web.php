<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GameRoomController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';

Route::middleware('auth')->get('/jogos/jogo-da-velha', [GameRoomController::class, 'index'])->name('games.tic-tac-toe');

Route::delete('/jogos/sala/{room}/cancelar', [GameRoomController::class, 'cancel'])
    ->middleware('auth')
    ->name('games.room.cancel');
