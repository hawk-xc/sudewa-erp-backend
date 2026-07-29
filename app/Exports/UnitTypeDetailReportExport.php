<?php

namespace App\Exports;

use App\Models\UnitTransactionItemDetail;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class UnitTypeDetailReportExport implements FromCollection, WithHeadings
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $query = UnitTransactionItemDetail::query()->with(['unitTransactionItem.unitType', 'unitTransactionItem.unitTransaction.person']);

        if ($this->request->filled('type')) {
            $query->whereHas('unitTransactionItem.unitTransaction', function ($q) {
                $q->where('type', $this->request->type);
            });
        }

        if ($this->request->filled('code')) {
            $query->whereHas('unitTransactionItem.unitTransaction', function ($q) {
                $q->where('code', 'like', "%{$this->request->code}%");
            });
        }

        if ($this->request->filled('person')) {
            $query->whereHas('unitTransactionItem.unitTransaction.person', function ($q) {
                $q->where('name', 'like', "%{$this->request->person}%");
            });
        }

        if ($this->request->filled('unit_type_id')) {
            $query->whereHas('unitTransactionItem', function ($q) {
                $q->where('unit_type_id', $this->request->unit_type_id);
            });
        }

        if ($this->request->filled('machine_number')) {
            $query->where('machine_number', 'like', "%{$this->request->machine_number}%");
        }

        if ($this->request->filled('chassis_number')) {
            $query->where('chassis_number', 'like', "%{$this->request->chassis_number}%");
        }

        if ($this->request->filled('color')) {
            $query->where('color', 'like', "%{$this->request->color}%");
        }

        if ($this->request->filled('status')) {
            $query->where('status', $this->request->status);
        }

        if ($this->request->filled('in_stock')) {
            $query->where('in_stock', filter_var($this->request->in_stock, FILTER_VALIDATE_BOOLEAN));
        }

        if ($this->request->filled('is_forecast')) {
            $query->where('is_forecast', filter_var($this->request->is_forecast, FILTER_VALIDATE_BOOLEAN));
        }

        return $query->get()->map(function ($item) {
            return [
                'ID' => $item->id,
                'Date' => $item->created_at,
                'Transaction Code' => $item->unitTransactionItem->unitTransaction->code ?? null,
                'Type' => $item->unitTransactionItem->unitTransaction->type ?? null,
                'Person' => $item->unitTransactionItem->unitTransaction->person->name ?? null,
                'Unit Type Code' => $item->unitTransactionItem->unitType->code ?? null,
                'Unit Type Name' => $item->unitTransactionItem->unitType->name ?? null,
                'Machine Number' => $item->machine_number,
                'Chassis Number' => $item->chassis_number,
                'Color' => $item->color,
                'In Stock' => $item->in_stock ? 'Yes' : 'No',
                'Forecast' => $item->is_forecast ? 'Yes' : 'No',
                'Status' => $item->status,
            ];
        });
    }

    public function headings(): array
    {
        return ['ID', 'Tanggal', 'Kode Transaksi', 'Tipe', 'Nama', 'Kode Tipe Unit', 'Nama Tipe Unit', 'Nomor Mesin', 'Nomor Rangka', 'Warna', 'Tersedia', 'Forecast', 'Status'];
    }
}
