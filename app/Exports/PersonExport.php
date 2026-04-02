<?php

namespace App\Exports;

use App\Models\Person;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PersonExport implements FromCollection, WithHeadings
{
    protected Request $request;
    protected array $columns;

    public function __construct(Request $request, array $columns, $person_type = 'customer')
    {
        $this->request = $request;
        $this->columns = $columns;
        $this->person_type = $person_type;
    }

    public function collection()
    {
        $query = Person::query();

        $query->select($this->columns)->where('type', (string) $this->person_type);

        if ($this->request->filled('search')) {
            $search = $this->request->search;
            $caseSensitive = $this->request->boolean('case_sensitive');

            $query->where(function ($q) use ($search, $caseSensitive) {
                if ($caseSensitive) {
                    $q->where('name', 'LIKE BINARY', "%$search%")
                        ->orWhere('code', 'LIKE BINARY', "%$search%")
                        ->orWhere('phone', 'LIKE BINARY', "%$search%")
                        ->orWhere('npwp', 'LIKE BINARY', "%$search%");
                } else {
                    $q->where('name', 'like', "%$search%")
                        ->orWhere('code', 'like', "%$search%")
                        ->orWhere('phone', 'like', "%$search%")
                        ->orWhere('npwp', 'like', "%$search%");
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
            'Nama PIC',
            'Kode',
            'Tipe',
            'Nama',
            'Alamat',
            'NPWP',
            'No Telp',
            'Tanggal Dibuat',
        ];
    }
}