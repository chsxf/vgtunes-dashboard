<?php

namespace AutomatedActions;

use chsxf\MFX\DataValidator;
use chsxf\MFX\Services\ICoreServiceProvider;

abstract class AbstractAutomatedAction
{
    protected const string PROGRESS_DATA = 'progress';

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
        return [];
    }

    public function getCooldown(): int
    {
        return 500;
    }

    abstract public function setUp(DataValidator $validator): void;
    abstract public function proceedWithNextStep(): AutomatedActionStepData;
    abstract public function shutDown(): void;
}
