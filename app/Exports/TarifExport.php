<?php

namespace App\Exports;

use App\Models\Tarif;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TarifExport implements FromCollection, WithHeadings, WithMapping
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
        $query = Tarif::query();

        if ($this->request->filled('search')) {
            $search = $this->request->search;
            $query->where(function ($q) use ($search) {
                $q->where('loading_in', 'like', "%$search%")
                    ->orWhere('loading_out', 'like', "%$search%");
            });
        }

        foreach ($this->columns as $field) {
            if ($this->request->filled($field)) {
                $query->where($field, $this->request->$field);
            }
        }

        $sortBy = in_array($this->request->sort_by, $this->columns) ? $this->request->sort_by : 'id';
        $sortOrder = $this->request->sort_order === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortOrder)->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'UUID',
            'Muat',
            'Bongkar',
            'Jarak',
            'UJ Towing',
            'UJ CDD',
            'UJ Fuso',
            'INV CDD',
            'INV Fuso',
            'Status',
            'Dibuat Pada'
        ];
    }

    public function map($tarif): array
    {
        return [
            $tarif->id,
            $tarif->uuid,
            $tarif->loading_in,
            $tarif->loading_out,
            $tarif->distance,
            $tarif->uj_towing,
            $tarif->uj_cdd,
            $tarif->uj_fuso,
            $tarif->inv_cdd,
            $tarif->inv_fuso,
            $tarif->is_active ? 'aktif' : 'tidak aktif',
            $tarif->created_at,
        ];
    }
}
