<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionFlow extends Model
{
    use HasFactory;

    protected $table = 'transaction_flows';

    protected $fillable = [
        'uuid',
        'code',
        'company_id',
        'unit_transaction_id',
        'transaction_date',
        'name',
        'description',
        'bank_usd_debit',
        'bank_usd_credit',
        'bank_idr_debit',
        'bank_idr_credit',
        'cash_idr_debit',
        'cash_idr_credit',
        'transaction_proof',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'unit_transaction_id' => 'integer',
        'transaction_date' => 'datetime',
        'bank_usd_debit' => 'integer',
        'bank_usd_credit' => 'integer',
        'bank_idr_debit' => 'integer',
        'bank_idr_credit' => 'integer',
        'cash_idr_debit' => 'integer',
        'cash_idr_credit' => 'integer',
    ];

    public function unitTransaction()
    {
        return $this->belongsTo(UnitTransaction::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
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
