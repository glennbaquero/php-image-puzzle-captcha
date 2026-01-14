<?php

namespace PuzzleCaptcha\Services;

class ImageValidator
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png'];
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png'];
    private const MAX_FILE_SIZE = 5242880; // 5MB in bytes
    private const MIN_DIMENSIONS = 100; // Minimum width/height
    private const MAX_DIMENSIONS = 4000; // Maximum width/height
    
    /**
     * Validate uploaded image file
     */
    public function validate(array $file): array
    {
        $errors = [];
        
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'No file was uploaded';
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = $this->getUploadErrorMessage($file['error']);
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Validate file size
        if ($file['size'] > self::MAX_FILE_SIZE) {
            $errors[] = 'File size exceeds maximum allowed size of ' . $this->formatBytes(self::MAX_FILE_SIZE);
        }
        
        // Validate file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            $errors[] = 'Invalid file extension. Allowed: ' . implode(', ', self::ALLOWED_EXTENSIONS);
        }
        
        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            $errors[] = 'Invalid file type. Allowed: JPG, PNG';
        }
        
        // Validate image dimensions
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            $errors[] = 'File is not a valid image';
        } else {
            [$width, $height] = $imageInfo;
            
            if ($width < self::MIN_DIMENSIONS || $height < self::MIN_DIMENSIONS) {
                $errors[] = "Image dimensions too small. Minimum: {$this->MIN_DIMENSIONS}x{$this->MIN_DIMENSIONS}px";
            }
            
            if ($width > self::MAX_DIMENSIONS || $height > self::MAX_DIMENSIONS) {
                $errors[] = "Image dimensions too large. Maximum: {$this->MAX_DIMENSIONS}x{$this->MAX_DIMENSIONS}px";
            }
        }
        
        // Check for image bombs (excessive memory usage)
        if ($imageInfo !== false) {
            $memoryNeeded = $imageInfo[0] * $imageInfo[1] * 4; // RGBA
            $memoryLimit = $this->getMemoryLimit();
            
            if ($memoryNeeded > $memoryLimit * 0.5) {
                $errors[] = 'Image is too large to process safely';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'info' => $imageInfo !== false ? [
                'width' => $imageInfo[0],
                'height' => $imageInfo[1],
                'mime' => $imageInfo['mime'],
                'extension' => $extension
            ] : null
        ];
    }
    
    /**
     * Get upload error message
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
        ];
        
        return $errors[$errorCode] ?? 'Unknown upload error';
    }
    
    /**
     * Format bytes to human readable size
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    /**
     * Get PHP memory limit in bytes
     */
    private function getMemoryLimit(): int
    {
        $memoryLimit = ini_get('memory_limit');
        
        if ($memoryLimit == -1) {
            return PHP_INT_MAX;
        }
        
        $unit = strtoupper(substr($memoryLimit, -1));
        $value = (int)substr($memoryLimit, 0, -1);
        
        switch ($unit) {
            case 'G':
                $value *= 1024;
            case 'M':
                $value *= 1024;
            case 'K':
                $value *= 1024;
        }
        
        return $value;
    }
}
