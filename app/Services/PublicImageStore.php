<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PublicImageStore
{
    /**
     * Store an uploaded image on the public disk and return its public URL.
     */
    public function store(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, 'public');

        return Storage::disk('public')->url($path);
    }

    /**
     * Delete a stored image by its public URL; URLs that do not belong to the public disk (external links) are left alone.
     */
    public function delete(?string $url): void
    {
        if ($url === null || $url === '') {
            return;
        }

        $prefix = rtrim(Storage::disk('public')->url(''), '/').'/';

        if (! str_starts_with($url, $prefix)) {
            return;
        }

        $path = ltrim(substr($url, strlen($prefix)), '/');

        if ($path !== '') {
            Storage::disk('public')->delete($path);
        }
    }
}
