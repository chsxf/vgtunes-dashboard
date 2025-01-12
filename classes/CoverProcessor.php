<?php

final class CoverProcessor
{
    public static function getProcessedCovers(string $coverUrl): array
    {
        $srcImgData = file_get_contents($coverUrl);
        if (($srcImage = imagecreatefromstring($srcImgData)) === false) {
            throw new Exception("Unable to read source image");
        }

        $sizes = [
            'source' => self::getJPEGAsString($srcImage, 100),
            1000 => self::getWebPAsString($srcImage)
        ];

        // Reductions
        $reductions = [500, 250, 100];
        foreach ($reductions as $reduction) {
            $reducedImage = imagescale($srcImage, $reduction, mode: IMG_BICUBIC);
            if ($reducedImage === false) {
                $reducedImage = imagescale($srcImage, $reduction);
            }
            $sizes[$reduction] = self::getWebPAsString($reducedImage);
            imagedestroy($reducedImage);
        }
        imagedestroy($srcImage);

        return $sizes;
    }

    private static function getJPEGAsString(GdImage $image, int $quality = 90): string
    {
        ob_start();
        imagejpeg($image, null, $quality);
        return ob_get_clean();
    }

    private static function getWebPAsString(GdImage $image, int $quality = 60): string
    {
        ob_start();
        imagewebp($image, null, $quality);
        return ob_get_clean();
    }
}
