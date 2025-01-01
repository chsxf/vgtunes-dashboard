<?php

namespace PlatformHelpers;

trait SearchExactMatchTrait
{
    public function searchExactMatch(string $title, string $artistName): ?array
    {
        $firstPass = true;

        while (true) {
            $query = $firstPass ? $title : trim(preg_replace('/\([^)]+\)/', '', $title));
            $passQueryResults = $this->search($title);
            foreach ($passQueryResults as $result) {
                if ($result->title == $query && stripos($result->artist_name, $artistName) !== false) {
                    return iterator_to_array($result);
                }
            }

            if (!$firstPass) {
                break;
            }
            $firstPass = false;
        }

        return null;
    }
}
