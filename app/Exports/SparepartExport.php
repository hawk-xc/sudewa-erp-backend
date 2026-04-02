<?php

namespace App\Exports;

use App\Models\Sparepart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SparepartExport implements FromCollection, WithHeadings
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
        $query = Sparepart::query()
            ->leftJoin('sparepart_categories', 'spareparts.sparepart_category_id', '=', 'sparepart_categories.id');

        if ($this->request->filled('search')) {
            $search = $this->request->search;

            $query->where(function ($q) use ($search) {
                $q->where('spareparts.name', 'like', "%{$search}%")
                  ->orWhere('spareparts.code', 'like', "%{$search}%");
            });
        }

        foreach ($this->columns as $field) {
            if ($this->request->filled($field)) {
                $query->where("spareparts.$field", $this->request->$field);
            }
        }

        $allowedSort = $this->columns;

        $sortBy = in_array($this->request->sort_by, $allowedSort)
            ? $this->request->sort_by
            : 'spareparts.id';

        $sortOrder = $this->request->sort_order === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortBy, $sortOrder);

        $query->select([
            'spareparts.id',
            'spareparts.code',
            'spareparts.name',
            'sparepart_categories.name as category_name',
            'spareparts.buy_price',
            'spareparts.sell_price',
            'spareparts.capacity',
            'spareparts.unit_type',
            'spareparts.created_at',
        ]);

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Kode',
            'Nama',
            'Kategori Sparepart',
            'Harga Beli',
            'Harga Jual',
            'Kapasitas',
            'Tipe Unit',
            'Tanggal Dibuat',
        ];
    }
}