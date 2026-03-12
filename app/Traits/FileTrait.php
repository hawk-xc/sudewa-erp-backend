<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;

trait FileTrait
{
    public function storeFile(UploadedFile $file, string $storage)
    {
        if (! $file) {
            return null;
        }

        $path = public_path($storage);

        if (! file_exists($path)) {
            mkdir($path, 0755, true);
        }

        $filename = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();

        $file->move($path, $filename);

        return $filename;
    }

    public function destroyFile(string $path)
    {
        $fullPath = public_path($path);

        if (file_exists($fullPath)) {
            unlink($fullPath);

            return true;
        }

        return false;
    }
}
