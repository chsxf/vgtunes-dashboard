<?php

namespace AutomatedActions;

use chsxf\MFX\DataValidator\Field;
use chsxf\MFX\DataValidator\FieldType;

abstract class AbstractSequentialAutomatedAction extends AbstractAutomatedAction
{
    protected const string LIMIT_OPTION = 'limit';
    protected const string FIRST_ID_OPTION = 'first_id';

    protected const string ALBUM_IDS = 'album_ids';
    protected const string CURRENT_INDEX = 'current_index';

    public function getOptions(): array
    {
        $options = parent::getOptions();

        $firstIdOption = Field::create(self::FIRST_ID_OPTION, FieldType::POSITIVEZERO_INTEGER, defaultValue: 0, required: false);
        $firstIdOption->addExtra('class', 'form-control');
        $options[] = $firstIdOption;

        $limitOption = Field::create(self::LIMIT_OPTION, FieldType::POSITIVEZERO_INTEGER, defaultValue: 0, required: false);
        $limitOption->addExtra('class', 'form-control');
        $options[] = $limitOption;

        return $options;
    }
}
