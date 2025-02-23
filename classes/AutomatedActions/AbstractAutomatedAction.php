<?php

namespace AutomatedActions;

use chsxf\MFX\DataValidator;
use chsxf\MFX\DataValidator\Field;
use chsxf\MFX\DataValidator\FieldType;
use chsxf\MFX\Services\ICoreServiceProvider;

abstract class AbstractAutomatedAction
{
    protected const string LIMIT_OPTION = 'limit';
    protected const string FIRST_ID_OPTION = 'first_id';

    public function __construct(protected readonly ICoreServiceProvider $coreServiceProvider) {}

    private function getSessionKey(string $key): string
    {
        $instanceClassHash = sha1(get_class($this));
        return "{$instanceClassHash}_{$key}";
    }

    protected final function storeInSession(string $key, mixed $value)
    {
        $sessionService = $this->coreServiceProvider->getSessionService();
        $sessionKey = $this->getSessionKey($key);
        $sessionService[$sessionKey] = $value;
    }

    protected final function getFromSession(string $key): mixed
    {
        $sessionService = $this->coreServiceProvider->getSessionService();
        $sessionKey = $this->getSessionKey($key);
        return $sessionService[$sessionKey];
    }

    protected final function removeFromSession(string $key): void
    {
        $sessionService = $this->coreServiceProvider->getSessionService();
        $sessionKey = $this->getSessionKey($key);
        unset($sessionService[$sessionKey]);
    }

    public function getOptions(): array
    {
        $limitOption = Field::create(self::LIMIT_OPTION, FieldType::POSITIVEZERO_INTEGER, defaultValue: 0, required: false);
        $limitOption->addExtra('class', 'form-control');

        $firstIdOption = Field::create(self::FIRST_ID_OPTION, FieldType::POSITIVEZERO_INTEGER, defaultValue: 0, required: false);
        $firstIdOption->addExtra('class', 'form-control');

        return [$limitOption, $firstIdOption];
    }

    abstract public function setUp(DataValidator $validator): void;
    abstract public function proceedWithNextStep(): AutomatedActionStepData;
    abstract public function shutDown(): void;
}
