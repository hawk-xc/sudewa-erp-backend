<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    use HasFactory;

    protected $table = 'features';

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    public function modules()
    {
        return $this->belongsToMany(Module::class, 'module_features');
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_modules');
    }
}
