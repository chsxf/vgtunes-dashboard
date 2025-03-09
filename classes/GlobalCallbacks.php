<?php

use chsxf\MFX\RequestResult;
use chsxf\MFX\Routers\RouterData;
use chsxf\MFX\Services\ICoreServiceProvider;

final class GlobalCallbacks
{
    public static function main(ICoreServiceProvider $iCoreServiceProvider, RouterData $routerData): ?RequestResult
    {
        $iCoreServiceProvider->getTemplateService()->getTwig()->addGlobal('platforms', Platform::PLATFORMS);
        return null;
    }

    public static function googleChartsPreRouteCallback(ICoreServiceProvider $coreServiceProvider, RouterData $routerData): ?RequestResult
    {
        $coreServiceProvider->getScriptService()->add('https://www.gstatic.com/charts/loader.js');
        return null;
    }
}
