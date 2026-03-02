<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Urna extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'descripcion',
        'votos_nulos',
        'votos_blancos',
    ];

    public function resultados()
    {
        return $this->hasMany(ResultadoVoto::class);
    }
}
