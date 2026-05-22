<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;


class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $guarded = ['id'];
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'carteira_game'     => 'decimal:2',
        'winnings_balance'  => 'decimal:2',
    ];

    /**
     * Verifica se o usuário tem saldo suficiente para apostar.
     *
     * @param  float|int|string  $amount
     * @return bool
     */
    public function hasGameBalance($amount): bool
    {
        $amount = (float) $amount;

        if ($amount < 0) {
            return false;
        }

        // aqui você define de onde sai o saldo:
        // só créditos de jogo:
        return (float) $this->carteira_game >= $amount;

        // se quiser permitir usar carteira_game + credito_game juntos:
        // return (float) ($this->credito_game + $this->carteira_game) >= $amount;
    }
}
