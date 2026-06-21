<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'smtp2go' => [
        'api_key' => env('SMTP2GO_API_KEY'),
        'api_endpoint' => env('SMTP2GO_API_ENDPOINT'),
    ],

    'cloudflare' => [
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
        'zone_id' => env('CLOUDFLARE_ZONE_ID'),
        'tunnel_id' => env('CLOUDFLARE_TUNNEL_ID'),
        'tunnel_credentials_file' => env('CLOUDFLARE_TUNNEL_CREDENTIALS_FILE'),
        'dashboard_hostname' => env('CLOUDFLARE_DASHBOARD_HOSTNAME'),
        'admin_hostname' => env('CLOUDFLARE_ADMIN_HOSTNAME'),
        'access_application_name' => env('CLOUDFLARE_ACCESS_APPLICATION_NAME'),
        'access_allowed_email' => env('CLOUDFLARE_ACCESS_ALLOWED_EMAIL'),
    ],

    'backup' => [
        'root' => env('BACKUP_ROOT'),
        'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
        'remote_target' => env('BACKUP_REMOTE_TARGET'),
    ],

    'github' => [
        'backup_repository' => env('GITHUB_BACKUP_REPOSITORY'),
        'backup_token' => env('GITHUB_BACKUP_TOKEN'),
    ],

];
