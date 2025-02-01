<?php

namespace PlatformHelpers;

use Platform;

interface IPlatformHelper
{
    public function getPlatform(): Platform;
    public function getLookUpURL(string $platformId): string;
    public function search(string $query): array;
    public function searchExactMatch(string $title, string $artistName): ?array;
}
