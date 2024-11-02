<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyPackage extends Model
{
    use HasFactory;

    protected $table = 'company_package';

    protected $fillable = ['company_id', 'package_id', 'start_date', 'end_date'];

    public function company() : BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function package() : BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
