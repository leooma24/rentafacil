<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    use HasFactory;

    protected $fillable = ['nombre'];

    protected $table = 'estados';

    public function address() {
        return $this->belongsTo(Address::class);
    }
}
