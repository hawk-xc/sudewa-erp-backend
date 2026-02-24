<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cash extends Model
{
    use HasFactory;

    protected $table = 'cashes';

    protected $fillable = [
        'account_id',
        'code',
        'description',
        'amount',
        'type',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
