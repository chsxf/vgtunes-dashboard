<?php

namespace PlatformHelpers;

use Platform;

interface IPlatformHelper
{
    public function getPlatform(): Platform;
    public function getLookUpURL(string $platformId): string;
    public function search(string $query, ?int $startAt = null): array;
    public function searchExactMatch(string $title, array $artists): ?array;
    public function supportsPagination(): bool;
    public function nextPageStart(): ?int;
    public function resultsPerPage(): int;
}
