<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Waitlist Hosts
    |--------------------------------------------------------------------------
    |
    | Requests to these hosts only expose the public waitlist surface.
    |
    */

    'waitlist_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('LARTISAN_WAITLIST_HOSTS', 'lartisan.app,www.lartisan.app')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Admin Host
    |--------------------------------------------------------------------------
    |
    | When set, Filament operation panels are scoped to this host and other
    | application routes on the host redirect to the admin panel.
    |
    */

    'admin_host' => trim((string) env('LARTISAN_ADMIN_HOST', '')) ?: null,
];
