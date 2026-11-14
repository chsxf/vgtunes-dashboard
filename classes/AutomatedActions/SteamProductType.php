<?php

declare(strict_types=1);

namespace AutomatedActions;

enum SteamProductType: string
{
    case game = 'game';
    case dlc = 'dlc';
    case other = 'other';
}
