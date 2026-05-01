<?php

namespace App\Exports;

use App\Models\DOExpedition;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DOExpeditionExport implements FromQuery, WithHeadings, WithMapping
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function query()
    {
        $query = DOExpedition::with(['vehicle', 'driver', 'items']);

        if ($this->request->filled('search')) {
            $search = $this->request->search;
            $query->where('do_code', 'like', "%$search%");
        }

        if ($this->request->filled('start_date') && $this->request->filled('end_date')) {
            $query->whereBetween('date', [$this->request->start_date, $this->request->end_date]);
        }

        return $query->orderBy('id', 'desc');
    }

    public function headings(): array
    {
        return [
            'ID',
            'UUID',
            'DO Code',
            'Date',
            'Vehicle',
            'Driver',
            'Total Items',
            'Total Invoice Fee',
            'Created At',
        ];
    }

    public function map($doExpedition): array
    {
        return [
            $doExpedition->id,
            $doExpedition->uuid,
            $doExpedition->do_code,
            $doExpedition->date->format('Y-m-d'),
            $doExpedition->vehicle ? $doExpedition->vehicle->registration_number : '-',
            $doExpedition->driver ? $doExpedition->driver->name : '-',
            $doExpedition->items->count(),
            $doExpedition->items->sum('invoice_fee'),
            $doExpedition->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
