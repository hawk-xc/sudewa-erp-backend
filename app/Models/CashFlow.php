<?php

namespace App\Models;

use App\Models\Cash;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CashFlow extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'code',
        'company_id',
        'cash_id',
        'date',
        'note',
        'debet',
        'credit',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'cash_id' => 'integer',
        'date' => 'date',
        'debet' => 'integer',
        'credit' => 'integer',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function cash()
    {
        return $this->belongsTo(Cash::class);
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }

            if (empty($model->code)) {
                DB::transaction(function () use ($model) {

                    $today = Carbon::now()->format('Ymd');
                    $prefix = 'TRX'.$today;

                    $last = self::where('code', 'like', $prefix.'%')
                        ->lockForUpdate()
                        ->orderBy('code', 'desc')
                        ->first();

                    if ($last) {
                        $lastNumber = (int) substr($last->code, -5);
                        $nextNumber = $lastNumber + 1;
                    } else {
                        $nextNumber = 1;
                    }

                    $model->code = $prefix.str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
                });
            }
        });
    }
}

