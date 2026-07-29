<?php

namespace App\Exports;

use App\Models\WarehouseSubBlock;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class WarehouseSubBlockExport implements FromCollection, WithHeadings
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
        $query = WarehouseSubBlock::query();

        $query->select($this->columns);

        if ($this->request->filled('search')) {
            $search = $this->request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('description', 'like', "%$search%");
            });
        }

        foreach ($this->columns as $field) {
            if ($this->request->filled($field)) {
                $query->where($field, $this->request->$field);
            }
        }

        $sortBy = in_array($this->request->sort_by, $this->columns) ? $this->request->sort_by : 'id';
        $sortOrder = $this->request->sort_order === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortBy, $sortOrder);

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'UUID',
            'Warehouse Block ID',
            'Name',
            'Description',
            'Is Active',
            'Is Default',
            'Created At',
            'Updated At',
        ];
    }
}
