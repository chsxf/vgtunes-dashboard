<?php

final class PlatformAlbum implements IteratorAggregate
{
    public const array CLEAN_REGEXP = [
        null,
        '/\([^)]+\)/',
        '/-\s+EP\s*$/'
    ];

    public bool $existsInDatabase;

    public function __construct(
        public readonly string $title,
        public readonly string $platform_id,
        public readonly string $artist_name,
        public readonly ?string $cover_url
    ) {
        $this->existsInDatabase = false;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this);
    }
}
