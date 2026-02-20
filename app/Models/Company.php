<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasFactory;

    protected $table = 'companies';

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'type',
        'created_at',
        'updated_at',
    ];

    public function modules()
    {
        return $this->belongsToMany(Module::class, 'company_has_modules');
    }

    public function features()
    {
        return Feature::query()
            ->join('module_has_features', 'features.id', '=', 'module_has_features.feature_id')
            ->join('modules', 'modules.id', '=', 'module_has_features.module_id')
            ->join('company_has_modules', 'company_has_modules.module_id', '=', 'modules.id')
            ->where('company_has_modules.company_id', $this->id)
            ->select('features.*')
            ->distinct();
    }

    protected static function booted()
    {
        static::creating(function ($company) {
            $slug = Str::slug($company->name);
            $uuid = Str::uuid();

            $searchTheSlug = self::where('slug', $slug)->first();
            $searchTheUuid = self::where('uuid', $uuid)->first();

            if ($searchTheSlug) {
                $company->slug = $slug.'-'.Str::lower(Str::random(5));
            } else {
                $company->slug = $slug;
            }

            if ($searchTheUuid) {
                $company->uuid = Str::uuid();
            } else {
                $company->uuid = $uuid;
            }
        });
    }

    public function scopeAssignModule(string $moduleName)
    {
        $modules = Module::select(['name'])->all();

        if (! in_array($moduleName, $modules)) {
            return false;
        }

        $module = Module::where('name', $moduleName)->first();

        return $this->modules()->attach($module->id);
    }
}
