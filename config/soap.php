<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Data Exchange System Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for the Australian Government's Department of
    | Social Services Data Exchange System SOAP client.
    |
    */

    'dss' => [
        // WSDL URL - Update with actual DSS endpoint
        'wsdl_url' => env('DSS_WSDL_URL', 'https://dex.dss.gov.au/webservice?wsdl'),

        // Authentication credentials
        'username' => env('DSS_USERNAME'),
        'password' => env('DSS_PASSWORD'),
        'organisation_id' => env('DSS_ORGANISATION_ID'),

        // SOAP client options
        'soap_options' => [
            'soap_version' => extension_loaded('soap') ? SOAP_1_2 : 2,
            'trace' => true,
            'exceptions' => true,
            'cache_wsdl' => extension_loaded('soap') ? WSDL_CACHE_NONE : 0,
            'connection_timeout' => 5,  // Max time to establish connection
            'user_agent' => 'Laravel SOAP Client v1.0',
            'stream_context' => [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ],
                'http' => [
                    'timeout' => 10,  // Max time for entire request/response
                    'follow_location' => 1,  // Follow HTTP redirects (302, 301, etc.)
                    'max_redirects' => 5  // Maximum number of redirects to follow
                ]
            ]
        ],

        // Logging configuration
        'logging' => [
            'enabled' => env('DSS_LOGGING_ENABLED', true),
            'channel' => env('DSS_LOG_CHANNEL', 'daily'),
        ],

        // Debug configuration
        'debug' => [
            'web_display_enabled' => env('DSS_DEBUG_WEB_DISPLAY', env('APP_DEBUG', false)),
            'show_requests' => env('DSS_DEBUG_SHOW_REQUESTS', env('APP_DEBUG', false)),
            'show_responses' => env('DSS_DEBUG_SHOW_RESPONSES', env('APP_DEBUG', false)),
        ],

        // Timeout settings
        'timeout' => [
            'connection' => 30,
            'request' => 60,
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Default SOAP Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for SOAP clients
    |
    */

    'defaults' => [
        'encoding' => 'UTF-8',
        'soap_version' => extension_loaded('soap') ? SOAP_1_2 : 2,
        'compression' => extension_loaded('soap') ? (SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP) : 0,
    ]
];
