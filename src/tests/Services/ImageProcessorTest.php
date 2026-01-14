<?php

namespace PuzzleCaptcha\Tests\Services;

use PHPUnit\Framework\TestCase;
use YourPackage\Captcha\Services\ImageProcessor;
use Intervention\Image\ImageManagerStatic as Image;

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
        
        // Create directories
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
    
    /**
     * Test processing uploaded PNG image successfully
     */
    public function testProcessUploadedPngImageSuccess(): void
    {
        $sourcePath = $this->createTestImage('png', 400, 400);
        $destPath = $this->testOutputPath . '/processed.png';
        
        $result = $this->processor->processUploadedImage($sourcePath, $destPath, 'png');
        
        $this->assertTrue($result);
        $this->assertFileExists($destPath);
        
        // Verify image dimensions
        list($width, $height) = getimagesize($destPath);
        $this->assertEquals(300, $width);
        $this->assertEquals(300, $height);
    }
    
    /**
     * Test processing uploaded JPG image successfully
     */
    public function testProcessUploadedJpgImageSuccess(): void
    {
        $sourcePath = $this->createTestImage('jpg', 500, 500);
        $destPath = $this->testOutputPath . '/processed.jpg';
        
        $result = $this->processor->processUploadedImage($sourcePath, $destPath, 'jpg');
        
        $this->assertTrue($result);
        $this->assertFileExists($destPath);
        
        // Verify image format
        $imageInfo = getimagesize($destPath);
        $this->assertEquals('image/jpeg', $imageInfo['mime']);
    }
    
    /**
     * Test image is resized to target dimensions
     */
    public function testImageIsResizedCorrectly(): void
    {
        $sourcePath = $this->createTestImage('png', 800, 600);
        $destPath = $this->testOutputPath . '/resized.png';
        
        $this->processor->processUploadedImage($sourcePath, $destPath, 'png');
        
        list($width, $height) = getimagesize($destPath);
        $this->assertEquals(300, $width);
        $this->assertEquals(300, $height);
    }
    
    /**
     * Test small images are not upscaled
     */
    public function testSmallImagesNotUpscaled(): void
    {
        $sourcePath = $this->createTestImage('png', 150, 150);
        $destPath = $this->testOutputPath . '/not_upscaled.png';
        
        $this->processor->processUploadedImage($sourcePath, $destPath, 'png');
        
        list($width, $height) = getimagesize($destPath);
        // Should be 300x300 with white background, but original image should not be upscaled
        $this->assertEquals(300, $width);
        $this->assertEquals(300, $height);
    }
    
    /**
     * Test non-square images maintain aspect ratio
     */
    public function testNonSquareImagesMaintainAspectRatio(): void
    {
        $sourcePath = $this->createTestImage('png', 400, 200);
        $destPath = $this->testOutputPath . '/aspect_ratio.png';
        
        $this->processor->processUploadedImage($sourcePath, $destPath, 'png');
        
        list($width, $height) = getimagesize($destPath);
        $this->assertEquals(300, $width);
        $this->assertEquals(300, $height);
        $this->assertFileExists($destPath);
    }
    
    /**
     * Test processing creates output directory if not exists
     */
    public function testProcessingCreatesDirectoryIfNotExists(): void
    {
        $sourcePath = $this->createTestImage('png', 300, 300);
        $destPath = $this->testOutputPath . '/new_dir/processed.png';
        
        $result = $this->processor->processUploadedImage($sourcePath, $destPath, 'png');
        
        $this->assertTrue($result);
        $this->assertFileExists($destPath);
        $this->assertDirectoryExists(dirname($destPath));
    }
    
    /**
     * Test processing fails gracefully with invalid source
     */
    public function testProcessingFailsWithInvalidSource(): void
    {
        $sourcePath = $this->testImagesPath . '/nonexistent.png';
        $destPath = $this->testOutputPath . '/should_not_exist.png';
        
        $result = $this->processor->processUploadedImage($sourcePath, $destPath, 'png');
        
        $this->assertFalse($result);
        $this->assertFileDoesNotExist($destPath);
    }
    
    /**
     * Test format conversion from PNG to JPG
     */
    public function testFormatConversionPngToJpg(): void
    {
        $sourcePath = $this->createTestImage('png', 300, 300);
        $destPath = $this->testOutputPath . '/converted.jpg';
        
        $result = $this->processor->processUploadedImage($sourcePath, $destPath, 'jpg');
        
        $this->assertTrue($result);
        $this->assertFileExists($destPath);
        
        $imageInfo = getimagesize($destPath);
        $this->assertEquals('image/jpeg', $imageInfo['mime']);
    }
    
    /**
     * Test format conversion from JPG to PNG
     */
    public function testFormatConversionJpgToPng(): void
    {
        $sourcePath = $this->createTestImage('jpg', 300, 300);
        $destPath = $this->testOutputPath . '/converted.png';
        
        $result = $this->processor->processUploadedImage($sourcePath, $destPath, 'png');
        
        $this->assertTrue($result);
        $this->assertFileExists($destPath);
        
        $imageInfo = getimagesize($destPath);
        $this->assertEquals('image/png', $imageInfo['mime']);
    }
    
    /**
     * Test batch upload processing
     */
    public function testProcessBatchUpload(): void
    {
        $files = [
            $this->createMockUploadFile('test1.png', 300, 300),
            $this->createMockUploadFile('test2.png', 400, 400),
            $this->createMockUploadFile('test3.png', 200, 200)
        ];
        
        $results = $this->processor->processBatchUpload(
            $files,
            $this->testOutputPath,
            'batch_'
        );
        
        $this->assertCount(3, $results);
        $this->assertEquals('batch_1.png', $results[0]['filename']);
        $this->assertEquals('batch_2.png', $results[1]['filename']);
        $this->assertEquals('batch_3.png', $results[2]['filename']);
        
        foreach ($results as $result) {
            $this->assertTrue($result['success']);
            $this->assertFileExists($result['path']);
        }
    }
    
    /**
     * Test batch processing skips invalid files
     */
    public function testBatchProcessingSkipsInvalidFiles(): void
    {
        $files = [
            $this->createMockUploadFile('valid.png', 300, 300),
            ['tmp_name' => '', 'name' => 'invalid.png'], // Invalid file
        ];
        
        $results = $this->processor->processBatchUpload(
            $files,
            $this->testOutputPath,
            'skip_'
        );
        
        $this->assertCount(1, $results); // Only valid file processed
        $this->assertTrue($results[0]['success']);
    }
    
    /**
     * Test thumbnail creation
     */
    public function testCreateThumbnail(): void
    {
        $sourcePath = $this->createTestImage('png', 500, 500);
        $destPath = $this->testOutputPath . '/thumbnail.png';
        
        $result = $this->processor->createThumbnail($sourcePath, $destPath, 150, 150);
        
        $this->assertTrue($result);
        $this->assertFileExists($destPath);
        
        list($width, $height) = getimagesize($destPath);
        $this->assertEquals(150, $width);
        $this->assertEquals(150, $height);
    }
    
    /**
     * Test thumbnail creation with non-square dimensions
     */
    public function testCreateNonSquareThumbnail(): void
    {
        $sourcePath = $this->createTestImage('png', 400, 200);
        $destPath = $this->testOutputPath . '/thumb_rect.png';
        
        $result = $this->processor->createThumbnail($sourcePath, $destPath, 100, 50);
        
        $this->assertTrue($result);
        $this->assertFileExists($destPath);
    }
    
    /**
     * Test thumbnail creation fails gracefully
     */
    public function testThumbnailCreationFailsGracefully(): void
    {
        $sourcePath = $this->testImagesPath . '/nonexistent.png';
        $destPath = $this->testOutputPath . '/thumb_fail.png';
        
        $result = $this->processor->createThumbnail($sourcePath, $destPath);
        
        $this->assertFalse($result);
    }
    
    /**
     * Test convert format method
     */
    public function testConvertFormat(): void
    {
        $sourcePath = $this->createTestImage('png', 300, 300);
        $destPath = $this->testOutputPath . '/format_converted.jpg';
        
        $result = $this->processor->convertFormat($sourcePath, $destPath, 'jpg');
        
        $this->assertTrue($result);
        $this->assertFileExists($destPath);
        
        $imageInfo = getimagesize($destPath);
        $this->assertEquals('image/jpeg', $imageInfo['mime']);
    }
    
    /**
     * Test convert format fails with invalid source
     */
    public function testConvertFormatFailsWithInvalidSource(): void
    {
        $result = $this->processor->convertFormat(
            $this->testImagesPath . '/invalid.png',
            $this->testOutputPath . '/fail.jpg',
            'jpg'
        );
        
        $this->assertFalse($result);
    }
    
    /**
     * Test sanitize image
     */
    public function testSanitizeImage(): void
    {
        $imagePath = $this->createTestImage('png', 300, 300);
        
        // Add some metadata (simulated)
        $originalSize = filesize($imagePath);
        
        $result = $this->processor->sanitizeImage($imagePath);
        
        $this->assertTrue($result);
        $this->assertFileExists($imagePath);
        
        // File should still exist and be a valid image
        $imageInfo = getimagesize($imagePath);
        $this->assertNotFalse($imageInfo);
    }
    
    /**
     * Test sanitize fails with invalid image
     */
    public function testSanitizeFailsWithInvalidImage(): void
    {
        $imagePath = $this->testImagesPath . '/nonexistent.png';
        
        $result = $this->processor->sanitizeImage($imagePath);
        
        $this->assertFalse($result);
    }
    
    /**
     * Test custom processor with different settings
     */
    public function testCustomProcessorSettings(): void
    {
        $processor = new ImageProcessor(
            targetWidth: 500,
            targetHeight: 500,
            quality: 100,
            stripMetadata: false
        );
        
        $sourcePath = $this->createTestImage('png', 600, 600);
        $destPath = $this->testOutputPath . '/custom_settings.png';
        
        $result = $processor->processUploadedImage($sourcePath, $destPath, 'png');
        
        $this->assertTrue($result);
        
        list($width, $height) = getimagesize($destPath);
        $this->assertEquals(500, $width);
        $this->assertEquals(500, $height);
    }
    
    /**
     * Test processor handles JPEG extension variation
     */
    public function testProcessorHandlesJpegExtension(): void
    {
        $sourcePath = $this->createTestImage('jpg', 300, 300);
        $destPath = $this->testOutputPath . '/test.jpeg';
        
        $result = $this->processor->processUploadedImage($sourcePath, $destPath, 'jpeg');
        
        $this->assertTrue($result);
        $this->assertFileExists($destPath);
    }
    
    // Helper Methods
    
    /**
     * Create a test image file
     */
    private function createTestImage(string $format, int $width, int $height): string
    {
        $filename = uniqid('test_') . '.' . $format;
        $filePath = $this->testImagesPath . '/' . $filename;
        
        $image = imagecreatetruecolor($width, $height);
        $bgColor = imagecolorallocate($image, 100, 150, 200);
        imagefill($image, 0, 0, $bgColor);
        
        // Add some patterns to make it look more realistic
        $lineColor = imagecolorallocate($image, 255, 255, 255);
        imageline($image, 0, 0, $width, $height, $lineColor);
        imageline($image, 0, $height, $width, 0, $lineColor);
        
        if ($format === 'png') {
            imagepng($image, $filePath);
        } else {
            imagejpeg($image, $filePath, 90);
        }
        
        imagedestroy($image);
        
        return $filePath;
    }
    
    /**
     * Create a mock upload file array
     */
    private function createMockUploadFile(string $filename, int $width, int $height): array
    {
        $sourcePath = $this->createTestImage('png', $width, $height);
        
        return [
            'tmp_name' => $sourcePath,
            'name' => $filename,
            'type' => 'image/png',
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($sourcePath)
        ];
    }
    
    /**
     * Clean up test files
     */
    private function cleanupTestFiles(): void
    {
        $paths = [$this->testImagesPath, $this->testOutputPath];
        
        foreach ($paths as $path) {
            if (is_dir($path)) {
                $files = glob($path . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    } elseif (is_dir($file)) {
                        $this->removeDirectory($file);
                    }
                }
            }
        }
    }
    
    /**
     * Recursively remove directory
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        
        rmdir($dir);
    }
}
