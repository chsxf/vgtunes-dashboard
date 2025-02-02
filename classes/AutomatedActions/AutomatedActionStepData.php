<?php

namespace AutomatedActions;

use JsonSerializable;

class AutomatedActionStepData implements JsonSerializable
{
    public int $currentItemNumber = 0;
    public int $totalItems = 0;
    public array $logLines = [];

    public function __construct(public AutomatedActionStatus $status = AutomatedActionStatus::ok, ?string $logLine = null, AutomatedActionLogType $logType = AutomatedActionLogType::log)
    {
        if ($logLine !== null) {
            $this->addLogLine($logLine, $logType);
        }
    }

    public function addLogLine(string $logLine, AutomatedActionLogType $logType = AutomatedActionLogType::log)
    {
        $this->logLines[] = [$logLine, $logType];
    }

    public function computeNormalizedProgress(): ?float
    {
        if ($this->totalItems > 0) {
            return $this->currentItemNumber / $this->totalItems;
        }
        return null;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'total' => $this->totalItems,
            'current' => $this->currentItemNumber,
            'status' => $this->status->value,
            'logs' => array_map(fn($line) => [$line[0], $line[1]->value], $this->logLines)
        ];
    }
}
