<?php

namespace PlatformHelpers;

use PlatformAlbum;

trait SearchExactMatchTrait
{
    public function searchExactMatch(string $title, array $artists): ?array
    {
        $query = $title;

        foreach (PlatformAlbum::CLEAN_REGEXP as $replacementRegex) {
            if ($replacementRegex !== null) {
                $query = trim(preg_replace($replacementRegex, '', $query));
            }

            $passQueryResults = $this->search($query);
            foreach ($passQueryResults as $result) {
                $sameTitle = stripos($result->title, $query) === 0;

                $hasSomeArtists = false;
                if (!empty($artists)) {
                    foreach ($artists as $artist) {
                        if ($result->hasArtist($artist)) {
                            $hasSomeArtists = true;
                            break;
                        }
                    }
                }

                if ($sameTitle && $hasSomeArtists) {
                    return iterator_to_array($result);
                }
            }
        }

        return null;
    }
}
