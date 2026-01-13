<?php

declare(strict_types=1);

namespace PuzzleCaptcha\Tests;

use PHPUnit\Framework\TestCase;
use PuzzleCaptcha\Config\CaptchaConfig;

class CaptchaConfigTest extends TestCase
{
    public function testDefaultConstructorValues(): void
    {
        $config = new CaptchaConfig();

        $this->assertStringEndsWith('/images', $config->getImagesPath());
        $this->assertStringEndsWith('/storage', $config->getStorageBasePath());
        $this->assertSame('puzzle_images', $config->getStorageFolder());
        $this->assertSame('/storage', $config->getPublicUrl());
    }

    public function testCustomConstructorValues(): void
    {
        $config = new CaptchaConfig(
            '/custom/images',
            '/custom/storage',
            'custom_folder',
            '/public/storage'
        );

        $this->assertSame('/custom/images', $config->getImagesPath());
        $this->assertSame('/custom/storage', $config->getStorageBasePath());
        $this->assertSame('custom_folder', $config->getStorageFolder());
        $this->assertSame('/public/storage', $config->getPublicUrl());
    }

    public function testGetStoragePath(): void
    {
        $config = new CaptchaConfig(
            '/base/storage',
            '/base/storage',
            'captcha'
        );

        $this->assertSame(
            '/base/storage/captcha',
            $config->getStoragePath()
        );
    }

    public function testGetMainImagePath(): void
    {
        $config = new CaptchaConfig('/images');

        $this->assertSame(
            '/images/a5.png',
            $config->getMainImagePath(5)
        );
    }

    public function testGetMaskImagePath(): void
    {
        $config = new CaptchaConfig('/images');

        $this->assertSame(
            '/images/3.png',
            $config->getMaskImagePath(3)
        );
    }

    public function testClassConstants(): void
    {
        $this->assertSame(15, CaptchaConfig::MAIN_IMAGE_COUNT);
        $this->assertSame(7, CaptchaConfig::MASK_IMAGE_COUNT);
        $this->assertSame(300, CaptchaConfig::IMAGE_SIZE);
        $this->assertSame(120, CaptchaConfig::CROP_SIZE);
        $this->assertSame(15, CaptchaConfig::VERIFICATION_MARGIN);
        $this->assertSame(2, CaptchaConfig::OUTLINE_WIDTH);
        $this->assertSame(50, CaptchaConfig::BLUR_INTENSITY);
    }
}
