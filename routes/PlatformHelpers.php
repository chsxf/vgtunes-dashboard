<?php

declare(strict_types=1);

use chsxf\MFX\Attributes\RedirectURL;
use chsxf\MFX\Attributes\RequiredRequestMethod;
use chsxf\MFX\Attributes\Route;
use chsxf\MFX\DataValidator;
use chsxf\MFX\DataValidator\FieldType;
use chsxf\MFX\DataValidator\Filters\In;
use chsxf\MFX\DataValidator\Filters\InIntRange;
use chsxf\MFX\HttpStatusCodes;
use chsxf\MFX\RequestMethod;
use chsxf\MFX\RequestResult;
use chsxf\MFX\Routers\BaseRouteProvider;
use PlatformHelpers\AbstractPlatformHelper;
use PlatformHelpers\PlatformHelperFactory;

final class PlatformHelpers extends BaseRouteProvider
{
    #[Route, RequiredRequestMethod(RequestMethod::GET)]
    public function list(): RequestResult
    {
        return new RequestResult(data: [
            'options_by_helper' => PlatformHelperFactory::getOptions(),
            'enabled_platforms' => Platform::getEnabledPlatforms()
        ]);
    }

    #[Route, RequiredRequestMethod(RequestMethod::POST), RedirectURL('/PlatformHelpers/list')]
    public function setOption(): RequestResult
    {
        $validator = new DataValidator();
        $validator->createField('platform', FieldType::TEXT)
            ->addFilter(new In(array_keys(Platform::PLATFORMS)));
        $validator->createField('option', FieldType::TEXT)
            ->addFilter(new In([AbstractPlatformHelper::PLATFORM_OPTION_ENABLED]));
        $validator->createField('value', FieldType::POSITIVEZERO_INTEGER)
            ->addFilter(new InIntRange(0, 1, true));

        if (!$validator->validate($_POST)) {
            trigger_error('Invalid parameters');
            return RequestResult::buildStatusRequestResult(HttpStatusCodes::badRequest);
        }

        $dbService = $this->serviceProvider->getDatabaseService();
        $dbConn = $dbService->open();

        $sql = "INSERT INTO `platform_helper_options`
                    VALUE (?, ?, ?)
                    ON DUPLICATE KEY UPDATE `value` = ?";
        if ($dbConn->exec($sql, $validator['platform'], $validator['option'], $validator['value'], $validator['value']) === false) {
            return RequestResult::buildStatusRequestResult(HttpStatusCodes::internalServerError);
        }

        return RequestResult::buildRedirectRequestResult();
    }
}
