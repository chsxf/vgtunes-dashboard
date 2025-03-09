<?php

namespace PlatformHelpers;

use PlatformAlbum;

trait SearchExactMatchTrait
{
    public function searchExactMatch(string $title, string $artistName): ?array
    {
        $query = $title;

        foreach (PlatformAlbum::CLEAN_REGEXP as $replacementRegex) {
            if ($replacementRegex !== null) {
                $query = trim(preg_replace($replacementRegex, '', $query));
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
