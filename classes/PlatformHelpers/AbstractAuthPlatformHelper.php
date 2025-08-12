<?php

namespace PlatformHelpers;

use chsxf\MFX\Services\IAuthenticationService;
use chsxf\MFX\Services\IConfigService;
use chsxf\MFX\Services\IDatabaseService;

abstract class AbstractAuthPlatformHelper extends AbstractPlatformHelper
{
    public function __construct(protected IConfigService $configService, private IDatabaseService $databaseService, private IAuthenticationService $authService) {}

    abstract protected function fetchAccessToken(): AuthAccessTokenData;

    abstract protected function getClientId(): string;
    abstract protected function getClientSecret(): string;

    protected function getAccessToken(): string
    {
        $platformName = $this->getPlatform()->value;

        $dbConn = $this->databaseService->open();
        $wasInTransaction = $dbConn->inTransaction();
        if (!$wasInTransaction) {
            $dbConn->beginTransaction();
        }

        $user = $this->authService->getCurrentAuthenticatedUser();

        $sql = "SELECT `access_token` FROM `access_tokens` WHERE `user_id` = ? AND `platform` = ? AND `expires_at` > CURRENT_TIMESTAMP()";
        if (($accessToken = $dbConn->getValue($sql, $user->getId(), $platformName)) !== false) {
            if (!$wasInTransaction) {
                $dbConn->rollBack();
            }
            $this->databaseService->close($dbConn);
            return $accessToken;
        }

        try {
            $newAccessTokenData = $this->fetchAccessToken();
        } catch (PlatformHelperException $e) {
            if (!$wasInTransaction) {
                $dbConn->rollBack();
            }
            $this->databaseService->close($dbConn);
            throw new PlatformHelperException("Issue generating new {$platformName} access token", previous: $e);
        }

        $sqlInterval = "INTERVAL {$newAccessTokenData->expirationDelay} SECOND";
        $sql = "INSERT INTO `access_tokens` (`user_id`, `platform`, `access_token`, `expires_at`) VALUE (?, ?, ?, DATE_ADD(CURRENT_TIMESTAMP(), $sqlInterval))
                    ON DUPLICATE KEY UPDATE `access_token` = ?, `expires_at` = DATE_ADD(CURRENT_TIMESTAMP(), $sqlInterval)";
        if ($dbConn->exec($sql, $user->getId(), $platformName, $newAccessTokenData->token, $newAccessTokenData->token) === false) {
            if (!$wasInTransaction) {
                $dbConn->rollBack();
            }
            $this->databaseService->close($dbConn);
            throw new PlatformHelperException("Unable to update {$platformName} access token");
        }

        if (!$wasInTransaction) {
            $dbConn->commit();
        }
        $this->databaseService->close($dbConn);
        return $newAccessTokenData->token;
    }
}
