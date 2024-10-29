<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Neighborhood extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'ciudad', 'municipio_id', 'asentamiento', 'codigo_postal'];

    protected $table = 'colonias';

    public function township()
    {
        return $this->belongsTo(Township::class, 'municipio_id');
    }
}
