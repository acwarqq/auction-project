<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Initial administrator account
    |--------------------------------------------------------------------------
    |
    | Used by DatabaseSeeder to create (or repair) the first admin account.
    | Values come from backend/.env - never hardcode credentials here.
    |
    */

    'email' => env('ADMIN_EMAIL'),

    'password' => env('ADMIN_PASSWORD'),

];
