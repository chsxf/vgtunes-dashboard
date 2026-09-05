<?php

use chsxf\MFX\RequestResult;
use chsxf\MFX\Routers\RouterData;
use chsxf\MFX\Services\ICoreServiceProvider;
use PlatformHelpers\AbstractPlatformHelper;
use PlatformHelpers\PlatformHelperFactory;

final class GlobalCallbacks
{
    public static function main(ICoreServiceProvider $coreServiceProvider, RouterData $routerData): ?RequestResult
    {
        $tplService = $coreServiceProvider->getTemplateService();
        $twig = $tplService->getTwig();

        if (strcasecmp($routerData->route, 'DatabaseUpdater/update') != 0) {
            PlatformHelperFactory::loadOptions($coreServiceProvider);
            $twig->addGlobal('auto_search_disabled_platform_helper_count', PlatformHelperFactory::getHelperCount(function ($options) {
                $enabled = $options[AbstractPlatformHelper::PLATFORM_OPTION_AUTO_SEARCH_ENABLED] ?? true;
                return !$enabled;
            }));
            $twig->addGlobal('disabled_platform_helper_count', PlatformHelperFactory::getHelperCount(function ($options) {
                $enabled = $options[AbstractPlatformHelper::PLATFORM_OPTION_ENABLED] ?? true;
                return !$enabled;
            }));

            if (!empty($_SESSION[Album::SESS_BULK_SELECTION]) && is_array($_SESSION[Album::SESS_BULK_SELECTION])) {
                $twig->addGlobal('remaining_bulk_selection', count($_SESSION[Album::SESS_BULK_SELECTION]));
                if ($routerData->route == 'Album/show' && !empty($_SESSION[Album::SESS_ALBUM_DATA])) {
                    $twig->addGlobal('disable_bulk_selection_link', true);
                }
            }
        }

        $twig->addGlobal('platforms', Platform::PLATFORMS);

        return null;
    }

    public static function googleChartsPreRouteCallback(ICoreServiceProvider $coreServiceProvider, RouterData $routerData): ?RequestResult
    {
        $coreServiceProvider->getScriptService()->add('https://www.gstatic.com/charts/loader.js');
        return null;
    }
}
