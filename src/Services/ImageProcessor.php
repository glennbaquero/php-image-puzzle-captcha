<?php

namespace PuzzleCaptcha\Services;

use Intervention\Image\ImageManagerStatic as Image;

class ImageProcessor
{
    private int $targetWidth;
    private int $targetHeight;
    private int $quality;
    private bool $stripMetadata;
    
    public function __construct(
        int $targetWidth = 300,
        int $targetHeight = 300,
        int $quality = 85,
        bool $stripMetadata = true
    ) {
        $this->targetWidth = $targetWidth;
        $this->targetHeight = $targetHeight;
        $this->quality = $quality;
        $this->stripMetadata = $stripMetadata;
    }
    
    /**
     * Process and normalize uploaded image
     */
    public function processUploadedImage(string $sourcePath, string $destinationPath, string $format = 'png'): bool
    {
        try {
            // Load image
            $image = Image::make($sourcePath);
            
            // Strip metadata for security
            if ($this->stripMetadata) {
                $this->stripExifData($image);
            }
            
            // Normalize and resize
            $image = $this->normalizeImage($image);
            
            // Save processed image
            $this->saveImage($image, $destinationPath, $format);
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Image processing error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Normalize image (resize, orient, optimize)
     */
    private function normalizeImage($image)
    {
        // Auto-orient based on EXIF data
        $image->orientate();
        
        // Resize to target dimensions while maintaining aspect ratio
        $image->fit($this->targetWidth, $this->targetHeight, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize(); // Prevent upsizing smaller images
        });
        
        // Ensure image is exactly target size with white background
        $canvas = Image::canvas($this->targetWidth, $this->targetHeight, '#ffffff');
        $canvas->insert($image, 'center');
        
        return $canvas;
    }
    
    /**
     * Strip EXIF and metadata from image
     */
    private function stripExifData($image): void
    {
        // Intervention Image strips most metadata by default when encoding
        $image->backup();
    }
    
    /**
     * Save image in specified format
     */
    private function saveImage($image, string $path, string $format): void
    {
        $directory = dirname($path);
        
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        switch (strtolower($format)) {
            case 'jpg':
            case 'jpeg':
                $image->encode('jpg', $this->quality)->save($path);
                break;
                
            case 'png':
                // PNG quality is different (0-9, where 9 is maximum compression)
                $pngQuality = (int)(9 - ($this->quality / 100 * 9));
                $image->encode('png', $pngQuality)->save($path);
                break;
                
            default:
                $image->save($path);
        }
    }
    
    /**
     * Process multiple images from upload
     */
    public function processBatchUpload(array $files, string $destinationDir, string $prefix = 'image'): array
    {
        $results = [];
        $counter = 1;
        
        foreach ($files as $file) {
            if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                continue;
            }
            
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = $prefix . $counter . '.' . ($extension === 'jpg' || $extension === 'jpeg' ? 'jpg' : 'png');
            $destination = $destinationDir . '/' . $filename;
            
            $success = $this->processUploadedImage($file['tmp_name'], $destination);
            
            $results[] = [
                'filename' => $filename,
                'path' => $destination,
                'success' => $success,
                'original_name' => $file['name']
            ];
            
            $counter++;
        }
        
        return $results;
    }
    
    /**
     * Create thumbnail from image
     */
    public function createThumbnail(string $sourcePath, string $destinationPath, int $width = 150, int $height = 150): bool
    {
        try {
            $image = Image::make($sourcePath);
            
            $image->fit($width, $height, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            
            $image->save($destinationPath);
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Thumbnail creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Convert image format
     */
    public function convertFormat(string $sourcePath, string $destinationPath, string $format): bool
    {
        try {
            $image = Image::make($sourcePath);
            
            if ($this->stripMetadata) {
                $this->stripExifData($image);
            }
            
            $this->saveImage($image, $destinationPath, $format);
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Format conversion error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate and sanitize image
     */
    public function sanitizeImage(string $imagePath): bool
    {
        try {
            // Re-encode image to remove any malicious code
            $image = Image::make($imagePath);
            
            // Strip metadata
            $this->stripExifData($image);
            
            // Save back
            $image->save($imagePath);
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Image sanitization error: " . $e->getMessage());
            return false;
        }
    }
}
