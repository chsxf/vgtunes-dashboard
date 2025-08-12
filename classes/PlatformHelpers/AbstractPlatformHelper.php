<?php

namespace PlatformHelpers;

use Platform;
use PlatformAlbum;

abstract class AbstractPlatformHelper
{
    public const string PLATFORM_ID_PLACEHOLDER = '{PLATFORM_ID}';

    public abstract function getPlatform(): Platform;
    public abstract function getLookUpURL(string $platformId): string;

    protected abstract function queryAPI(string $url, array $queryParams): array;

    public abstract function search(string $query, ?int $startAt = null): array;
    public abstract function searchExactMatch(string $title, array $artists): ?array;

    public abstract function getAlbumDetails(string $albumId): PlatformAlbum|false|null;

    public abstract function supportsPagination(): bool;
    public abstract function nextPageStart(): ?int;
    public abstract function resultsPerPage(): int;
}
