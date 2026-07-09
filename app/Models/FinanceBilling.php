<?php
 
namespace App\Models;
 
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
 
class FinanceBilling extends Model
{
    use HasFactory;
    
    protected $table = 'finance_billings';
 
    protected $fillable = [
        'uuid',
        'unit_transaction_billing_id',
        'goods_transaction_billing_id',
        'cash_flow_id',
        'cash_id',
        'account_id',
        'amount',
        'amount_original',
        'payment_proof',
        'payment_at',
        'note',
    ];
 
    protected $casts = [
        'unit_transaction_billing_id' => 'integer',
        'goods_transaction_billing_id' => 'integer',
        'cash_flow_id' => 'integer',
        'cash_id' => 'integer',
        'account_id' => 'integer',
        'amount' => 'decimal:2',
        'amount_original' => 'decimal:2',
        'payment_at' => 'date',
    ];
 
    public function unitTransactionBilling()
    {
        return $this->belongsTo(UnitTransactionBilling::class, 'unit_transaction_billing_id', 'id');
    }
 
    public function goodsTransactionBilling()
    {
        return $this->belongsTo(GoodsTransactionBilling::class, 'goods_transaction_billing_id', 'id');
    }
 
    public function cashFlow()
    {
        return $this->belongsTo(CashFlow::class);
    }
 
    public function cash()
    {
        return $this->belongsTo(Cash::class);
    }
 
    public function account()
    {
        return $this->belongsTo(Account::class);
    }
 
    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
