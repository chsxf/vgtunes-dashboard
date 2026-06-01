<?php

namespace PlatformHelpers;

use chsxf\MFX\StringTools;
use Platform;
use PlatformAlbum;
use PlatformAvailability;

abstract class AbstractPlatformHelper
{
    public const string PLATFORM_ID_PLACEHOLDER = '{PLATFORM_ID}';
    public const string PLATFORM_OPTION_ENABLED = 'enabled';
    public const string PLATFORM_OPTION_AUTO_SEARCH_ENABLED = 'auto_search_enabled';

    public function __construct(protected array $options) {}

    public abstract function getPlatform(): Platform;
    public abstract function getLookUpURL(string $platformId): string;

    protected abstract function queryAPI(string $url, array $queryParams): array;

    public abstract function search(string $query, ?int $startAt = null): array;
    public abstract function searchExactMatch(string $title, array $artists): ?array;

    public abstract function getAlbumDetails(string $albumId): PlatformAlbum|false|null;

    public function canGetAlbumAvailability(): bool
    {
        return true;
    }

    public function getAlbumAvailability(string $albumId): PlatformAvailability|false
    {
        return PlatformAvailability::Unknown;
    }

    public function __get(string $name): mixed
    {
        if (empty($this->options[AbstractPlatformHelper::PLATFORM_OPTION_ENABLED])) {
            return null;
        }
        return $this->options[$name] ?? $this->options[StringTools::toSnakeCase($name)] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->options[$name]) || isset($this->options[StringTools::toSnakeCase($name)]);
    }

    public abstract function supportsPagination(): bool;
    public abstract function nextPageStart(): ?int;
    public abstract function resultsPerPage(): int;
}
