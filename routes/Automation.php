<?php

use AutomatedActions\AbstractAutomatedAction;
use AutomatedActions\AutomatedActionStatus;
use AutomatedActions\BandcampDatabaseUpdater;
use AutomatedActions\DebugAutomatedAction;
use AutomatedActions\MultiArtistsUpdater;
use AutomatedActions\SteamDatabaseUpdater;
use AutomatedActions\SteamProductsUpdater;
use AutomatedActions\TidalDatabaseUpdater;
use chsxf\MFX\Attributes\RequiredRequestMethod;
use chsxf\MFX\Attributes\Route;
use chsxf\MFX\DataValidator;
use chsxf\MFX\DataValidator\Fields\WithOptions;
use chsxf\MFX\DataValidator\FieldType;
use chsxf\MFX\HttpStatusCodes;
use chsxf\MFX\RequestMethod;
use chsxf\MFX\RequestResult;
use chsxf\MFX\Routers\BaseRouteProvider;

class Automation extends BaseRouteProvider
{
    private const string ACTION_FIELD = 'action';
    private const string CURRENT_AUTOMATED_ACTION_SHA1 = 'current_automated_action_sha1';

    private static ?array $actions = null;

    private static function getActions(bool $includeDebugActions): array
    {
        if (self::$actions === null) {
            $values = [
                BandcampDatabaseUpdater::class,
                MultiArtistsUpdater::class,
                SteamDatabaseUpdater::class,
                SteamProductsUpdater::class,
                TidalDatabaseUpdater::class
            ];

            if ($includeDebugActions) {
                $values[] = DebugAutomatedAction::class;
            }

            $keys = array_map(fn($item) => sha1($item), $values);
            self::$actions = array_combine($keys, $values);
        }
        return self::$actions;
    }

    private function allowDebugActions(): bool
    {
        return $this->serviceProvider->getConfigService()->getValue('automation.allow_debug_actions', false);
    }

    private function buildActionValidator(array &$additionalFields, bool $requiresAction = true): DataValidator
    {
        $validator = new DataValidator();
        $actions = self::getActions($this->allowDebugActions());
        $defaultValue = key($actions);
        $f = $validator->createField(self::ACTION_FIELD, FieldType::SELECT, defaultValue: $defaultValue, required: $requiresAction, extras: ['class' => 'form-select']);
        if ($f instanceof WithOptions) {
            $f->addOptions($actions);
        }

        if (!empty($_REQUEST) && !$validator->validate($_REQUEST, silent: true)) {
            throw new Exception("Unable to validate request data");
        }

        $actionHash = $validator->getFieldValue(self::ACTION_FIELD, true);
        $automatedAction = $this->getAutomatedActionInstance($actionHash);
        $actionOptions = $automatedAction->getOptions();
        foreach ($actionOptions as $optionField) {
            $additionalFields[] = $optionField->getName();
            $validator->addField($optionField);
        }

        return $validator;
    }

    private function getAutomatedActionInstance(string $classSha1): AbstractAutomatedAction
    {
        $actions = self::getActions($this->allowDebugActions());

        if (!array_key_exists($classSha1, $actions)) {
            throw new Exception("Invalid automated action class SHA1 '{$classSha1}'");
        }

        $actionClassName = $actions[$classSha1];
        $rc = new ReflectionClass($actionClassName);
        if (!$rc->isSubclassOf(AbstractAutomatedAction::class)) {
            throw new Exception(sprintf("The '%s' class isn't a subclass of %s", $actionClassName, AbstractAutomatedAction::class));
        }

        return $rc->newInstance($this->serviceProvider);
    }

    #[Route, RequiredRequestMethod(RequestMethod::GET)]
    public function home(): RequestResult
    {
        $additionalFields = [];

        try {
            $actionValidator = $this->buildActionValidator($additionalFields, false);
        } catch (Exception $e) {
            trigger_error($e->getMessage());
            return RequestResult::buildStatusRequestResult(HttpStatusCodes::internalServerError);
        }

        $this->serviceProvider->getScriptService()->add('/js/automation-home.js', defer: true);
        return new RequestResult(data: ['validator' => $actionValidator, 'additional_fields' => $additionalFields]);
    }

    #[Route, RequiredRequestMethod(RequestMethod::POST)]
    public function execute(): RequestResult
    {
        try {
            $additionalFields = [];
            $validator = $this->buildActionValidator($additionalFields);
            if (!$validator->validate($_POST)) {
                return RequestResult::buildRedirectRequestResult('/Automation/home');
            }

            $automatedActionSha1 = $validator[self::ACTION_FIELD];

            $automatedAction = $this->getAutomatedActionInstance($automatedActionSha1);
            $automatedAction->setUp($validator);

            $sessService = $this->serviceProvider->getSessionService();
            $sessService[self::CURRENT_AUTOMATED_ACTION_SHA1] = $automatedActionSha1;
            return RequestResult::buildRedirectRequestResult('/Automation/process');
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_ERROR);
            return RequestResult::buildStatusRequestResult(HttpStatusCodes::internalServerError);
        }
    }

    #[Route, RequiredRequestMethod(RequestMethod::GET)]
    public function process(): RequestResult
    {
        $sessService = $this->serviceProvider->getSessionService();
        if (!isset($sessService[self::CURRENT_AUTOMATED_ACTION_SHA1])) {
            return RequestResult::buildStatusRequestResult(HttpStatusCodes::badRequest);
        }

        try {
            $currentAutomatedActionSha1 = $sessService[self::CURRENT_AUTOMATED_ACTION_SHA1];
            $automatedAction = $this->getAutomatedActionInstance($currentAutomatedActionSha1);
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_ERROR);
            return RequestResult::buildStatusRequestResult(HttpStatusCodes::internalServerError);
        }

        $this->serviceProvider->getScriptService()->add('/js/automation.js', defer: true);
        return new RequestResult(data: [
            'action_class' => get_class($automatedAction)
        ]);
    }

    #[Route, RequiredRequestMethod(RequestMethod::GET)]
    public function step(): RequestResult
    {
        $sessService = $this->serviceProvider->getSessionService();
        if (!isset($sessService[self::CURRENT_AUTOMATED_ACTION_SHA1])) {
            return RequestResult::buildStatusRequestResult(HttpStatusCodes::badRequest);
        }

        try {
            $currentAutomatedActionSha1 = $sessService[self::CURRENT_AUTOMATED_ACTION_SHA1];
            $automatedAction = $this->getAutomatedActionInstance($currentAutomatedActionSha1);
            $stepData = $automatedAction->proceedWithNextStep();
            if ($stepData->status == AutomatedActionStatus::complete) {
                $automatedAction->shutDown();
            }

            $encodedJSON = json_encode($stepData);
            return RequestResult::buildJSONRequestResult($encodedJSON, true);
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_ERROR);
            return RequestResult::buildStatusRequestResult(HttpStatusCodes::internalServerError);
        }
    }
}
