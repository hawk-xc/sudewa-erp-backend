<?php

namespace App\Exports;

use App\Models\FinanceAsset;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class FinanceAssetExport implements FromCollection, WithHeadings
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
        $query = FinanceAsset::query()
            ->join('assets', 'finance_assets.asset_id', '=', 'assets.id')
            ->select([
                'assets.code as asset_code',
                'assets.serial_number as serial_number',
                'finance_assets.economic_age',
                'finance_assets.depreciation',
                'finance_assets.residual_value',
                'finance_assets.final_value',
                'finance_assets.description',
            ]);

        if ($this->request->filled('search')) {
            $search = $this->request->search;
            $caseSensitive = $this->request->boolean('case_sensitive');

            $query->where(function ($q) use ($search, $caseSensitive) {
                if ($caseSensitive) {
                    $q->where('assets.code', 'LIKE BINARY', "%$search%")
                        ->orWhere('finance_assets.description', 'LIKE BINARY', "%$search%");
                } else {
                    $q->where('assets.code', 'like', "%$search%")
                        ->orWhere('finance_assets.description', 'like', "%$search%");
                }
            });
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Kode Asset',
            'Nomor Seri',
            'Umur Ekonomis',
            'Depresiasi',
            'Nilai Residu',
            'Nilai Akhir',
            'Deskripsi',
        ];
    }
}
