<?php

enum Platform: string
{
    case appleMusic = 'apple_music';
    case bandcamp = 'bandcamp';
    case deezer = 'deezer';
    case spotify = 'spotify';

    public const array PLATFORMS = [
        self::appleMusic->value => 'Apple Music',
        self::bandcamp->value => 'Bandcamp',
        self::deezer->value => 'Deezer',
        self::spotify->value => 'Spotify'
    ];
}
