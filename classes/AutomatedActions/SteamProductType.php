<?php

namespace AutomatedActions;

enum SteamProductType: string
{
    case game = 'game';
    case dlc = 'dlc';
    case other = 'other';
}
