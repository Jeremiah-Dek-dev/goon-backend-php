<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploadService
{
    private const AVATAR_DIRECTORY = 'uploads/avatars';

    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    /**
     * Upload an administrator/user avatar.
     *
     * Returns the public URL/path that should be stored
     * in the User entity.
     */
    public function uploadAvatar(
        UploadedFile $file
    ): string {

        $this->validateAvatar($file);

        $uploadDirectory = sprintf(
            '%s/public/%s',
            rtrim($this->projectDir, '/'),
            self::AVATAR_DIRECTORY
        );

        /*
         * Create directory if it doesn't exist.
         */
        if (!is_dir($uploadDirectory)) {
            if (!mkdir(
                $uploadDirectory,
                0755,
                true
            ) && !is_dir($uploadDirectory)) {
                throw new \RuntimeException(
                    'Unable to create avatar upload directory.'
                );
            }
        }

        /*
         * Generate a completely unique filename.
         *
         * Never trust the original filename.
         */
        $extension = strtolower(
            $file->guessExtension()
                ?? $file->getClientOriginalExtension()
                ?? 'bin'
        );

        $filename = sprintf(
            '%s.%s',
            bin2hex(random_bytes(16)),
            $extension
        );

        try {

            $file->move(
                $uploadDirectory,
                $filename
            );

        } catch (\Throwable $e) {

            throw new \RuntimeException(
                'Unable to store uploaded avatar.',
                0,
                $e
            );
        }

        return sprintf(
            '/%s/%s',
            self::AVATAR_DIRECTORY,
            $filename
        );
    }

    /**
     * Delete a previously uploaded avatar.
     *
     * External URLs are deliberately ignored.
     */
    public function deleteAvatar(
        ?string $avatarPath
    ): void {

        if (!$avatarPath) {
            return;
        }

        /*
         * Only delete files belonging to our local
         * avatar storage.
         */
        $prefix = '/' . self::AVATAR_DIRECTORY . '/';

        if (!str_starts_with(
            $avatarPath,
            $prefix
        )) {
            return;
        }

        $filename = basename($avatarPath);

        /*
         * Prevent path traversal.
         */
        if (
            $filename === '.' ||
            $filename === '..' ||
            $filename !== basename($filename)
        ) {
            return;
        }

        $filePath = sprintf(
            '%s/public/%s/%s',
            rtrim($this->projectDir, '/'),
            self::AVATAR_DIRECTORY,
            $filename
        );

        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }

    /**
     * Validate avatar upload.
     */
    private function validateAvatar(
        UploadedFile $file
    ): void {

        if (!$file->isValid()) {
            throw new \InvalidArgumentException(
                'Uploaded file is invalid.'
            );
        }

        /*
         * Maximum avatar size: 5 MB.
         */
        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new \InvalidArgumentException(
                'Avatar must not exceed 5 MB.'
            );
        }

        /*
         * Validate the actual MIME type rather than
         * trusting the filename extension.
         */
        $mimeType = $file->getMimeType();

        $allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/webp',
        ];

        if (!in_array(
            $mimeType,
            $allowedMimeTypes,
            true
        )) {
            throw new \InvalidArgumentException(
                'Avatar must be a JPEG, JPG, PNG, or WebP image.'
            );
        }
    }
    





    /**
 * Upload a ride image.
 *
 * Returns the public URL/path stored in Ride.imageUrl.
 */
public function uploadRideImage(
    UploadedFile $file
): string {
    $this->validateRideImage($file);

    $directory = 'uploads/rides';

    $uploadDirectory = sprintf(
        '%s/public/%s',
        rtrim($this->projectDir, '/'),
        $directory
    );

    if (!is_dir($uploadDirectory)) {
        if (
            !mkdir($uploadDirectory, 0755, true)
            && !is_dir($uploadDirectory)
        ) {
            throw new \RuntimeException(
                'Unable to create ride image upload directory.'
            );
        }
    }

    $extension = strtolower(
        $file->guessExtension()
        ?? $file->getClientOriginalExtension()
        ?? 'bin'
    );

    $filename = sprintf(
        '%s.%s',
        bin2hex(random_bytes(16)),
        $extension
    );

    try {
        $file->move(
            $uploadDirectory,
            $filename
        );
    } catch (\Throwable $e) {
        throw new \RuntimeException(
            'Unable to store ride image.',
            0,
            $e
        );
    }

    return sprintf(
        '/%s/%s',
        $directory,
        $filename
    );
}

/**
 * Delete a previously uploaded ride image.
 */
public function deleteRideImage(
    ?string $imagePath
): void {
    if (!$imagePath) {
        return;
    }

    $prefix = '/uploads/rides/';

    if (!str_starts_with($imagePath, $prefix)) {
        return;
    }

    $filename = basename($imagePath);

    if (
        $filename === '.'
        || $filename === '..'
        || $filename !== basename($filename)
    ) {
        return;
    }

    $filePath = sprintf(
        '%s/public/uploads/rides/%s',
        rtrim($this->projectDir, '/'),
        $filename
    );

    if (is_file($filePath)) {
        @unlink($filePath);
    }
}

/**
 * Validate ride image.
 */
private function validateRideImage(
    UploadedFile $file
): void {
    if (!$file->isValid()) {
        throw new \InvalidArgumentException(
            'Uploaded ride image is invalid.'
        );
    }

    if ($file->getSize() > 10 * 1024 * 1024) {
        throw new \InvalidArgumentException(
            'Ride image must not exceed 10 MB.'
        );
    }

    $mimeType = $file->getMimeType();

    $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    if (!in_array(
        $mimeType,
        $allowedMimeTypes,
        true
    )) {
        throw new \InvalidArgumentException(
            'Ride image must be a JPEG, PNG, or WebP image.'
        );
    }
}
}
