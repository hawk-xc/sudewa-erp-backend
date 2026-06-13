<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AccountGroup extends Model
{
    use HasFactory;

    protected $table = 'account_groups';

    protected $fillable = [
        'uuid',
        'company_id',
        'group_code',
        'is_lock',
        'description',
    ];

    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
