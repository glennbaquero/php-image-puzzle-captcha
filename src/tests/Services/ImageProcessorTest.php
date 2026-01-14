<?php

namespace PuzzleCaptcha\Tests\Services;

use PHPUnit\Framework\TestCase;
use PuzzleCaptcha\Services\ImageProcessor;

class ImageProcessorTest extends TestCase
{
    private ImageProcessor $processor;
    private string $testImagesPath;
    private string $testOutputPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->processor = new ImageProcessor(
            targetWidth: 300,
            targetHeight: 300,
            quality: 85,
            stripMetadata: true
        );

        $this->testImagesPath = __DIR__ . '/../fixtures/test_images';
        $this->testOutputPath = __DIR__ . '/../fixtures/output';

        if (!is_dir($this->testImagesPath)) {
            mkdir($this->testImagesPath, 0755, true);
        }

        if (!is_dir($this->testOutputPath)) {
            mkdir($this->testOutputPath, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cleanupTestFiles();
    }

    public function testProcessUploadedPngImageSuccess(): void
    {
        $sourcePath = $this->createTestImage('png', 400, 400);
        $destPath = $this->testOutputPath . '/processed.png';

        $result = $this->processor->processUploadedImage($sourcePath, $destPath, 'png');

        $this->assertTrue($result);
        $this->assertFileExists($destPath);

        [$width, $height] = getimagesize($destPath);
        $this->assertEquals(300, $width);
        $this->assertEquals(300, $height);
    }

    public function testProcessUploadedJpgImageSuccess(): void
    {
        $sourcePath = $this->createTestImage('jpg', 500, 500);
        $destPath = $this->testOutputPath . '/processed.jpg';

        $result = $this->processor->processUploadedImage($sourcePath, $destPath, 'jpg');

        $this->assertTrue($result);
        $this->assertFileExists($destPath);

        $imageInfo = getimagesize($destPath);
        $this->assertEquals('image/jpeg', $imageInfo['mime']);
    }

    public function testImageIsResizedCorrectly(): void
    {
        $sourcePath = $this->createTestImage('png', 800, 600);
        $destPath = $this->testOutputPath . '/resized.png';

        $this->processor->processUploadedImage($sourcePath, $destPath, 'png');

        [$width, $height] = getimagesize($destPath);
        $this->assertEquals(300, $width);
        $this->assertEquals(300, $height);
    }

    public function testSmallImagesNotUpscaled(): void
    {
        $sourcePath = $this->createTestImage('png', 150, 150);
        $destPath = $this->testOutputPath . '/not_upscaled.png';

        $this->processor->processUploadedImage($sourcePath, $destPath, 'png');

        [$width, $height] = getimagesize($destPath);
        $this->assertEquals(300, $width);
        $this->assertEquals(300, $height);
    }

    public function testProcessingCreatesDirectoryIfNotExists(): void
    {
        $sourcePath = $this->createTestImage('png', 300, 300);
        $destPath = $this->testOutputPath . '/new_dir/processed.png';

        $result = $this->processor->processUploadedImage($sourcePath, $destPath, 'png');

        $this->assertTrue($result);
        $this->assertFileExists($destPath);
        $this->assertDirectoryExists(dirname($destPath));
    }

    public function testProcessingFailsWithInvalidSource(): void
    {
        $result = $this->processor->processUploadedImage(
            $this->testImagesPath . '/nonexistent.png',
            $this->testOutputPath . '/fail.png',
            'png'
        );

        $this->assertFalse($result);
    }

    public function testCreateThumbnail(): void
    {
        $sourcePath = $this->createTestImage('png', 500, 500);
        $destPath = $this->testOutputPath . '/thumbnail.png';

        $result = $this->processor->createThumbnail($sourcePath, $destPath, 150, 150);

        $this->assertTrue($result);
        $this->assertFileExists($destPath);

        [$width, $height] = getimagesize($destPath);
        $this->assertEquals(150, $width);
        $this->assertEquals(150, $height);
    }

    public function testConvertFormat(): void
    {
        $sourcePath = $this->createTestImage('png', 300, 300);
        $destPath = $this->testOutputPath . '/converted.jpg';

        $result = $this->processor->convertFormat($sourcePath, $destPath, 'jpg');

        $this->assertTrue($result);

        $imageInfo = getimagesize($destPath);
        $this->assertEquals('image/jpeg', $imageInfo['mime']);
    }

    public function testSanitizeImage(): void
    {
        $imagePath = $this->createTestImage('png', 300, 300);

        $result = $this->processor->sanitizeImage($imagePath);

        $this->assertTrue($result);
        $this->assertNotFalse(getimagesize($imagePath));
    }
    
    // Helper methods

    private function createTestImage(string $format, int $width, int $height): string
    {
        $filename = uniqid('test_') . '.' . $format;
        $filePath = $this->testImagesPath . '/' . $filename;

        $image = imagecreatetruecolor($width, $height);
        $bgColor = imagecolorallocate($image, 100, 150, 200);
        imagefill($image, 0, 0, $bgColor);

        if ($format === 'png') {
            imagepng($image, $filePath);
        } else {
            imagejpeg($image, $filePath, 90);
        }

        imagedestroy($image);

        return $filePath;
    }

    private function cleanupTestFiles(): void
    {
        foreach ([$this->testImagesPath, $this->testOutputPath] as $path) {
            if (!is_dir($path)) {
                continue;
            }

            foreach (glob($path . '/*') as $file) {
                is_dir($file) ? $this->removeDirectory($file) : unlink($file);
            }
        }
    }

    private function removeDirectory(string $dir): void
    {
        foreach (array_diff(scandir($dir), ['.', '..']) as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }
}
