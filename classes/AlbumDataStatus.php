<?php

declare(strict_types=1);

enum AlbumDataStatus: string
{
    case unknown = 'unknown';
    case saved = 'saved';
    case new = 'new';
    case removed = 'removed';
}
