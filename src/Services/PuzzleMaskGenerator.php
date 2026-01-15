<?php

namespace PuzzleCaptcha\Services;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Geometry\Factories\CircleFactory;
use Intervention\Image\Geometry\Factories\EllipseFactory;

class PuzzleMaskGenerator
{
    private ImageManager $manager;
    private int $pieceSize;
    private int $tabSize;
    
    public function __construct(int $pieceSize = 120, int $tabSize = 20)
    {
        $this->manager = new ImageManager(new Driver());
        $this->pieceSize = $pieceSize;
        $this->tabSize = $tabSize;
    }
    
    /**
     * Create a jigsaw puzzle piece shape mask
     * 
     * @param string $type Type of puzzle piece: 'classic', 'rounded', 'star', 'heart'
     * @return ImageInterface
     */
    public function createPuzzleMask(string $type = 'classic'): ImageInterface
    {
        return match($type) {
            'classic' => $this->createClassicJigsawMask(),
            'rounded' => $this->createRoundedMask(),
            'star' => $this->createStarMask(),
            'heart' => $this->createHeartMask(),
            'hexagon' => $this->createHexagonMask(),
            'circle' => $this->createCircleMask(),
            default => $this->createClassicJigsawMask()
        };
    }
    
    /**
     * Create classic jigsaw puzzle piece with tabs and blanks
     */
    private function createClassicJigsawMask(): ImageInterface
    {
        $size = $this->pieceSize;
        $tab = $this->tabSize;
        
        // Create transparent canvas
        $mask = $this->manager->create($size, $size)->fill('rgba(0, 0, 0, 0)');
        
        // Draw the base shape using GD
        $gd = imagecreatetruecolor($size, $size);
        imagesavealpha($gd, true);
        $transparent = imagecolorallocatealpha($gd, 0, 0, 0, 127);
        imagefill($gd, 0, 0, $transparent);
        
        $white = imagecolorallocate($gd, 255, 255, 255);
        
        // Create points for jigsaw shape
        $points = $this->generateJigsawPoints($size, $tab);
        
        // Fill the polygon
        imagefilledpolygon($gd, $points, count($points) / 2, $white);
        
        // Convert GD resource to Intervention image
        ob_start();
        imagepng($gd);
        $imageData = ob_get_clean();
        imagedestroy($gd);
        
        return $this->manager->read($imageData);
    }
    
    /**
     * Generate jigsaw puzzle piece points
     */
    private function generateJigsawPoints(int $size, int $tab): array
    {
        $points = [];
        $halfTab = $tab / 2;
        $center = $size / 2;
        
        // Top edge with tab
        $points[] = 0; $points[] = 0;
        $points[] = $center - $halfTab; $points[] = 0;
        
        // Top tab (protruding)
        $points[] = $center - $halfTab; $points[] = -$tab;
        $points[] = $center - $halfTab + 5; $points[] = -$tab - 5;
        $points[] = $center + $halfTab - 5; $points[] = -$tab - 5;
        $points[] = $center + $halfTab; $points[] = -$tab;
        
        $points[] = $center + $halfTab; $points[] = 0;
        $points[] = $size; $points[] = 0;
        
        // Right edge with blank
        $points[] = $size; $points[] = $center - $halfTab;
        
        // Right blank (indented)
        $points[] = $size + $tab; $points[] = $center - $halfTab;
        $points[] = $size + $tab + 5; $points[] = $center - $halfTab + 5;
        $points[] = $size + $tab + 5; $points[] = $center + $halfTab - 5;
        $points[] = $size + $tab; $points[] = $center + $halfTab;
        
        $points[] = $size; $points[] = $center + $halfTab;
        $points[] = $size; $points[] = $size;
        
        // Bottom edge
        $points[] = 0; $points[] = $size;
        
        return $points;
    }
    
    /**
     * Create rounded puzzle piece
     */
    private function createRoundedMask(): ImageInterface
    {
        $size = $this->pieceSize;
        
        $gd = imagecreatetruecolor($size, $size);
        imagesavealpha($gd, true);
        $transparent = imagecolorallocatealpha($gd, 0, 0, 0, 127);
        imagefill($gd, 0, 0, $transparent);
        
        $white = imagecolorallocate($gd, 255, 255, 255);
        
        // Draw rounded rectangle
        $radius = 20;
        imagefilledarc($gd, $radius, $radius, $radius * 2, $radius * 2, 180, 270, $white, IMG_ARC_PIE);
        imagefilledarc($gd, $size - $radius, $radius, $radius * 2, $radius * 2, 270, 0, $white, IMG_ARC_PIE);
        imagefilledarc($gd, $size - $radius, $size - $radius, $radius * 2, $radius * 2, 0, 90, $white, IMG_ARC_PIE);
        imagefilledarc($gd, $radius, $size - $radius, $radius * 2, $radius * 2, 90, 180, $white, IMG_ARC_PIE);
        
        imagefilledrectangle($gd, $radius, 0, $size - $radius, $size, $white);
        imagefilledrectangle($gd, 0, $radius, $size, $size - $radius, $white);
        
        ob_start();
        imagepng($gd);
        $imageData = ob_get_clean();
        imagedestroy($gd);
        
        return $this->manager->read($imageData);
    }
    
