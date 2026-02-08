<?php

use chsxf\MFX\DataValidator;

final class PlatformAlbum implements IteratorAggregate
{
    public const array CLEAN_REGEXP = [
        null,
        '/\([^)]+\)/',
        '/-\s+EP\s*$/',
        "/[^':a-z0-9\s]/i",
        '/\s(OST|EP)$/i',
        '/Original( .+)? Soundtrack/i',
        '/vol\.? [ixvlm0-9]/i'
    ];

    public bool $existsInDatabase;
    public int $potentialDuplicate;

    public function __construct(
        public readonly string $title,
        public readonly string $platform_id,
        public readonly array $artists,
        public readonly ?string $cover_url
    ) {
        $this->existsInDatabase = false;
        $this->potentialDuplicate = 0;
    }

    public function hasPotentialDuplicate(): bool
    {
        return $this->potentialDuplicate !== 0;
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

    public function hasArtist(string $artistName): bool
    {
        foreach ($this->artists as $artist) {
            if (stripos($artist, $artistName) === 0) {
                return true;
            }
        }
        return false;
    }

    public function applyToValidator(DataValidator $dataValidator)
    {
        $asArray = iterator_to_array($this);
        $asArray['artists'] = json_encode($asArray['artists']);
        $dataValidator->validate($asArray, true);
    }
}
