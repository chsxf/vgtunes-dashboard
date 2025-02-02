<?php

namespace AutomatedActions;

enum AutomatedActionLogType: string
{
    case debug = 'd';
    case error = 'e';
    case log = 'l';
    case warning = 'w';
}
