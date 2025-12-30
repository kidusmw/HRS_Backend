<?php

// Cloudinary disk config can be driven purely by CLOUDINARY_URL
// (cloudinary://API_KEY:API_SECRET@CLOUD_NAME). If individual vars like
// CLOUDINARY_CLOUD_NAME aren't set, derive them from the URL so the disk works.
$cloudinaryUrl = env('CLOUDINARY_URL');
$cloudinaryUrlParts = is_string($cloudinaryUrl) ? parse_url($cloudinaryUrl) : [];
$cloudinaryKeyFromUrl = is_array($cloudinaryUrlParts) ? ($cloudinaryUrlParts['user'] ?? null) : null;
$cloudinarySecretFromUrl = is_array($cloudinaryUrlParts) ? ($cloudinaryUrlParts['pass'] ?? null) : null;
$cloudinaryCloudFromUrl = is_array($cloudinaryUrlParts) ? ($cloudinaryUrlParts['host'] ?? null) : null;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Media Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | This disk is used specifically for user-uploaded media assets (logos,
    | avatars, hotel/room images, etc). Keeping it separate from the default
    | disk lets us switch media storage (e.g., Cloudinary) without impacting
    | other filesystem use-cases.
    |
    */

    'media_disk' => env('MEDIA_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        'cloudinary' => [
            'driver' => 'cloudinary',
            'key' => env('CLOUDINARY_KEY', $cloudinaryKeyFromUrl),
            'secret' => env('CLOUDINARY_SECRET', $cloudinarySecretFromUrl),
            'cloud' => env('CLOUDINARY_CLOUD_NAME', $cloudinaryCloudFromUrl),
            'url' => $cloudinaryUrl,
            'secure' => (bool) env('CLOUDINARY_SECURE', true),
            'prefix' => env('CLOUDINARY_PREFIX'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
