<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
        return $this->belongsToMany(Module::class, 'company_has_modules');
    }

    public function features()
    {
        return $this->belongsToMany(Feature::class, 'module_features');
    }

    protected static function booted()
    {
        static::creating(function ($company) {
            $slug = Str::slug($company->name);
            $searchTheSlug = self::where('slug', $slug)->first();

            if ($searchTheSlug) {
                $company->slug = $slug . '-' . Str::lower(Str::random(5));
            } else {
                $company->slug = $slug;
            }
        });
    }

    public function scopeAssignModule(string $moduleName) 
    {
        $modules = Module::select(['name'])->all();

        if (!in_array($moduleName, $modules)) {
            return false;
        }

        $module = Module::where('name', $moduleName)->first();

        return $this->modules()->attach($module->id);
    }
}
