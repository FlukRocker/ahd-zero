<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Member registration
    |--------------------------------------------------------------------------
    |
    | Controls whether new member sign-ups are accepted at /member/register.
    | The runtime value is the AND of:
    |   1. config('site.registration_enabled')   ← from this file / env
    |   2. storage/app/site_settings.json        ← admin Settings UI override
    |
    | Either turning it off in env (deploy-time) or via admin (runtime) blocks
    | new registrations. Use App\Support\SiteSettings::registrationEnabled()
    | to read the resolved value.
    |
    */

    'registration_enabled' => env('REGISTRATION_ENABLED', true),

];
