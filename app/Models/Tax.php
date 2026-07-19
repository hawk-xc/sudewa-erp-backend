<?php

namespace App\Models;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tax extends Model
{
    use HasFactory;

    protected $table = 'taxes';

    protected $fillable = [
        'code',
        'name',
        'is_lock',
    ];

    public function TaxVersions()
    {
        return $this->hasMany(TaxVersion::class, 'tax_id', 'id');
    }

    public function getLatest(string $taxCode)
    {
        try {
            $tax = $this->where('code', $taxCode)->first();
            if (!$tax) {
                throw new Exception('Tax not found');
            }

            $taxVersion = TaxVersion::where('tax_id', $tax->id)
                ->first();

            if (!$taxVersion) {
                throw new Exception('Tax version not found');
            }

            return $taxVersion;
        } catch (Exception $err) {
            Log::error('Error getting latest tax: ' . $err->getMessage());
            return null;
        }
    }

    public function getDefault(string $taxCode)
    {
        try {
            $tax = $this->where('code', $taxCode)->first();
            if (!$tax) {
                throw new Exception('Tax not found');
            }

            $taxVersion = TaxVersion::where('tax_id', $tax->id)
                ->where('is_default', true)
                ->first();

            if (!$taxVersion) {
                throw new Exception('Tax version not found');
            }

            return $taxVersion;
        } catch (Exception $err) {
            Log::error('Error getting default tax: ' . $err->getMessage());
            return null;
        }
    }
}
