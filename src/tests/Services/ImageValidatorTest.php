<?php

namespace PuzzleCaptcha\Tests\Services;

use PHPUnit\Framework\TestCase;
use PuzzleCaptcha\Services\ImageValidator;

class ImageValidatorTest extends TestCase
{
    private ImageValidator $validator;
    private string $testImagesPath;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ImageValidator();
        $this->testImagesPath = __DIR__ . '/../fixtures/images';
        
        // Create test images directory if it doesn't exist
        if (!is_dir($this->testImagesPath)) {
            mkdir($this->testImagesPath, 0755, true);
        }
    }
    
    protected function tearDown(): void
    {
        parent::tearDown();
        // Clean up test images
        $this->cleanupTestImages();
    }
    
    /**
     * Test validation with valid PNG image
     */
    public function testValidatePngImageSuccess(): void
    {
        $testFile = $this->createValidPNGImage();
        
        $result = $this->validator->validate($testFile);
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertNotNull($result['info']);
        $this->assertEquals('png', $result['info']['extension']);
        $this->assertGreaterThan(0, $result['info']['width']);
        $this->assertGreaterThan(0, $result['info']['height']);
    }
    
    /**
     * Test validation with valid JPG image
     */
    public function testValidateJpgImageSuccess(): void
    {
        $testFile = $this->createValidJPGImage();
        
        $result = $this->validator->validate($testFile);
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals('jpg', $result['info']['extension']);
    }
    
    /**
     * Test validation fails with no file uploaded
     */
    public function testValidateNoFileUploaded(): void
    {
        $testFile = [
            'tmp_name' => '',
            'error' => UPLOAD_ERR_OK,
            'size' => 0,
            'name' => 'test.png'
        ];
        
        $result = $this->validator->validate($testFile);
        
        $this->assertFalse($result['valid']);
        $this->assertContains('No file was uploaded', $result['errors']);
    }
    
    /**
     * Test validation fails with upload error
     */
    public function testValidateUploadError(): void
    {
        $testFile = [
            'tmp_name' => '/tmp/test.png',
            'error' => UPLOAD_ERR_NO_FILE,
            'size' => 0,
            'name' => 'test.png'
        ];
        
        $result = $this->validator->validate($testFile);
        
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }
    
    /**
     * Test validation fails when file size exceeds limit
     */
    public function testValidateFileSizeExceedsLimit(): void
    {
        $testFile = $this->createValidPNGImage();
        $testFile['size'] = 10485760; // 10MB
        
        $result = $this->validator->validate($testFile);
        
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('exceeds maximum', $result['errors'][0]);
    }
    
    /**
     * Test validation fails with invalid extension
     */
    public function testValidateInvalidExtension(): void
    {
        $testFile = $this->createValidPNGImage();
        $testFile['name'] = 'test.gif';
        
        $result = $this->validator->validate($testFile);
        
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Invalid file extension', $result['errors'][0]);
    }
    
    /**
     * Test validation fails with invalid MIME type
     */
    public function testValidateInvalidMimeType(): void
    {
        // Create a text file disguised as image
        $filePath = $this->testImagesPath . '/fake.png';
        file_put_contents($filePath, 'This is not an image');
        
        $testFile = [
            'tmp_name' => $filePath,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($filePath),
            'name' => 'fake.png'
        ];
        
        $result = $this->validator->validate($testFile);
        
        $this->assertFalse($result['valid']);
        $this->assertTrue(
            $this->containsError($result['errors'], 'Invalid file type') ||
            $this->containsError($result['errors'], 'not a valid image')
        );
    }
    
    /**
     * Test validation fails when image dimensions are too small
     */
    public function testValidateDimensionsTooSmall(): void
    {
        $testFile = $this->createSmallImage(50, 50);
        
        $result = $this->validator->validate($testFile);
        
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('too small', $result['errors'][0]);
    }
    
    /**
     * Test validation fails when image dimensions are too large
     */
    public function testValidateDimensionsTooLarge(): void
    {
        $testFile = $this->createLargeImageMock();
        
        $result = $this->validator->validate($testFile);
        
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('too large', $result['errors'][0]);
    }
    
    /**
     * Test validation fails when file is not a valid image
     */
    public function testValidateNotAnImage(): void
    {
        $filePath = $this->testImagesPath . '/not_image.png';
        file_put_contents($filePath, 'Not an image content');
        
        $testFile = [
            'tmp_name' => $filePath,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($filePath),
            'name' => 'not_image.png'
        ];
        
        $result = $this->validator->validate($testFile);
        
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }
    
    /**
     * Test multiple validation errors are returned
     */
    public function testValidateMultipleErrors(): void
    {
        $filePath = $this->testImagesPath . '/invalid.txt';
        file_put_contents($filePath, 'Invalid file');
        
        $testFile = [
            'tmp_name' => $filePath,
            'error' => UPLOAD_ERR_OK,
            'size' => 10485760, // Too large
            'name' => 'invalid.txt'
        ];
        
        $result = $this->validator->validate($testFile);
        
        $this->assertFalse($result['valid']);
        $this->assertGreaterThan(1, count($result['errors']));
    }
    
    /**
     * Test validation with JPEG extension variation
     */
    public function testValidateJpegExtension(): void
    {
        $testFile = $this->createValidJPGImage();
        $testFile['name'] = 'test.jpeg';
        
        $result = $this->validator->validate($testFile);
        
        $this->assertTrue($result['valid']);
        $this->assertEquals('jpeg', $result['info']['extension']);
    }
    
    /**
     * Test validation returns correct image info
     */
    public function testValidateReturnsCorrectImageInfo(): void
    {
        $testFile = $this->createValidPNGImage(200, 150);
        
        $result = $this->validator->validate($testFile);
        
        $this->assertTrue($result['valid']);
        $this->assertEquals(200, $result['info']['width']);
        $this->assertEquals(150, $result['info']['height']);
        $this->assertEquals('image/png', $result['info']['mime']);
    }
    
    // Helper Methods
    
    /**
     * Create a valid PNG test image
     */
    private function createValidPNGImage(int $width = 300, int $height = 300): array
    {
        $filePath = $this->testImagesPath . '/valid_test.png';
        
        $image = imagecreatetruecolor($width, $height);
        $bgColor = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $bgColor);
        imagepng($image, $filePath);
        imagedestroy($image);
        
        return [
            'tmp_name' => $filePath,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($filePath),
            'name' => 'valid_test.png'
        ];
    }
    
    /**
     * Create a valid JPG test image
     */
    private function createValidJPGImage(int $width = 300, int $height = 300): array
    {
        $filePath = $this->testImagesPath . '/valid_test.jpg';
        
        $image = imagecreatetruecolor($width, $height);
        $bgColor = imagecolorallocate($image, 200, 200, 200);
        imagefill($image, 0, 0, $bgColor);
        imagejpeg($image, $filePath, 90);
        imagedestroy($image);
        
        return [
            'tmp_name' => $filePath,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($filePath),
            'name' => 'valid_test.jpg'
        ];
    }
    
    /**
     * Create a small image for dimension testing
     */
    private function createSmallImage(int $width, int $height): array
    {
        $filePath = $this->testImagesPath . '/small_test.png';
        
        $image = imagecreatetruecolor($width, $height);
        imagepng($image, $filePath);
        imagedestroy($image);
        
        return [
            'tmp_name' => $filePath,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($filePath),
            'name' => 'small_test.png'
        ];
    }
    
    /**
     * Create a mock for large image testing
     */
    private function createLargeImageMock(): array
    {
        $filePath = $this->testImagesPath . '/large_test.png';
        
        // Create a 5000x5000 image (exceeds max dimensions)
        $image = imagecreatetruecolor(5000, 5000);
        imagepng($image, $filePath);
        imagedestroy($image);
        
        return [
            'tmp_name' => $filePath,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($filePath),
            'name' => 'large_test.png'
        ];
    }
    
    /**
     * Check if errors array contains specific error message
     */
    private function containsError(array $errors, string $needle): bool
    {
        foreach ($errors as $error) {
            if (stripos($error, $needle) !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Clean up test images
     */
    private function cleanupTestImages(): void
    {
        if (is_dir($this->testImagesPath)) {
            $files = glob($this->testImagesPath . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
