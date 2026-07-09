<?php
 
namespace App\Models;
 
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
 
class GoodsTransactionBilling extends Model
{
    use HasFactory;
 
    protected $table = 'goods_transaction_billings';
 
    protected $fillable = [
        'uuid',
        'goods_transaction_id',
        'is_paid',
        'grand_total',
    ];
 
    protected $casts = [
        'goods_transaction_id' => 'integer',
        'is_paid' => 'boolean',
        'grand_total' => 'integer',
    ];
 
    public function goodsTransaction()
    {
        return $this->belongsTo(GoodsTransaction::class);
    }
 
    public function payments()
    {
        return $this->hasMany(GoodsTransactionBillingPayment::class, 'goods_transaction_billing_id');
    }
 
    public function cashFlow()
    {
        return $this->hasOne(CashFlow::class, 'goods_transaction_billing_id', 'id');
    }

    public function financeBillings()
    {
        return $this->hasManyThrough(
            FinanceBilling::class,
            CashFlow::class,
            'goods_transaction_billing_id',
            'cash_flow_id',
            'id',
            'id'
        );
    }
 
    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
