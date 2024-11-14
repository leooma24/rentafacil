<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProspectiveClient extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'source',
        'notes',
        'last_contacted_at',
        'status',
    ];
}
