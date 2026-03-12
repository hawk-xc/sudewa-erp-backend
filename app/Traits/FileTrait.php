<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait FileTrait
{
    public function storeFile(UploadedFile $file, string $folder)
    {
        if (! $file) {
            return null;
        }

        $filename = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();

        $path = $file->storeAs($folder, $filename, 'public');

        return $path;
    }

    public function destroyFile(?string $path)
    {
        if (! $path) {
            return false;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);

            return true;
        }

        return false;
    }
}
