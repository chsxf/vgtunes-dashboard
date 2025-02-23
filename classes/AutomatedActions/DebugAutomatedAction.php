<?php

namespace AutomatedActions;

use AutomatedActions\AbstractAutomatedAction;
use AutomatedActions\AutomatedActionStatus;
use chsxf\MFX\DataValidator;
use AutomatedActions\AutomatedActionStepData;
use chsxf\MFX\DataValidator\Field;
use chsxf\MFX\DataValidator\FieldType;

class DebugAutomatedAction extends AbstractAutomatedAction
{
    public function getOptions(): array
    {
        $arr = parent::getOptions();

        $field = Field::create('test', FieldType::TEXT, required: false);
        $field->addExtra('class', 'form-control');
        $arr[] = $field;

        return $arr;
    }

    public function setUp(DataValidator $validator): void {}

    public function proceedWithNextStep(): AutomatedActionStepData
    {
        return new AutomatedActionStepData(AutomatedActionStatus::complete);
    }

    public function shutDown(): void {}
}
