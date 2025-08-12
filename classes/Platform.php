<?php

enum Platform: string
{
    case appleMusic = 'apple_music';
    case bandcamp = 'bandcamp';
    case deezer = 'deezer';
    case spotify = 'spotify';
    case steamGame = 'steam_game';
    case steamSoundtrack = 'steam_soundtrack';
    case tidal = 'tidal';

    public const array PLATFORMS = [
        self::appleMusic->value => 'Apple Music',
        self::bandcamp->value => 'Bandcamp',
        self::deezer->value => 'Deezer',
        self::spotify->value => 'Spotify',
        self::steamGame->value => 'Steam (Game)',
        self::steamSoundtrack->value => 'Steam (Soundtrack)',
        self::tidal->value => 'Tidal'
    ];

    public function getLabel()
    {
        return self::PLATFORMS[$this->value];
    }
}
