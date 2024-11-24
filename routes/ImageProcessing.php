<?php

use chsxf\MFX\Attributes\AnonymousRoute;
use chsxf\MFX\Attributes\Route;
use chsxf\MFX\CoreManager;
use chsxf\MFX\HttpStatusCodes;
use chsxf\MFX\RequestResult;
use chsxf\MFX\Routers\BaseRouteProvider;
use chsxf\MFX\Routers\IRouteProvider;
use ZipStream\ZipStream;

final class ImageProcessing extends BaseRouteProvider
{
    #[Route, AnonymousRoute]
    public function covers(): RequestResult
    {
        try {
            $url = trim($_REQUEST['url'] ?? '');
            if (empty($url)) {
                throw new Exception('An image URL must be provided');
            }

            $srcImgData = file_get_contents($url);
            if (($srcImage = imagecreatefromstring($srcImgData)) === false) {
                throw new Exception("Unable to read source image");
            }

            $zipTempFile = tempnam(sys_get_temp_dir(), 'cov');
            $zipArchive = new ZipArchive();
            if ($zipArchive->open($zipTempFile, ZipArchive::OVERWRITE) === false) {
                throw new Exception("Unable to open the archive");
            }

            $zipArchive->addFromString('cover_1000.jpg', self::getJPEGAsString($srcImage));

            // Reductions
            $reductions = [500, 250, 100];
            foreach ($reductions as $reduction) {
                $reducedImage = imagescale($srcImage, $reduction, mode: IMG_BICUBIC);
                $zipArchive->addFromString("cover_{$reduction}.jpg", self::getJPEGAsString($reducedImage));
                imagedestroy($reducedImage);
            }
            imagedestroy($srcImage);

            $zipArchive->close();

            $this->serviceProvider->getRequestService()->setAttachmentHeaders('covers.zip', 'application/zip');
            readfile($zipTempFile);
            unlink($zipTempFile);
            exit();
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_ERROR);
            return RequestResult::buildJSONRequestResult([], statusCode: HttpStatusCodes::internalServerError);
        }
    }

    private static function getJPEGAsString(GdImage $image): string
    {
        ob_start();
        imagejpeg($image, null, 90);
        return ob_get_clean();
    }
}
