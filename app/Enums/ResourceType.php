<?php

namespace App\Enums;

enum ResourceType: string
{
    case CLIENT = 'CLIENT';
    case CASE = 'CASE';
    case SESSION = 'SESSION';
    case FULL_CLIENT = 'FULL_CLIENT';
    case FULL_CASE = 'FULL_CASE';
    case FULL_SESSION = 'FULL_SESSION';
    case UNKNOWN = 'UNKNOWN';

    public static function resolve(string $type): ResourceType
    {
        if (in_array($type, [
            self::CASE,
            self::CASE->value,
            'case',
            'cases',
            'CASE',
            'CASES',
        ])) return self::CASE;

        if (in_array($type, [
            self::CLIENT,
            self::CLIENT->value,
            'client',
            'clients',
            'CLIENT',
            'CLIENTS',
        ])) return self::CLIENT;

        if (in_array($type, [
            self::SESSION,
            self::SESSION->value,
            'session',
            'sessions',
            'SESSION',
            'SESSIONS',
        ])) return self::SESSION;

        if (in_array($type, [
            self::FULL_CASE,
            self::FULL_CASE->value,
            'full_case',
            'full-case',
            'full_cases',
            'full-cases',
            'FULL_CASE',
            'FULL-CASE',
            'FULL_CASES',
            'FULL-CASES',
        ])) return self::FULL_CASE;

        if (in_array($type, [
            self::FULL_CLIENT,
            self::FULL_CLIENT->value,
            'full_client',
            'full-client',
            'full_clients',
            'full-clients',
            'FULL_CLIENT',
            'FULL-CLIENT',
            'FULL_CLIENTS',
            'FULL-CLIENTS',
        ])) return self::FULL_CLIENT;

        if (in_array($type, [
            self::FULL_SESSION,
            self::FULL_SESSION->value,
            'full_session',
            'full-session',
            'full_sessions',
            'full-sessions',
            'FULL_SESSION',
            'FULL-SESSION',
            'FULL_SESSIONS',
            'FULL-SESSIONS',
        ])) return self::FULL_SESSION;

        // If cannot resolve, return unknown
        return self::UNKNOWN;
    }
}
