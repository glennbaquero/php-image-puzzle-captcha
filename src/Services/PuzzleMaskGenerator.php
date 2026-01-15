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
     * @return ImageInterface
     */
    public function createPuzzleMask(): ImageInterface
    {
        return $this->createClassicJigsawMask();
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
