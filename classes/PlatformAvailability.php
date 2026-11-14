<?php

declare(strict_types=1);

enum PlatformAvailability: string
{
    case Unknown = 'unknown';
    case Available = 'available';
    case NotAvailable = 'not_available';
}
