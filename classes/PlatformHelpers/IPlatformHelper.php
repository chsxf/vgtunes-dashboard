<?php

namespace PlatformHelpers;

use Platform;
use PlatformAlbum;

interface IPlatformHelper
{
    public const string PLATFORM_ID_PLACEHOLDER = '{PLATFORM_ID}';

    public function getPlatform(): Platform;
    public function getLookUpURL(string $platformId): string;

    public function search(string $query, ?int $startAt = null): array;
    public function searchExactMatch(string $title, array $artists): ?array;

    public function getAlbumDetails(string $albumId): PlatformAlbum|false|null;

    public function supportsPagination(): bool;
    public function nextPageStart(): ?int;
    public function resultsPerPage(): int;
}
