<?php

namespace PlatformHelpers;

trait DistanceResultSorterTrait
{
    private static function splitWords(string $fullText): array
    {
        $sanitizedTitle = preg_replace('/[^a-z0-9 ]/', '', strtolower($fullText));
        return array_unique(array_filter(preg_split('/\W/', $sanitizedTitle)));
    }

    private static function containsExactWords(string $fullText, array $words): bool
    {
        $textWords = array_unique(self::splitWords($fullText));
        $intersection = array_intersect($textWords, $words);
        return count($intersection) == count($words);
    }

    private static function computeDistance(string $fullText, array $queryWords, int $queryLength)
    {
        if (self::containsExactWords($fullText, $queryWords)) {
            return strlen($fullText) - $queryLength;
        }
        return PHP_INT_MAX;
    }

    private static function sortByDistance(array &$dataToSort)
    {
        usort($dataToSort, function ($itemA, $itemB) {
            $comp = $itemA[1] <=> $itemB[1];
            if ($comp === 0) {
                $comp = $itemA[0]->title <=> $itemB[0]->title;
            }
            return $comp;
        });
    }
}
