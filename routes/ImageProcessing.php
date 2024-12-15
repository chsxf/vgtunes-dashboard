<?php

use chsxf\MFX\Attributes\Route;
use chsxf\MFX\HttpStatusCodes;
use chsxf\MFX\RequestResult;
use chsxf\MFX\Routers\BaseRouteProvider;

final class ImageProcessing extends BaseRouteProvider
{
    #[Route]
    public function covers(): RequestResult
    {
        try {
            $url = trim($_REQUEST['url'] ?? '');
            if (empty($url)) {
                throw new Exception('An image URL must be provided');
            }

            $covers = CoverProcessor::getProcessedCovers($url);

            $zipTempFile = tempnam(sys_get_temp_dir(), 'cov');
            $zipArchive = new ZipArchive();
            if ($zipArchive->open($zipTempFile, ZipArchive::OVERWRITE) === false) {
                throw new Exception("Unable to open the archive");
            }

            foreach ($covers as $size => $image) {
                $zipArchive->addFromString("cover_{$size}.jpg", $image);
            }

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
}
