<?php

return [

    /*
    |--------------------------------------------------------------------------
    | User Profile Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for user profiles and account management.
    |
    */

    'avatar' => [
        'disk' => env('AVATAR_DISK', 'public'),
        'path' => env('AVATAR_PATH', 'avatars'),
        'max_size' => env('AVATAR_MAX_SIZE', 2048), // in KB
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'image/jpg',
            'image/gif',
            'image/svg+xml',
        ],
        'allowed_extensions' => ['jpeg', 'png', 'jpg', 'gif', 'svg'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Currencies
    |--------------------------------------------------------------------------
    |
    | List of supported currencies with their symbols and formatting options.
    |
    */

    'currencies' => [
        'USD' => ['symbol' => '$', 'name' => 'US Dollar', 'decimal_places' => 2],
        'EUR' => ['symbol' => '€', 'name' => 'Euro', 'decimal_places' => 2],
        'GBP' => ['symbol' => '£', 'name' => 'British Pound', 'decimal_places' => 2],
        'JPY' => ['symbol' => '¥', 'name' => 'Japanese Yen', 'decimal_places' => 0],
        'PHP' => ['symbol' => '₱', 'name' => 'Philippine Peso', 'decimal_places' => 2],
        'SGD' => ['symbol' => 'S$', 'name' => 'Singapore Dollar', 'decimal_places' => 2],
        'MYR' => ['symbol' => 'RM', 'name' => 'Malaysian Ringgit', 'decimal_places' => 2],
        'THB' => ['symbol' => '฿', 'name' => 'Thai Baht', 'decimal_places' => 2],
        'IDR' => ['symbol' => 'Rp', 'name' => 'Indonesian Rupiah', 'decimal_places' => 0],
        'VND' => ['symbol' => '₫', 'name' => 'Vietnamese Dong', 'decimal_places' => 0],
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Languages
    |--------------------------------------------------------------------------
    |
    | List of supported languages for the application.
    |
    */

    'languages' => [
        'en' => 'English',
        'es' => 'Spanish',
        'fr' => 'French',
        'de' => 'German',
        'it' => 'Italian',
        'pt' => 'Portuguese',
        'ru' => 'Russian',
        'ja' => 'Japanese',
        'ko' => 'Korean',
        'zh' => 'Chinese',
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Timezones
    |--------------------------------------------------------------------------
    |
    | List of commonly used timezones.
    |
    */

    'timezones' => [
        'UTC' => 'UTC',
        'America/New_York' => 'Eastern Time (US)',
        'America/Chicago' => 'Central Time (US)',
        'America/Denver' => 'Mountain Time (US)',
        'America/Los_Angeles' => 'Pacific Time (US)',
        'Europe/London' => 'Greenwich Mean Time',
        'Europe/Paris' => 'Central European Time',
        'Asia/Tokyo' => 'Japan Standard Time',
        'Asia/Shanghai' => 'China Standard Time',
        'Asia/Manila' => 'Philippine Standard Time',
        'Asia/Singapore' => 'Singapore Time',
        'Asia/Bangkok' => 'Indochina Time',
        'Australia/Sydney' => 'Australian Eastern Time',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default User Preferences
    |--------------------------------------------------------------------------
    |
    | Default values for user preferences when creating new accounts.
    |
    */

    'default_preferences' => [
        'theme' => 'auto',
        'date_format' => 'DD/MM/YYYY',
        'number_format' => '1,000.00',
        'start_of_week' => 1, // Monday
        'budget_period' => 'monthly',
        'show_account_balance' => true,
        'show_category_icons' => true,
        'enable_sound' => true,
        'auto_backup' => false,
        'default_account_id' => null,
        'default_category_id' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | User Security Settings
    |--------------------------------------------------------------------------
    |
    | Security-related configuration options.
    |
    */

    'security' => [
        'session_timeout' => env('SESSION_TIMEOUT', 120), // minutes
        'max_login_attempts' => env('MAX_LOGIN_ATTEMPTS', 5),
        'password_reset_timeout' => env('PASSWORD_RESET_TIMEOUT', 60), // minutes
        'require_email_verification' => env('REQUIRE_EMAIL_VERIFICATION', false),
        'enable_two_factor' => env('ENABLE_TWO_FACTOR', false),
        'backup_codes_count' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Export Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for data export functionality.
    |
    */

    'export' => [
        'max_records' => env('EXPORT_MAX_RECORDS', 50000),
        'chunk_size' => env('EXPORT_CHUNK_SIZE', 1000),
        'allowed_formats' => ['json', 'csv', 'xlsx'],
        'include_attachments' => env('EXPORT_INCLUDE_ATTACHMENTS', false),
        'encrypt_exports' => env('ENCRYPT_EXPORTS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Preferences
    |--------------------------------------------------------------------------
    |
    | Default notification preferences for new users.
    |
    */

    'notifications' => [
        'budget_alerts' => true,
        'bill_reminders' => true,
        'goal_milestones' => true,
        'low_balance_alerts' => true,
        'transaction_notifications' => false,
        'email_notifications' => true,
        'push_notifications' => true,
        'sms_notifications' => false,
        'reminder_days_before_bill' => 3,
        'low_balance_threshold' => 100.00,
        'budget_alert_threshold' => 80.00, // percentage
    ],

    /*
    |--------------------------------------------------------------------------
    | Account Deletion Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for account deletion process.
    |
    */

    'deletion' => [
        'soft_delete' => true,
        'permanent_delete_after_days' => 30,
        'require_confirmation' => true,
        'confirmation_text' => 'DELETE',
        'export_data_before_deletion' => true,
        'notify_before_permanent_deletion' => true,
        'notification_days_before' => [7, 3, 1],
    ],

];
