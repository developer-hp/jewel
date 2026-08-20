<?php

/*
|--------------------------------------------------------------------------
| Appearance Defaults
|--------------------------------------------------------------------------
|
| Baked-in defaults for things a user can override on the Appearance screen.
| When a setting is left blank there — "use the app default" — these values are
| what the app falls back to.
|
| Plain arrays only, no closures, so `php artisan config:cache` still works.
|
*/

return [

    /*
    | Table header colours, applied to every table in the app.
    |
    | Separate light and dark values, because a header that reads well in one mode
    | is usually wrong in the other. Set a colour to null to hand that mode back to
    | the theme's own grey instead of forcing one.
    |
    | `text` is stated explicitly rather than derived: these are the shipped
    | defaults, so they should be exactly what was intended. A colour the user picks
    | on the Appearance screen still has its text contrast worked out automatically.
    */
    'table_header' => [
        'light' => [
            'bg' => '#4254ba',
            'text' => 'white',
        ],
        'dark' => [
            'bg' => '#159488',
            'text' => 'white',
        ],
    ],

];
