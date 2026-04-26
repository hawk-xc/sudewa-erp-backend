<?php

namespace App\Imports;

use App\Models\Person;
use App\Traits\PersonTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PersonImport implements ToCollection, WithHeadingRow
{
    use PersonTrait;

    protected string $type;

    protected int $companyId;

    public function __construct(string $type, int $companyId)
    {
        $this->type = (string) $type;

        $this->companyId = $companyId;
    }

    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            $codesInFile = [];

            foreach ($rows as $index => $row) {
                $rowData = [
                    'nama' => isset($row['nama']) ? trim($row['nama']) : null,
                    'alamat' => $row['alamat'] ?? null,
                    'telp' => $row['telp'] ?? null,
                    'npwp' => $row['npwp'] ?? null,
                    'nama_pic' => $row['nama_pic'] ?? null,
                    'identity_number' => $row['identity_number'] ?? $row['no_identitas'] ?? $row['nik'] ?? null,
                    'drive_license_identity_number' => $row['drive_license_identity_number'] ?? $row['no_sim'] ?? $row['sim'] ?? null,
                    'image' => $row['image'] ?? $row['foto'] ?? null,
                    'map_link' => $row['map_link'] ?? null,
                    'social_media_1_link' => $row['social_media_1_link'] ?? null,
                    'social_media_2_link' => $row['social_media_2_link'] ?? null,
                    'social_media_3_link' => $row['social_media_3_link'] ?? null,
                    'social_media_4_link' => $row['social_media_4_link'] ?? null,
                    'website_link' => $row['website_link'] ?? null,
                    'join_date' => $row['join_date'] ?? $row['tanggal_bergabung'] ?? $row['tgl_gabung'] ?? null,
                ];

                $validator = Validator::make($rowData, [
                    'nama' => 'required|string|max:255',
                    'alamat' => 'nullable|string|max:255',
                    'telp' => 'nullable|string|max:255',
                    'npwp' => 'nullable|string|max:255',
                    'nama_pic' => 'nullable|string|max:255',
                    'identity_number' => 'nullable|string|max:255',
                    'drive_license_identity_number' => 'nullable|string|max:255',
                    'image' => 'nullable|string|max:255',
                    'map_link' => 'nullable|string|max:255',
                    'social_media_1_link' => 'nullable|string|max:255',
                    'social_media_2_link' => 'nullable|string|max:255',
                    'social_media_3_link' => 'nullable|string|max:255',
                    'social_media_4_link' => 'nullable|string|max:255',
                    'website_link' => 'nullable|string|max:255',
                    'join_date' => 'nullable|date',
                ]);

                if ($validator->fails()) {
                    throw new \Exception(
                        'Error on row '.($index + 2).': '.json_encode($validator->errors()->all())
                    );
                }

                Person::create([
                    'company_id' => $this->companyId,
                    'code' => $this->generateCode($this->type),
                    'type' => $this->type,
                    'name' => $rowData['nama'],
                    'address' => $rowData['alamat'],
                    'phone' => $rowData['telp'],
                    'npwp' => $rowData['npwp'],
                    'pic_name' => $rowData['nama_pic'],
                    'identity_number' => $rowData['identity_number'],
                    'drive_license_identity_number' => $rowData['drive_license_identity_number'],
                    'image' => $rowData['image'],
                    'map_link' => $rowData['map_link'],
                    'social_media_1_link' => $rowData['social_media_1_link'],
                    'social_media_2_link' => $rowData['social_media_2_link'],
                    'social_media_3_link' => $rowData['social_media_3_link'],
                    'social_media_4_link' => $rowData['social_media_4_link'],
                    'website_link' => $rowData['website_link'],
                    'join_date' => $rowData['join_date'],
                ]);
            }
        });
    }
}
