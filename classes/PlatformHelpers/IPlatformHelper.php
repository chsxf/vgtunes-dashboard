<?php

namespace PlatformHelpers;

interface IPlatformHelper
{
    public function getPlatform(): string;
    public function getLookUpURL(string $platformId): string;
    public function search(string $query): array;
}
