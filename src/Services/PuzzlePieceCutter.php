<?php

namespace PuzzleCaptcha\Services;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Interfaces\ImageInterface;

class PuzzlePieceCutter
{
    private ImageManager $manager;
    private PuzzleMaskGenerator $maskGenerator;
    
    public function __construct(PuzzleMaskGenerator $maskGenerator = null)
    {
        $this->manager = new ImageManager(new Driver());
        $this->maskGenerator = $maskGenerator ?? new PuzzleMaskGenerator();
    }
    
    /**
     * Cut puzzle piece from source image and preserve alpha transparency
     * 
     * @param ImageInterface $sourceImage The source image
     * @param ImageInterface $mask The puzzle mask shape
     * @param int $x Position X where to cut
     * @param int $y Position Y where to cut
     * @return ImageInterface Puzzle piece with transparency
     */
    public function cutPuzzlePiece(
        ImageInterface $sourceImage,
        ImageInterface $mask,
        int $x,
        int $y
    ): ImageInterface {
        $maskWidth = $mask->width();
        $maskHeight = $mask->height();
        
        // Crop the area from source image
        $croppedArea = $sourceImage->crop($maskWidth, $maskHeight, $x, $y);
        
        // Apply mask with alpha transparency preservation
        $puzzlePiece = $this->applyMaskWithAlpha($croppedArea, $mask);
        
        // Add outline for better visibility
        $puzzlePiece = $this->addOutline($puzzlePiece);
        
        return $puzzlePiece;
    }
    
    /**
     * Generate image with missing puzzle area (blurred hole)
     * 
     * @param ImageInterface $sourceImage The source image
     * @param ImageInterface $mask The puzzle mask shape
     * @param int $x Position X of missing piece
     * @param int $y Position Y of missing piece
     * @param int $blurAmount Blur intensity (default: 50)
     * @return ImageInterface Image with missing puzzle area
     */
    public function generateImageWithMissingPiece(
        ImageInterface $sourceImage,
        ImageInterface $mask,
        int $x,
        int $y,
        int $blurAmount = 50
    ): ImageInterface {
        $maskWidth = $mask->width();
        $maskHeight = $mask->height();
        
        // Clone the source image
        $resultImage = clone $sourceImage;
        
        // Create blurred overlay for the missing area
        $blurredOverlay = $this->createBlurredOverlay(
            $sourceImage,
            $mask,
            $x,
            $y,
            $blurAmount
        );
        
        // Place the blurred overlay at the position
        $resultImage->place($blurredOverlay, 'top-left', $x, $y);
        
        return $resultImage;
    }
    
