<?php

namespace App\Traits;

use App\Models\Asset;
use App\Models\BBNBill;
use App\Models\DOExpedition;
use App\Models\DOInvoice;
use App\Models\DOOrderList;
use App\Models\GoodsTransaction;
use App\Models\Material;
use App\Models\Person;
use App\Models\Tarif;
use App\Models\UnitTransaction;
use App\Models\UnitTransactionRefund;
use App\Models\VehicleDocument;
use App\Models\VehicleEquipment;
use App\Models\VehicleEquipmentTransaction;
use App\Models\VehicleFleet;
use App\Models\WarehouseActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait GlobalCodeNumberTrait
{
    /**
     * Generate unique code following format code({nama pt}, {fitur})
     * e.g., code('transindo', 'dealer')
     */
    public function code(string $company, string $feature): ?string
    {
        $company = strtolower(trim($company));
        $feature = strtolower(trim($feature));

        // Map company slug/alias to short code suffix / date prefix
        $compKey = '';
        if (str_contains($company, 'morindo') || $company === 'wjm') {
            $compKey = 'wjm';
        } elseif (str_contains($company, 'internasional') || str_contains($company, 'international') || $company === 'win') {
            $compKey = 'win';
        } elseif (str_contains($company, 'adhiyas') || $company === 'aad') {
            $compKey = 'aad';
        } elseif (str_contains($company, 'transindo') || $company === 'wjt') {
            $compKey = 'wjt';
        } elseif (str_contains($company, 'yanotama') || $company === 'wjy') {
            $compKey = 'wjy';
        } else {
            $compKey = $company;
        }

        // Determine prefix, model, column, length, and constraints
        $modelClass = null;
        $column = 'code';
        $prefix = '';
        $length = 4;
        $additionalConstraints = [];

        // Normalize feature mapping
        switch ($feature) {
            // Master Data
            case 'supplier':
                $modelClass = Person::class;
                $additionalConstraints = [['type', '=', 'supplier']];
                if ($compKey === 'wjm') $prefix = 'SP-M/';
                elseif ($compKey === 'win') $prefix = 'SP-I/';
                elseif ($compKey === 'aad') $prefix = 'SP-A/';
                else $prefix = 'SP-' . strtoupper(substr($compKey, 0, 1)) . '/';
                break;
            case 'customer':
                $modelClass = Person::class;
                $additionalConstraints = [['type', '=', 'customer']];
                if ($compKey === 'wjm') $prefix = 'CS-M/';
                elseif ($compKey === 'win') $prefix = 'CS-I/';
                elseif ($compKey === 'aad') $prefix = 'CS-A/';
                elseif ($compKey === 'wjt') $prefix = 'CS-T/';
                else $prefix = 'CS-' . strtoupper(substr($compKey, 0, 1)) . '/';
                break;
            case 'aset':
            case 'asset':
                $modelClass = Asset::class;
                if ($compKey === 'wjm') $prefix = 'AS-M/';
                elseif ($compKey === 'win') $prefix = 'AS-I/';
                elseif ($compKey === 'aad') $prefix = 'AS-A/';
                elseif ($compKey === 'wjt') $prefix = 'AS-T/';
                elseif ($compKey === 'wjy') $prefix = 'AS-Y/';
                else $prefix = 'AS-' . strtoupper(substr($compKey, 0, 1)) . '/';
                break;
            case 'dealer':
                $modelClass = Person::class;
                $additionalConstraints = [['type', '=', 'dealer']];
                if ($compKey === 'wjt') $prefix = 'DL-T/';
                elseif ($compKey === 'wjy') $prefix = 'DL-Y/';
                else $prefix = 'DL-' . strtoupper(substr($compKey, 0, 1)) . '/';
                break;
            case 'vendor':
                $modelClass = Person::class;
                $additionalConstraints = [['type', '=', 'vendor']];
                if ($compKey === 'wjy') $prefix = 'VN-Y/';
                else $prefix = 'VN-' . strtoupper(substr($compKey, 0, 1)) . '/';
                break;
            case 'driver':
                $modelClass = Person::class;
                $additionalConstraints = [['type', '=', 'driver']];
                if ($compKey === 'wjt') $prefix = 'DR-T/';
                else $prefix = 'DR-' . strtoupper(substr($compKey, 0, 1)) . '/';
                break;
            case 'tarif':
                $modelClass = Tarif::class;
                $prefix = 'TF-T/';
                break;
            case 'armada':
                $modelClass = VehicleFleet::class;
                $column = 'registration_number';
                $prefix = 'AR-T/';
                break;
            case 'perlengkapan':
                $modelClass = VehicleEquipment::class;
                $prefix = 'PK-T/';
                break;
            case 'material':
                $modelClass = Material::class;
                $prefix = 'MT-Y/';
                break;

            // Administrasi / Transaksi
            case 'pembelian_unit':
            case 'pembelian':
                $modelClass = UnitTransaction::class;
                $additionalConstraints = [['type', '=', 'purchase']];
                $prefix = 'PBL-' . strtoupper($compKey) . '/' . date('Ymd') . '-';
                break;
            case 'refund_beli':
            case 'refund_purchase':
                $modelClass = UnitTransactionRefund::class;
                $prefix = 'RFB-' . strtoupper($compKey) . '/' . date('Ymd') . '-';
                break;
            case 'penjualan':
            case 'sales':
                $modelClass = UnitTransaction::class;
                $additionalConstraints = [['type', '=', 'sales']];
                $prefix = 'INV-' . strtoupper($compKey) . '/' . date('Ymd') . '-';
                break;
            case 'refund_jual':
            case 'refund_sales':
                $modelClass = UnitTransactionRefund::class;
                $prefix = 'RFJ-' . strtoupper($compKey) . '/' . date('Ymd') . '-';
                break;
            case 'order_list':
            case 'order':
                $modelClass = DOOrderList::class;
                $prefix = 'ORD-' . strtoupper($compKey) . '/' . date('Ymd') . '-';
                break;
            case 'do_ekspedisi':
            case 'ekspedisi':
                $modelClass = DOExpedition::class;
                $prefix = 'DOE-' . strtoupper($compKey) . '/' . date('Ymd') . '-';
                break;
            case 'create_invoice':
            case 'invoice':
                $modelClass = DOInvoice::class;
                $prefix = 'INV-' . strtoupper($compKey) . '/' . date('Ymd') . '-';
                break;
            case 'penerimaan_unit':
                $modelClass = WarehouseActivity::class;
                $column = 'activity_number';
                $additionalConstraints = [['activity_type', '=', 'receipt']];
                $prefix = 'TRM-' . strtoupper($compKey) . '/' . date('Ymd') . '-';
                break;
            case 'pengeluaran_unit':
                $modelClass = WarehouseActivity::class;
                $column = 'activity_number';
                $additionalConstraints = [['activity_type', '=', 'issue']];
                $prefix = 'SIJ-' . strtoupper($compKey) . '/' . date('Ymd') . '-';
                break;

            // Warehouse TR
            case 'beli_penerimaan_perlengkapan':
                $modelClass = GoodsTransaction::class;
                $additionalConstraints = [['type', '=', 'receipt']];
                $prefix = 'TRM-PK/' . date('Ymd') . '-';
                break;
            case 'pengeluaran_perlengkapan':
                $modelClass = GoodsTransaction::class;
                $additionalConstraints = [['type', '=', 'issue']];
                $prefix = 'KLR-PK/' . date('Ymd') . '-';
                break;

            // Warehouse YN
            case 'beli_penerimaan_material':
                $modelClass = GoodsTransaction::class;
                $additionalConstraints = [['type', '=', 'receipt']];
                $prefix = 'TRM-MT/' . date('Ymd') . '-';
                break;
            case 'pengeluaran_material':
                $modelClass = GoodsTransaction::class;
                $additionalConstraints = [['type', '=', 'issue']];
                $prefix = 'KLR-MT/' . date('Ymd') . '-';
                break;

            // Proses Ditlantas
            case 'input_data_kendaraan':
                $modelClass = DOOrderList::class;
                $prefix = 'ORD-' . strtoupper($compKey) . '/' . date('Ymd') . '-';
                break;
            case 'ditlantas_input_stnk_bpkb':
                $modelClass = VehicleDocument::class;
                $prefix = 'DTL-' . strtoupper($compKey) . '/' . date('Ymd') . '-';
                break;
            case 'penerimaan_input_stnk_bpkb':
                $modelClass = VehicleDocument::class;
                $prefix = 'TRP-' . strtoupper($compKey) . '/' . date('Ymd') . '-';
                break;
            case 'tagihan_bbn':
                $modelClass = BBNBill::class;
                $prefix = 'INV-' . strtoupper($compKey) . '/' . date('Ymd') . '-';
                break;

            default:
                $prefix = strtoupper(substr($feature, 0, 3)) . '-' . strtoupper($compKey) . '/';
                break;
        }

        // Fetch last number and generate new sequence
        return DB::transaction(function () use ($modelClass, $column, $prefix, $length, $additionalConstraints) {
            $lastNumber = 0;

            if ($modelClass && class_exists($modelClass)) {
                $tableName = (new $modelClass)->getTable();
                if (Schema::hasColumn($tableName, $column)) {
                    $query = $modelClass::where($column, 'like', "$prefix%");

                    foreach ($additionalConstraints as $constraint) {
                        $query->where($constraint[0], $constraint[1], $constraint[2]);
                    }

                    $lastRecord = $query->lockForUpdate()
                        ->orderBy($column, 'desc')
                        ->first();

                    if ($lastRecord && !empty($lastRecord->$column)) {
                        $lastCode = $lastRecord->$column;
                        $suffix = substr($lastCode, -$length);
                        if (is_numeric($suffix)) {
                            $lastNumber = (int) $suffix;
                        }
                    }
                } else {
                    $lastNumber = $modelClass::count();
                }
            }

            $newNumber = $lastNumber + 1;
            $formattedNumber = str_pad($newNumber, $length, '0', STR_PAD_LEFT);

            return "$prefix$formattedNumber";
        });
    }
}
