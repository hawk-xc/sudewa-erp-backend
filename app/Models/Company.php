<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $table = 'companies';

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    public function modules()
    {
        return $this->belongsToMany(Module::class, 'company_modules');
    }

    public function features()
    {
        return $this->belongsToMany(Feature::class, 'module_features');
    }
}
