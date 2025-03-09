<?php

final class PlatformAlbum implements IteratorAggregate
{
    public const array CLEAN_REGEXP = [
        null,
        '/\([^)]+\)/',
        '/-\s+EP\s*$/',
        '/[^a-z0-9\s]/i'
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

    public static function cleanupAlbumTitle(string $albumTitle): string
    {
        $cleanedTitle = $albumTitle;
        foreach (self::CLEAN_REGEXP as $cleanRegex) {
            if ($cleanRegex !== null) {
                $cleanedTitle = trim(preg_replace($cleanRegex, '', $cleanedTitle));
            }
        }
        return $cleanedTitle;
    }
}
