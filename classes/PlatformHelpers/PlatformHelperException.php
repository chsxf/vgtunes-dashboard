<?php

namespace PlatformHelpers;

use chsxf\MFX\HttpStatusCodes;
use ErrorException;
use Throwable;

class PlatformHelperException extends ErrorException
{
    public function __construct(
        string $message = "",
        public readonly ?HttpStatusCodes $statusCode = null,
        int $code = 0,
        int $severity = 1,
        ?string $filename = null,
        ?int $line = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $severity, $filename, $line, $previous);
    }
}
