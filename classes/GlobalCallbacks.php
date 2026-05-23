<?php

use chsxf\MFX\RequestResult;
use chsxf\MFX\Routers\RouterData;
use chsxf\MFX\Services\ICoreServiceProvider;
use PlatformHelpers\PlatformHelperFactory;

final class GlobalCallbacks
{
    public static function main(ICoreServiceProvider $coreServiceProvider, RouterData $routerData): ?RequestResult
    {
        $tplService = $coreServiceProvider->getTemplateService();
        $twig = $tplService->getTwig();

        if (strcasecmp($routerData->route, 'DatabaseUpdater/update') != 0) {
            PlatformHelperFactory::loadOptions($coreServiceProvider);
            $twig->addGlobal('disbled_platform_helper_count', PlatformHelperFactory::getDisabledHelperCount());
        }

        $twig->addGlobal('platforms', Platform::PLATFORMS);
        $twig->addGlobal('enabled_platforms', Platform::getEnabledPlatforms());

        return null;
    }

    public static function googleChartsPreRouteCallback(ICoreServiceProvider $coreServiceProvider, RouterData $routerData): ?RequestResult
    {
        $coreServiceProvider->getScriptService()->add('https://www.gstatic.com/charts/loader.js');
        return null;
    }
}
