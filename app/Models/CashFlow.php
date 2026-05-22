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

    protected $table = 'cash_flows';

    protected $fillable = [
        'uuid',
        'code',
        'company_id',
        'cash_id',
        'account_id',
        'unit_transaction_billing_history_id',
        'transaction_category',
        'date',
        'note',
        'debet',
        'credit',
        'payment_proof'
    ];

    protected $casts = [
        'company_id' => 'integer',
        'account_id' => 'integer',
        'cash_id' => 'integer',
        'unit_transaction_billing_history_id' => 'integer',
        'date' => 'date',
        'debet' => 'integer',
        'credit' => 'integer',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function cash()
    {
        return $this->belongsTo(Cash::class);
    }

    public function unitTransactionBillingHistory()
    {
        return $this->belongsTo(UnitTransactionBillingHistory::class);
    }

    public function financeBilling()
    {
        return $this->hasOne(FinanceBilling::class);
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

