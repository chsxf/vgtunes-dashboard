<?php

namespace AutomatedActions;

use chsxf\MFX\DataValidator;
use Exception;

enum SteamProductsUpdaterPhase: int
{
    case games = 1;
    case other = 2;
}

class SteamProductsUpdater extends AbstractAutomatedAction
{
    private const string PROGRESS_DATA = 'progress_data';

    public function setUp(DataValidator $validator): void
    {
        $dbService = $this->coreServiceProvider->getDatabaseService();
        $dbConn = $dbService->open();

        $sql = "SELECT `type`, UNIX_TIMESTAMP(MAX(`last_update`))
                    FROM `steam_products`
                    GROUP BY `type`";
        if (($lastUpdates = $dbConn->getPairs($sql)) === false) {
            throw new Exception('Unable to fetch last update times');
        }

        $dbService->close($dbConn);

        $sessionData = [
            'phase' => SteamProductsUpdaterPhase::games,
            'progress' => [
                'game' => [
                    'last_appid' => 0,
                    'last_update' => $lastUpdates['game'] ?? 0
                ],
                'other' => [
                    'last_appid' => 0,
                    'last_update' => $lastUpdates['other'] ?? 0
                ]
            ]
        ];
        $this->storeInSession(self::PROGRESS_DATA, $sessionData);
    }

    public function proceedWithNextStep(): AutomatedActionStepData
    {
        return new AutomatedActionStepData(AutomatedActionStatus::complete);
    }

    public function shutDown(): void {}
}
