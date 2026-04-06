<?php

namespace App\Exports;

use App\Models\Cash;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CashExport implements FromCollection, WithHeadings
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
        $query = Cash::query();

        $query->select($this->columns);

        if ($this->request->filled('search')) {
            $search = $this->request->search;

            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%$search%")
                    ->orWhere('description', 'like', "%$search%")
                    ->orWhere('type', 'like', "%$search%");
            });
        }

        foreach (['company_id', 'type', 'code'] as $field) {
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
            'Code',
            'Description',
            'Type',
            'Created At',
        ];
    }
}