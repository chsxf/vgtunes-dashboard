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
            1000 => self::getJPEGAsString($srcImage)
        ];

        // Reductions
        $reductions = [500, 250, 100];
        foreach ($reductions as $reduction) {
            $reducedImage = imagescale($srcImage, $reduction, mode: IMG_BICUBIC);
            $sizes[$reduction] = self::getJPEGAsString($reducedImage);
            imagedestroy($reducedImage);
        }
        imagedestroy($srcImage);

        return $sizes;
    }

    private static function getJPEGAsString(GdImage $image): string
    {
        ob_start();
        imagejpeg($image, null, 90);
        return ob_get_clean();
    }
}
