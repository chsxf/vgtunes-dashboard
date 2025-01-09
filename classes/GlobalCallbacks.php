<?php

use chsxf\MFX\RequestResult;
use chsxf\MFX\Routers\RouterData;
use chsxf\MFX\Services\ICoreServiceProvider;

final class GlobalCallbacks
{
    public static function googleChartsPreRouteCallback(ICoreServiceProvider $coreServiceProvider, RouterData $routerData): ?RequestResult
    {
        $coreServiceProvider->getScriptService()->add('https://www.gstatic.com/charts/loader.js');
        return null;
    }
}