    /**
     * Apply mask to image while preserving alpha transparency
     */
    private function applyMaskWithAlpha(
        ImageInterface $image,
        ImageInterface $mask
    ): ImageInterface {
        $width = $image->width();
        $height = $image->height();
        
        // Convert images to GD resources for pixel-level manipulation
        $imageGd = $this->interventionToGd($image);
        $maskGd = $this->interventionToGd($mask);
        
        // Create result image with alpha channel
        $result = imagecreatetruecolor($width, $height);
        imagesavealpha($result, true);
        $transparent = imagecolorallocatealpha($result, 0, 0, 0, 127);
        imagefill($result, 0, 0, $transparent);
        
        // Apply mask pixel by pixel
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                // Get mask pixel
                $maskColor = imagecolorat($maskGd, $x, $y);
                $maskRgba = imagecolorsforindex($maskGd, $maskColor);
                
                // If mask is not transparent (white area)
                if ($maskRgba['alpha'] < 127) {
                    // Copy pixel from source image
                    $sourceColor = imagecolorat($imageGd, $x, $y);
                    imagesetpixel($result, $x, $y, $sourceColor);
                }
            }
        }
        
        // Convert back to Intervention image
        ob_start();
        imagepng($result);
        $imageData = ob_get_clean();
        
        imagedestroy($imageGd);
        imagedestroy($maskGd);
        imagedestroy($result);
        
        return $this->manager->read($imageData);
    }
    
    /**
     * Create blurred overlay for missing piece area
     */
    private function createBlurredOverlay(
        ImageInterface $sourceImage,
        ImageInterface $mask,
        int $x,
        int $y,
        int $blurAmount
    ): ImageInterface {
        $maskWidth = $mask->width();
        $maskHeight = $mask->height();
        
        // Crop the area
        $area = $sourceImage->crop($maskWidth, $maskHeight, $x, $y);
        
        // Apply heavy blur
        for ($i = 0; $i < $blurAmount; $i++) {
            $area->blur(1);
        }
        
        // Darken the blurred area
        $area->brightness(-20);
        
        // Apply mask to blurred area
        $blurredMasked = $this->applyMaskWithAlpha($area, $mask);
        
        return $blurredMasked;
    }
    
    /**
     * Add white outline to puzzle piece for visibility
     */
    private function addOutline(ImageInterface $puzzlePiece, int $outlineWidth = 2): ImageInterface
    {
        $width = $puzzlePiece->width();
        $height = $puzzlePiece->height();
        
        $pieceGd = $this->interventionToGd($puzzlePiece);
        
        // Create result with outline
        $result = imagecreatetruecolor($width, $height);
        imagesavealpha($result, true);
        $transparent = imagecolorallocatealpha($result, 0, 0, 0, 127);
        imagefill($result, 0, 0, $transparent);
        
        $white = imagecolorallocate($result, 255, 255, 255);
        
        // Draw outline by checking edges
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $currentColor = imagecolorat($pieceGd, $x, $y);
                $currentRgba = imagecolorsforindex($pieceGd, $currentColor);
                
                // If pixel is not transparent
                if ($currentRgba['alpha'] < 127) {
                    // Check if it's an edge pixel
                    if ($this->isEdgePixel($pieceGd, $x, $y, $width, $height, $outlineWidth)) {
                        imagesetpixel($result, $x, $y, $white);
                    } else {
                        // Copy original pixel
                        imagesetpixel($result, $x, $y, $currentColor);
                    }
                }
            }
        }
        
        ob_start();
        imagepng($result);
        $imageData = ob_get_clean();
        
        imagedestroy($pieceGd);
        imagedestroy($result);
        
        return $this->manager->read($imageData);
    }
    
    /**
     * Check if pixel is on the edge (has transparent neighbors)
     */
    private function isEdgePixel(
        $image,
        int $x,
        int $y,
        int $width,
        int $height,
        int $edgeWidth
    ): bool {
        for ($dx = -$edgeWidth; $dx <= $edgeWidth; $dx++) {
            for ($dy = -$edgeWidth; $dy <= $edgeWidth; $dy++) {
                if (abs($dx) + abs($dy) <= $edgeWidth) {
                    $nx = $x + $dx;
                    $ny = $y + $dy;
                    
                    if ($nx >= 0 && $nx < $width && $ny >= 0 && $ny < $height) {
                        $neighborColor = imagecolorat($image, $nx, $ny);
                        $neighborRgba = imagecolorsforindex($image, $neighborColor);
                        
                        // Found transparent neighbor
                        if ($neighborRgba['alpha'] == 127) {
                            return true;
                        }
                    } else {
                        // Outside bounds = transparent
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Convert Intervention Image to GD resource
     */
    private function interventionToGd(ImageInterface $image)
    {
        $encoded = $image->toPng()->toString();
        return imagecreatefromstring($encoded);
    }
    
    /**
     * Create complete puzzle captcha (piece + image with hole)
     */
    public function createPuzzleCaptcha(
        ImageInterface $sourceImage,
        int $x = null,
        int $y = null
    ): array {
        // Generate or use provided coordinates
        $imageWidth = $sourceImage->width();
        $imageHeight = $sourceImage->height();
        $pieceSize = 120;
        
        $x = $x ?? rand(0, $imageWidth - $pieceSize);
        $y = $y ?? rand(0, $imageHeight - $pieceSize);
        
        // Create mask
        $mask = $this->maskGenerator->createPuzzleMask();
        
        // Cut puzzle piece
        $puzzlePiece = $this->cutPuzzlePiece($sourceImage, $mask, $x, $y);
        
        // Generate image with missing piece
        $imageWithHole = $this->generateImageWithMissingPiece($sourceImage, $mask, $x, $y);
        
        return [
            'puzzle_piece' => $puzzlePiece,
            'image_with_hole' => $imageWithHole,
            'position' => ['x' => $x, 'y' => $y],
            'mask_type' => $maskType
        ];
    }
}
