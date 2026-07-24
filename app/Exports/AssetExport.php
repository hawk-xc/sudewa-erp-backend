<?php

namespace App\Exports;

use App\Models\Asset;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AssetExport implements FromCollection, WithHeadings
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
        $query = Asset::query()
            ->leftJoin('finance_assets', 'assets.id', '=', 'finance_assets.asset_id');

        $selectColumns = [];
        foreach ($this->columns as $col) {
            if (in_array($col, ['serial_number', 'purchase_date', 'price'])) {
                $selectColumns[] = 'finance_assets.' . $col . ' as ' . $col;
            } else {
                $selectColumns[] = 'assets.' . $col . ' as ' . $col;
            }
        }
        $query->select($selectColumns);

        if ($this->request->filled('search')) {
            $search = $this->request->search;
            $caseSensitive = $this->request->boolean('case_sensitive');

            $query->where(function ($q) use ($search, $caseSensitive) {
                if ($caseSensitive) {
                    $q->where('assets.name', 'LIKE BINARY', "%$search%")
                        ->orWhere('assets.code', 'LIKE BINARY', "%$search%")
                        ->orWhere('finance_assets.serial_number', 'LIKE BINARY', "%$search%")
                        ->orWhere('assets.type', 'LIKE BINARY', "%$search%");
                } else {
                    $q->where('assets.name', 'like', "%$search%")
                        ->orWhere('assets.code', 'like', "%$search%")
                        ->orWhere('finance_assets.serial_number', 'like', "%$search%")
                        ->orWhere('assets.type', 'like', "%$search%");
                }
            });
        }

        foreach ($this->columns as $field) {
            if ($this->request->filled($field)) {
                if (in_array($field, ['serial_number', 'purchase_date', 'price'])) {
                    $query->where('finance_assets.' . $field, $this->request->$field);
                } else {
                    $query->where('assets.' . $field, $this->request->$field);
                }
            }
        }

        $allowedSort = $this->columns;

        $sortBy = in_array($this->request->sort_by, $allowedSort)
            ? $this->request->sort_by
            : 'id';

        $sortOrder = $this->request->sort_order === 'asc' ? 'asc' : 'desc';

        if (in_array($sortBy, ['serial_number', 'purchase_date', 'price'])) {
            $query->orderBy('finance_assets.' . $sortBy, $sortOrder);
        } else {
            $query->orderBy('assets.' . $sortBy, $sortOrder);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'UUID',
            'Company ID',
            'Kode',
            'Nomor Seri',
            'Tanggal Pembelian',
            'Nama Asset',
            'Tipe',
            'Harga',
            'Tanggal Dibuat',
            'Tanggal Diupdate',
        ];
    }
}
