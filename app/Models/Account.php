<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Account extends Model
{
    use HasFactory;

    protected $table = 'accounts';

    protected $fillable = [
        'account_group_id',
        'uuid',
        'code',
        'name',
        'description',
        'type',
        'category'
    ];

    protected $casts = [
        'account_group_id' => 'integer'
    ];

    public function accountGroup()
    {
        return $this->belongsTo(AccountGroup::class);
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
