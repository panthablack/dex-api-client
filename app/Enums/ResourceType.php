<?php

namespace App\Enums;

enum ResourceType: string
{
    case CLIENT = 'client';
    case CASE = 'case';
    case SESSION = 'session';
    case FULL_CLIENT = 'full-client';
    case FULL_CASE = 'full-case';
    case FULL_SESSION = 'full-session';
    case UNKNOWN = 'unknown';
}
