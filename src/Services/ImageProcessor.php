<?php

namespace PuzzleCaptcha\Services;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Interfaces\ImageInterface;

class ImageProcessor
{
    private int $targetWidth;
    private int $targetHeight;
    private int $quality;
    private bool $stripMetadata;
    private ImageManager $manager;

    public function __construct(
        int $targetWidth = 300,
        int $targetHeight = 300,
        int $quality = 85,
        bool $stripMetadata = true
    ) {
        $this->targetWidth   = $targetWidth;
        $this->targetHeight  = $targetHeight;
        $this->quality       = $quality;
        $this->stripMetadata = $stripMetadata;

        // Intervention Image v3 manager
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Process and normalize uploaded image
     */
    public function processUploadedImage(
        string $sourcePath,
        string $destinationPath,
        string $format = 'png'
    ): bool {
        try {
            $image = $this->manager->read($sourcePath);

            if ($this->stripMetadata) {
                $this->stripExifData($image);
            }

            $image = $this->normalizeImage($image);

            $this->saveImage($image, $destinationPath, $format);

            return true;
        } catch (\Throwable $e) {
            error_log('Image processing error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Normalize image (resize, orient, optimize)
     */
    private function normalizeImage(ImageInterface $image): ImageInterface
    {
        // Auto-orient based on EXIF
        $image->orient();

        // Resize while keeping aspect ratio
        $image->resize(
            $this->targetWidth,
            $this->targetHeight,
            function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            }
        );

        // Create canvas and center image
        $canvas = $this->manager->create(
            $this->targetWidth,
            $this->targetHeight
        );

        $canvas->fill('#ffffff');
        $canvas->place($image, 'center');

        return $canvas;
    }

    /**
     * Strip EXIF and metadata
     */
    private function stripExifData(ImageInterface $image): void
    {
        // Re-encoding removes metadata in v3
        $image->encode();
    }

    /**
     * Save image in specified format
     */
    private function saveImage(
        ImageInterface $image,
        string $path,
        string $format
    ): void {
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $format = strtolower($format);

        match ($format) {
            'jpg', 'jpeg' => $image->toJpeg($this->quality)->save($path),
            'png' => $image->toPng(
                (int) (9 - ($this->quality / 100 * 9))
            )->save($path),
            default => $image->save($path),
        };
    }

    /**
     * Process multiple images from upload
     */
    public function processBatchUpload(
        array $files,
        string $destinationDir,
        string $prefix = 'image'
    ): array {
        $results = [];
        $counter = 1;

        foreach ($files as $file) {
            if (
                !isset($file['tmp_name']) ||
                !is_uploaded_file($file['tmp_name'])
            ) {
                continue;
            }

            $extension = strtolower(
                pathinfo($file['name'], PATHINFO_EXTENSION)
            );

            $filename = $prefix . $counter . '.' .
                (in_array($extension, ['jpg', 'jpeg']) ? 'jpg' : 'png');

            $destination = rtrim($destinationDir, '/') . '/' . $filename;

            $success = $this->processUploadedImage(
                $file['tmp_name'],
                $destination
            );

            $results[] = [
                'filename'       => $filename,
                'path'           => $destination,
                'success'        => $success,
                'original_name'  => $file['name'],
            ];

            $counter++;
        }

        return $results;
    }

    /**
     * Create thumbnail from image
     */
    public function createThumbnail(
        string $sourcePath,
        string $destinationPath,
        int $width = 150,
        int $height = 150
    ): bool {
        try {
            $image = $this->manager->read($sourcePath);

            $image->resize(
                $width,
                $height,
                function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                }
            );

            $image->save($destinationPath);

            return true;
        } catch (\Throwable $e) {
            error_log('Thumbnail creation error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Convert image format
     */
    public function convertFormat(
        string $sourcePath,
        string $destinationPath,
        string $format
    ): bool {
        try {
            $image = $this->manager->read($sourcePath);

            if ($this->stripMetadata) {
                $this->stripExifData($image);
            }

            $this->saveImage($image, $destinationPath, $format);

            return true;
        } catch (\Throwable $e) {
            error_log('Format conversion error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate and sanitize image
     */
    public function sanitizeImage(string $imagePath): bool
    {
        try {
            $image = $this->manager->read($imagePath);

            if ($this->stripMetadata) {
                $this->stripExifData($image);
            }

            $image->save($imagePath);

            return true;
        } catch (\Throwable $e) {
            error_log('Image sanitization error: ' . $e->getMessage());
            return false;
        }
    }
}
