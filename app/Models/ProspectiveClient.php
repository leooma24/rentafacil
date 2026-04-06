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
        'city',
        'business_name',
        'num_washers',
        'source',
        'notes',
        'last_contacted_at',
        'status',
        'converted_user_id',
        'converted_at',
    ];

    protected $casts = [
        'last_contacted_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    public function convertedUser()
    {
        return $this->belongsTo(User::class, 'converted_user_id');
    }

    public function isConverted(): bool
    {
        return $this->status === 'cliente' && $this->converted_user_id !== null;
    }
}
