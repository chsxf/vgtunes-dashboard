<?php

declare(strict_types=1);

use chsxf\MFX\Attributes\AnonymousRoute;
use chsxf\MFX\Attributes\Route;
use chsxf\MFX\DataValidator;
use chsxf\MFX\DataValidator\FieldType;
use chsxf\MFX\RequestMethod;
use chsxf\MFX\RequestResult;
use chsxf\MFX\Routers\BaseRouteProvider;

class Home extends BaseRouteProvider
{
    private const USERNAME_FIELD = 'user_name';
    private const PASSWORD_FIELD = 'user_password';

    #[Route, AnonymousRoute]
    public function signIn(): RequestResult
    {
        $authService = $this->serviceProvider->getAuthenticationService();

        if ($this->serviceProvider->getAuthenticationService()->hasAuthenticatedUser()) {
            return RequestResult::buildRedirectRequestResult('/Home/show');
        }

        $extras = ['class' => 'form-control'];

        $validator = new DataValidator();
        $validator->createField(self::USERNAME_FIELD, FieldType::TEXT, extras: $extras);
        $validator->createField(self::PASSWORD_FIELD, FieldType::PASSWORD, extras: $extras);

        if ($this->serviceProvider->getRequestService()->getRequestMethod() == RequestMethod::POST && $validator->validate($_POST)) {
            try {
                if (!$authService->validateWithFields([
                    [
                        'name' => self::USERNAME_FIELD,
                        'value' => $validator[self::USERNAME_FIELD]
                    ],
                    [
                        'name' => self::PASSWORD_FIELD,
                        'value' => $validator[self::PASSWORD_FIELD],
                        'function' => 'UNHEX(SHA2(?, 256))'
                    ]
                ])) {
                    throw new Exception("Unable to sign in user - Invalid login information");
                }

                return RequestResult::buildRedirectRequestResult('/Home/show');
            } catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_ERROR);
            }
        }

        return new RequestResult(null, ['validator' => $validator]);
    }

    #[Route]
    public function signOut(): RequestResult
    {
        $this->serviceProvider->getAuthenticationService()->invalidate();
        return RequestResult::buildRedirectRequestResult('/Home/signIn');
    }

    #[Route]
    public function show(): RequestResult
    {
        $dbService = $this->serviceProvider->getDatabaseService();
        $dbConn = $dbService->open();

        $albums = Albums::search($this->serviceProvider, $dbConn, 0, 10, orderClause: 'ORDER BY `created_at` DESC');

        $sql = "SELECT MAX(`last_update`) FROM `steam_products`";
        $lastSteamProductUpdate = $dbConn->getValue($sql);
        if (empty($lastSteamProductUpdate)) {
            $lastSteamProductUpdate = date('Y-m-d H:i:s');
        }

        return new RequestResult(null, [
            'albums' => $albums,
            'last_steam_update' => $lastSteamProductUpdate,
            'frontend_base_url' => $this->serviceProvider->getConfigService()->getValue('frontend.base_url')
        ]);
    }
}