    /**
     * Create star-shaped mask
     */
    private function createStarMask(): ImageInterface
    {
        $size = $this->pieceSize;
        $center = $size / 2;
        $outerRadius = $size / 2 - 5;
        $innerRadius = $outerRadius / 2.5;
        $points = 5;
        
        $gd = imagecreatetruecolor($size, $size);
        imagesavealpha($gd, true);
        $transparent = imagecolorallocatealpha($gd, 0, 0, 0, 127);
        imagefill($gd, 0, 0, $transparent);
        
        $white = imagecolorallocate($gd, 255, 255, 255);
        
        $starPoints = [];
        for ($i = 0; $i < $points * 2; $i++) {
            $angle = M_PI / 2 + ($i * M_PI / $points);
            $radius = ($i % 2 === 0) ? $outerRadius : $innerRadius;
            
            $starPoints[] = $center + ($radius * cos($angle));
            $starPoints[] = $center - ($radius * sin($angle));
        }
        
        imagefilledpolygon($gd, $starPoints, count($starPoints) / 2, $white);
        
        ob_start();
        imagepng($gd);
        $imageData = ob_get_clean();
        imagedestroy($gd);
        
        return $this->manager->read($imageData);
    }
    
    /**
     * Create heart-shaped mask
     */
    private function createHeartMask(): ImageInterface
    {
        $size = $this->pieceSize;
        
        $gd = imagecreatetruecolor($size, $size);
        imagesavealpha($gd, true);
        $transparent = imagecolorallocatealpha($gd, 0, 0, 0, 127);
        imagefill($gd, 0, 0, $transparent);
        
        $white = imagecolorallocate($gd, 255, 255, 255);
        
        // Generate heart shape points
        $heartPoints = [];
        $centerX = $size / 2;
        $scale = $size / 3;
        
        for ($t = 0; $t <= 2 * M_PI; $t += 0.1) {
            $x = $centerX + $scale * (16 * pow(sin($t), 3));
            $y = 20 + $scale * (13 * cos($t) - 5 * cos(2 * $t) - 2 * cos(3 * $t) - cos(4 * $t));
            
            $heartPoints[] = $x;
            $heartPoints[] = -$y + $size - 10;
        }
        
        imagefilledpolygon($gd, $heartPoints, count($heartPoints) / 2, $white);
        
        ob_start();
        imagepng($gd);
        $imageData = ob_get_clean();
        imagedestroy($gd);
        
        return $this->manager->read($imageData);
    }
    
    /**
     * Create hexagon-shaped mask
     */
    private function createHexagonMask(): ImageInterface
    {
        $size = $this->pieceSize;
        $center = $size / 2;
        $radius = $size / 2 - 10;
        
        $gd = imagecreatetruecolor($size, $size);
        imagesavealpha($gd, true);
        $transparent = imagecolorallocatealpha($gd, 0, 0, 0, 127);
        imagefill($gd, 0, 0, $transparent);
        
        $white = imagecolorallocate($gd, 255, 255, 255);
        
        $hexPoints = [];
        for ($i = 0; $i < 6; $i++) {
            $angle = M_PI / 3 * $i;
            $hexPoints[] = $center + $radius * cos($angle);
            $hexPoints[] = $center + $radius * sin($angle);
        }
        
        imagefilledpolygon($gd, $hexPoints, 6, $white);
        
        ob_start();
        imagepng($gd);
        $imageData = ob_get_clean();
        imagedestroy($gd);
        
        return $this->manager->read($imageData);
    }
    
    /**
     * Create circle mask
     */
    private function createCircleMask(): ImageInterface
    {
        $size = $this->pieceSize;
        $center = $size / 2;
        $radius = $size / 2 - 5;
        
        $gd = imagecreatetruecolor($size, $size);
        imagesavealpha($gd, true);
        $transparent = imagecolorallocatealpha($gd, 0, 0, 0, 127);
        imagefill($gd, 0, 0, $transparent);
        
        $white = imagecolorallocate($gd, 255, 255, 255);
        imagefilledellipse($gd, $center, $center, $radius * 2, $radius * 2, $white);
        
        ob_start();
        imagepng($gd);
        $imageData = ob_get_clean();
        imagedestroy($gd);
        
        return $this->manager->read($imageData);
    }
    
    /**
     * Save mask to file
     */
    public function saveMask(ImageInterface $mask, string $path): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        $mask->toPng()->save($path);
    }
}
