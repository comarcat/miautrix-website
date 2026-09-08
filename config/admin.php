<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seeded super_admin credentials
    |--------------------------------------------------------------------------
    |
    | Required from step 12 onward (blueprint §10). RolesSeeder looks the user up by this
    | email to assign the super_admin role — it does nothing if no matching user exists yet
    | (E2-T6 can run RolesSeeder standalone, before E2-T7's DatabaseSeeder creates this user).
    |
    */

    'seed_email' => env('ADMIN_SEED_EMAIL'),
    'seed_password' => env('ADMIN_SEED_PASSWORD'),

];
