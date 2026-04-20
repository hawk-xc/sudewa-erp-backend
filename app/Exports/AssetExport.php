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
        $query = Asset::query();
        $query->select($this->columns);

        if ($this->request->filled('search')) {
            $search = $this->request->search;
            $caseSensitive = $this->request->boolean('case_sensitive');

            $query->where(function ($q) use ($search, $caseSensitive) {
                if ($caseSensitive) {
                    $q->where('name', 'LIKE BINARY', "%$search%")
                        ->orWhere('code', 'LIKE BINARY', "%$search%")
                        ->orWhere('type', 'LIKE BINARY', "%$search%");
                } else {
                    $q->where('name', 'like', "%$search%")
                        ->orWhere('code', 'like', "%$search%")
                        ->orWhere('type', 'like', "%$search%");
                }
            });
        }

        foreach ($this->columns as $field) {
            if ($this->request->filled($field)) {
                $query->where($field, $this->request->$field);
            }
        }

        $allowedSort = $this->columns;

        $sortBy = in_array($this->request->sort_by, $allowedSort)
            ? $this->request->sort_by
            : 'id';

        $sortOrder = $this->request->sort_order === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortBy, $sortOrder);

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
