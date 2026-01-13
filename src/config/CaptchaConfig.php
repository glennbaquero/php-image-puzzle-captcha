<?php

namespace PuzzleCaptcha\Config;

class CaptchaConfig
{
    public const MAIN_IMAGE_COUNT = 15;
    public const MASK_IMAGE_COUNT = 7;
    public const IMAGE_SIZE = 300;
    public const CROP_SIZE = 120;
    public const VERIFICATION_MARGIN = 15;
    public const OUTLINE_WIDTH = 2;
    public const BLUR_INTENSITY = 50;
    
    private string $imagesPath;
    private string $storageBasePath;
    private string $storageFolder;
    private string $publicUrl;
    
    public function __construct(
        string $imagesPath = null,
        string $storageBasePath = null,
        string $storageFolder = 'puzzle_images',
        string $publicUrl = null
    ) {
        $this->imagesPath = $imagesPath ?? __DIR__ . '/../../images';
        $this->storageBasePath = $storageBasePath ?? __DIR__ . '/../../storage';
        $this->storageFolder = $storageFolder;
        $this->publicUrl = $publicUrl ?? '/storage';
    }
    
    public function getImagesPath(): string
    {
        return $this->imagesPath;
    }
    
    public function getStorageBasePath(): string
    {
        return $this->storageBasePath;
    }
    
    public function getStorageFolder(): string
    {
        return $this->storageFolder;
    }
    
    public function getPublicUrl(): string
    {
        return $this->publicUrl;
    }
    
    public function getStoragePath(): string
    {
        return $this->storageBasePath . '/' . $this->storageFolder;
    }
    
    public function getMainImagePath(int $number): string
    {
        return "{$this->imagesPath}/a{$number}.png";
    }
    
    public function getMaskImagePath(int $number): string
    {
        return "{$this->imagesPath}/{$number}.png";
    }
}
