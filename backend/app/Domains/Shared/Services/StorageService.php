<?php

namespace App\Domains\Shared\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class StorageService
{
    /**
     * Store a sensitive document in private object storage (MinIO / S3 Private).
     */
    public function storePrivateDocument(UploadedFile $file, string $directory = 'provider_documents'): array
    {
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $extension;
        $path = $directory . '/' . date('Y/m') . '/' . $filename;

        // Store with private visibility
        Storage::disk('s3_private')->put($path, file_get_contents($file->getRealPath()), 'private');

        return [
            'file_path_s3' => $path,
            'file_mime_type' => $file->getClientMimeType(),
            'file_size_bytes' => $file->getSize(),
        ];
    }

    /**
     * Generate a time-limited signed URL for a private document (Default 15 minutes).
     */
    public function getPrivateDocumentUrl(string $path, int $expirationMinutes = 15): string
    {
        if (app()->environment('local', 'testing') && !env('AWS_ENDPOINT')) {
            return "/api/v1/storage/private-mock/" . urlencode($path);
        }

        return Storage::disk('s3_private')->temporaryUrl(
            $path,
            now()->addMinutes($expirationMinutes)
        );
    }

    /**
     * Store a public asset (e.g. profile photo, category icon).
     */
    public function storePublicAsset(UploadedFile $file, string $directory = 'public_assets'): string
    {
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $extension;
        $path = $directory . '/' . $filename;

        Storage::disk('s3_public')->put($path, file_get_contents($file->getRealPath()), 'public');

        return Storage::disk('s3_public')->url($path);
    }

    /**
     * Delete a file from private storage.
     */
    public function deletePrivateDocument(string $path): bool
    {
        return Storage::disk('s3_private')->delete($path);
    }
}
