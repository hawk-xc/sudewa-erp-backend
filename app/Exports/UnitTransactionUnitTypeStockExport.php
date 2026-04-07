<?php

namespace App\Exports;

use App\Models\UnitTransaction;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UnitTransactionUnitTypeStockExport implements FromCollection, WithHeadings, WithMapping
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $query = UnitTransaction::query();

        if ($this->request->filled('type') && in_array($this->request->type, ['purchase', 'sales'])) {
            $query->where('type', (string) $this->request->type);
        }

        if ($this->request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $this->request->start_date);
        }

        if ($this->request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $this->request->end_date);
        }

        $query->with([
            'person:id,name',
            'unitTransactionItems.unitType:id,code,name,unit_type',
            'unitTransactionItems.unitTransactionItemDetails:id,unit_transaction_item_id,is_forecast',
        ]);

        if ($this->request->filled('search')) {
            $search = $this->request->search;

            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%$search%");
            });
        }

        $transactions = $query->get();
        $exportData = collect();

        foreach ($transactions as $trx) {
            foreach ($trx->unitTransactionItems as $item) {

                $forecastQty = $item->unitTransactionItemDetails
                    ->where('is_forecast', true)
                    ->count();

                $actualQty = $item->unitTransactionItemDetails->count();

                $exportData->push([
                    'id' => $trx->id,
                    'code' => $trx->code,
                    'date' => preg_replace('/\s.*/', '', (string) $trx->created_at),
                    'person' => $trx->person?->name,
                    'unit_type_code' => $item->unitType?->code,
                    'unit_type_name' => $item->unitType?->name,
                    'unit_type_category' => $item->unitType?->unit_type,
                    'qty_forecast' => $forecastQty,
                    'qty_actual' => $actualQty,
                    'qty_input' => (int) $item->qty_total,
                    'qty_difference' => (int) $item->qty_total - $actualQty,
                ]);
            }
        }

        return $exportData;
    }

    public function headings(): array
    {
        return [
            'ID Transaksi',
            'Kode Transaksi',
            'Tanggal',
            'Nama (Supplier/Customer)',
            'Kode Unit',
            'Nama Unit',
            'Kategori Unit',
            'Qty Forecast',
            'Qty Actual',
            'Qty Input',
            'Qty Selisih',
        ];
    }

    public function map($row): array
    {
        return [
            $row['id'],
            $row['code'],
            $row['date'],
            $row['person'],
            $row['unit_type_code'],
            $row['unit_type_name'],
            $row['unit_type_category'],
            $row['qty_forecast'],
            $row['qty_actual'],
            $row['qty_input'],
            $row['qty_difference'],
        ];
    }
}
