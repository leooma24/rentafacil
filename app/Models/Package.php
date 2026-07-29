<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'max_clients', 'max_washers', 'price'];

    /**
     * El plan gratuito NO vence.
     *
     * Existe para que el rentador pruebe la app con unos cuantos equipos, y para
     * que del otro lado se vea si de verdad la usa antes de ofrecerle un plan
     * mayor. Lo que lo limita es el cupo, no la fecha.
     *
     * Esto importa: 13 de las 17 cuentas reales están en gratuito con fecha de
     * fin ya pasada, y la app les enseñaba una barra roja diciendo "Tu plan ha
     * expirado, contratar plan para continuar" cada vez que entraban.
     */
    public function isFree(): bool
    {
        return (float) $this->price <= 0;
    }
}
