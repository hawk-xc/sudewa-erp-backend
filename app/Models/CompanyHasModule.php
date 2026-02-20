<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyHasModule extends Model
{
    use HasFactory;

    protected $table = 'company_has_modules';

    protected $fillable = [
        'company_id',
        'module_id',
        'created_at',
        'updated_at',
    ];

    // relation mapping
    public function companies()
    {
        return $this->hasMany(Company::class);
    }

    public function modules()
    {
        return $this->hasMany(Module::class);
    }
}
