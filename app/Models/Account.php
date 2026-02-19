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
        'code',
        'group_code',
        'name',
        'description',
        'type',
    ];

    protected static function booted()
    {
        static::creating(function ($account) {
            $account->uuid = (string) Str::uuid();
        });
    }
}
