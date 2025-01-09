<?php

namespace Analytics;

use chsxf\MFX\DataValidator;
use chsxf\MFX\DataValidator\Fields\WithOptions;
use chsxf\MFX\DataValidator\FieldType;

enum TimeFrame: int
{
    public const string TIMEFRAME_FIELD = 'days';

    case realtime = 1;
    case lastDays7 = 7;
    case lastDays30 = 30;
    case lastDays90 = 90;
    case lastYear = 365;

    public const array LABELS = [
        self::realtime->value => 'Real Time',
        self::lastDays7->value => 'Last 7 Days',
        self::lastDays30->value => 'Last 30 Days',
        self::lastDays90->value => 'Last 90 Days',
        self::lastYear->value => 'Last Year'
    ];

    public static function buildAnalyticsTimeFrameSelectorValidator(array $excludedValues = array()): DataValidator
    {
        $options = self::LABELS;
        $defaultValue = self::lastDays7->value;
        if (!empty($excludedValues)) {
            foreach ($excludedValues as $excludedValue) {
                unset($options[$excludedValue->value]);
            }
            if (!array_key_exists(self::lastDays7->value, $options)) {
                $defaultValue = array_key_first($options);
            }
        }

        $validator = new DataValidator();
        $f = $validator->createField(self::TIMEFRAME_FIELD, FieldType::SELECT, $defaultValue, required: false, extras: ['class' => 'form-select']);
        if ($f instanceof WithOptions) {
            $f->addOptions($options, true);
        }
        return $validator;
    }
}
