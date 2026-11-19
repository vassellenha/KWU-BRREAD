<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],


    // The counter on the Contact page. With a Maps Embed API key the page shows the official
    // embed; without one it falls back to the keyless Google Maps embed, so the map always shows.
    // The key is public by design (it sits in the page), so restrict it to this site's domain and
    // to the Maps Embed API in Google Cloud.
    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
        'query' => 'Telkom University Jakarta Kampus C Minangkabau, Jl. Minangkabau Barat No. 50, Pasar Manggis, Setiabudi, Jakarta Selatan',
        'place_url' => 'https://maps.google.com/maps?udm&fvr=1&pvq=Cg0vZy8xMXk5OHl5dmR4IhQKDmthbXB1cyBjIHRlbHl1EAIYA4kBObVASptQE0Y&lqi=Cg5rYW1wdXMgYyB0ZWx5dUic0_uOpb2AgAhaHhAAEAEQAhgAGAIiDmthbXB1cyBjIHRlbHl1MgJpZJIBF2VkdWNhdGlvbmFsX2luc3RpdHV0aW9u&vet=10CAAQoqAOahcKEwjQ7vvAh5GYAxUAAAAAHQAAAAAQDw..i&cs=1&um=1&ie=UTF-8&fb=1&gl=id&sa=X&ftid=0x2e69f5d99fe71b5b:0x4613509b4a40b539',
        'zoom' => 17,
    ],

    // Where "write to us" points now that the counter answers on Instagram.
    'instagram' => [
        'handle' => 'brread.official',
        'url' => 'https://www.instagram.com/brread.official/',
    ],

    // Approximate location of addresses that trigger the sign-in lockdown (ip-api.com).
    'geoip' => [
        'enabled' => (bool) env('GEOIP_ENABLED', true),
    ],

    // How long security events are kept before they are deleted.
    'security_events' => [
        'keep_days' => (int) env('SECURITY_EVENT_DAYS', 90),
    ],

];
