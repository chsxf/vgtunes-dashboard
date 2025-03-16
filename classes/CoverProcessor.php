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
            'source' => self::getJPEGAsString($srcImage, 100)
        ];

        // Converting to square if needed
        $srcWidth = imagesx($srcImage);
        $srcHeight = imagesy($srcImage);
        if ($srcWidth != $srcHeight) {
            $dstWidth = min(1000, $srcWidth);
            $dstHeight = min(1000, $srcHeight);

            $squareSize = max($dstWidth, $dstHeight);
            $reductionSource = imagecreatetruecolor($squareSize, $squareSize);

            $dstX = floor(($squareSize - $dstWidth) / 2);
            $dstY = floor(($squareSize - $dstHeight) / 2);

            imagecopyresampled($reductionSource, $srcImage, $dstX, $dstY, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);
            imagedestroy($srcImage);
            $srcImage = $reductionSource;
        }

        // Reductions
        $reductions = [1000, 500, 250, 100];
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
