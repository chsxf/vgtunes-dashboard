<?php

namespace AutomatedActions;

enum AutomatedActionStatus: string
{
    case complete = 'cp';
    case failed = 'fl';
    case ok = 'ok';
}
