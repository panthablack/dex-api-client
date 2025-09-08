<?php

return [
    'debugging' => [
        'show_debug_information' => env('SHOW_DEBUG_INFORMATION')
    ],
    'pagination' => [
        'default_page_size' => 10
    ],
    'toast' => [
        'default_timeout' => 5000, // 5 seconds in milliseconds
        'error_timeout' => 8000,   // 8 seconds for errors
        'position' => 'top-right'  // top-right, top-left, bottom-right, bottom-left
    ],
];
