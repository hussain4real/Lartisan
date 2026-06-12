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
];
