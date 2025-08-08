<?php

declare(strict_types=1);

use App\Models\Accounting\Contract\Contract;
use App\Models\Accounting\Invoice\Invoice;
use App\Models\User;
use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Search Engine
    |--------------------------------------------------------------------------
    |
    | This is a Laravel Scout configuration specifically tailored
    | towards a multi-tenancy compatible setup with meilisearch.
    |
    */

    'driver' => 'meilisearch',

    'prefix' => env(
        'SCOUT_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_') . '_scout:'
    ),

    'queue' => [
        'connection' => env('QUEUE_CONNECTION', 'redis'),
        'queue'      => 'scout',
    ],

    'after_commit' => false,

    'chunk' => [
        'searchable'   => 500,
        'unsearchable' => 500,
    ],

    'soft_delete' => true,

    'identify' => false,

    'meilisearch' => [
        'host'           => env('MEILISEARCH_HOST', 'http://localhost:7700'),
        'key'            => env('MEILISEARCH_KEY'),
        'index-settings' => [
            User::class => [
                'filterableAttributes' => ['role', '__soft_deleted'],
                'sortableAttributes'   => ['created_at', 'number'],
            ],
            Contract::class => [
                'filterableAttributes' => ['user_id', '__soft_deleted'],
                'sortableAttributes'   => ['created_at', 'number'],
            ],
            Invoice::class => [
                'filterableAttributes' => ['user_id', '__soft_deleted'],
                'sortableAttributes'   => ['created_at', 'number'],
            ],
        ],
    ],

    'tenants' => [
        'prefix' => env('SCOUT_TENANT_PREFIX', 'tsi'),
    ],

];
