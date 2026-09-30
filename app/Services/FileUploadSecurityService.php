<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FileUploadSecurityService
{
    public const MAX_SIZE_BYTES = 5242880; // 5 MB

    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];

    public const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /**
     * Inspect file content, enforce strict MIME/magic-bytes verification,
     * and store with a secure UUID filename.
     *
     * @throws ValidationException
     */
    public static function validateAndStore(UploadedFile $file, string $directory = 'permissions/evidence'): string
    {
        // 1. Size Verification
        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw ValidationException::withMessages([
                'evidence' => 'The uploaded file exceeds the 5MB maximum file size limit.',
            ]);
        }

        // 2. Extension Verification
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'evidence' => "File extension '.{$extension}' is not permitted. Allowed: ".implode(', ', self::ALLOWED_EXTENSIONS),
            ]);
        }

        // 3. MIME Type Verification
        $mime = $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            throw ValidationException::withMessages([
                'evidence' => "Detected MIME type '{$mime}' is not permitted for evidence documentation.",
            ]);
        }

        // 4. Content Signature & Malware / Script Inspection
        $filePath = $file->getRealPath();
        $headBytes = file_get_contents($filePath, false, null, 0, 512) ?: '';

        // Check for executable or script headers
        if (str_starts_with($headBytes, 'MZ') || str_starts_with($headBytes, "\x7fELF")) {
            throw ValidationException::withMessages([
                'evidence' => 'Binary executables are strictly prohibited from upload.',
            ]);
        }

        if (stripos($headBytes, '<?php') !== false || stripos($headBytes, '<?=') !== false) {
            throw ValidationException::withMessages([
                'evidence' => 'PHP script files are strictly prohibited from upload.',
            ]);
        }

        if (stripos($headBytes, '<script') !== false || stripos($headBytes, '<html') !== false) {
            throw ValidationException::withMessages([
                'evidence' => 'HTML or JavaScript files are prohibited from upload.',
            ]);
        }

        // 5. Store with UUID Obfuscated Name
        $uuidFileName = (string) Str::uuid().'.'.$extension;
        $storedPath = Storage::disk('public')->putFileAs($directory, $file, $uuidFileName);

        if (! $storedPath) {
            throw new \RuntimeException('Failed to store uploaded evidence file.');
        }

        return $storedPath;
    }
}
