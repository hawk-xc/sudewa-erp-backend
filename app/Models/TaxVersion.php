<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxVersion extends Model
{
    use HasFactory;

    protected $table = 'tax_versions';

    protected $fillable = [
        'tax_id',
        'name',
        'rate',
        'effective_from',
        'effective_until',
        'is_default',
    ];

    public function tax()
    {
        return $this->belongsTo(Tax::class);
    }
}
