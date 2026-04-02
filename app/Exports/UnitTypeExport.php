<?php

namespace App\Exports;

use App\Models\Company;
use App\Models\UnitType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class UnitTypeExport implements FromCollection, WithHeadings
{
    protected Request $request;
    protected array $columns;

    public function __construct(Request $request, array $columns)
    {
        $this->request = $request;
        $this->columns = $columns;
    }

    public function collection()
    {
        $query = UnitType::query()
            ->leftJoin('brands', 'unit_types.brand_id', '=', 'brands.id');

        if ($this->request->filled('search')) {
            $search = $this->request->search;

            $query->where(function ($q) use ($search) {
                $q->where('unit_types.name', 'like', "%{$search}%")
                  ->orWhere('unit_types.code', 'like', "%{$search}%");
            });
        }

        if ($this->request->filled('brand_id')) {
            $query->where('unit_types.brand_id', $this->request->brand_id);
        }

        if ($this->request->filled('unit_type')) {
            $query->where('unit_types.unit_type', $this->request->unit_type);
        }

        $warehouseId = null;

        if ($this->request->filled('company_id')) {
            $company = Company::find($this->request->company_id);
            $warehouseId = $company?->warehouse?->id;
        }

        $realStockQuery = DB::table('unit_transaction_item_details')
            ->join('unit_transaction_items', 'unit_transaction_item_details.unit_transaction_item_id', '=', 'unit_transaction_items.id')
            ->join('unit_transactions', 'unit_transaction_items.unit_transaction_id', '=', 'unit_transactions.id')
            ->where('unit_transaction_item_details.in_stock', true)
            ->when($warehouseId, function ($q) use ($warehouseId) {
                $q->where('unit_transactions.warehouse_id', $warehouseId);
            })
            ->selectRaw('unit_transaction_items.unit_type_id, COUNT(*) as stock')
            ->groupBy('unit_transaction_items.unit_type_id');

        $forecastStockQuery = DB::table('unit_transaction_item_details')
            ->join('unit_transaction_items', 'unit_transaction_item_details.unit_transaction_item_id', '=', 'unit_transaction_items.id')
            ->join('unit_transactions', 'unit_transaction_items.unit_transaction_id', '=', 'unit_transactions.id')
            ->when($warehouseId, function ($q) use ($warehouseId) {
                $q->where('unit_transactions.warehouse_id', $warehouseId);
            })
            ->selectRaw('unit_transaction_items.unit_type_id, COUNT(*) as stock')
            ->groupBy('unit_transaction_items.unit_type_id');

        $query->leftJoinSub($realStockQuery, 'real_stock', function ($join) {
            $join->on('unit_types.id', '=', 'real_stock.unit_type_id');
        });

        $query->leftJoinSub($forecastStockQuery, 'forecast_stock', function ($join) {
            $join->on('unit_types.id', '=', 'forecast_stock.unit_type_id');
        });

        $query->select([
            'unit_types.id',
            'unit_types.code',
            'unit_types.name',
            'brands.name as brand_name',
            'unit_types.unit_type',
            'unit_types.unit_model',
            'unit_types.netto_weight',
            'unit_types.bruto_weight',
            'unit_types.buy_price',
            'unit_types.sell_price',
            DB::raw('COALESCE(real_stock.stock, 0) as available_stock'),
            DB::raw('COALESCE(forecast_stock.stock, 0) as forecast_stock'),
            'unit_types.created_at',
        ]);

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Kode',
            'Nama',
            'Brand',
            'Tipe Unit',
            'Model Unit',
            'Berat Netto',
            'Berat Brutto',
            'Harga Beli',
            'Harga Jual',
            'Stok Real',
            'Stok Perkiraan',
            'Tanggal Dibuat',
        ];
    }
}