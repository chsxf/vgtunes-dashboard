<?php

namespace PlatformHelpers;

trait SearchExactMatchTrait
{
    private const array CLEAN_REGEXP = [
        null,
        '/\([^)]+\)/',
        '/-\s+EP\s*$/'
    ];

    public function searchExactMatch(string $title, string $artistName): ?array
    {
        $query = $title;

        foreach (self::CLEAN_REGEXP as $replacementRegex) {
            if ($replacementRegex !== null) {
                $query = preg_replace($replacementRegex, '', $query);
            }

            $passQueryResults = $this->search($query);
            foreach ($passQueryResults as $result) {
                if ($result->title == $query && stripos($result->artist_name, $artistName) !== false) {
                    return iterator_to_array($result);
                }
            }
        }

        return null;
    }
}
