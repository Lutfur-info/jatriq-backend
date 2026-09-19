<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Document Storage
    |--------------------------------------------------------------------------
    |
    | Uploaded NIDs, licences and registration papers are personal documents,
    | so they are written to a private disk and only ever handed back through
    | the authenticated download route - never a public URL.
    |
    */

    'disk' => env('VERIFICATION_DISK', 'local'),

    'directory' => env('VERIFICATION_DIRECTORY', 'verifications'),

    /*
    |--------------------------------------------------------------------------
    | Upload Constraints
    |--------------------------------------------------------------------------
    |
    | "max_size" is expressed in kilobytes. Scans may be photographed or sent
    | as a PDF; a profile photo must be an image so it can be rendered next to
    | the badge without a converter.
    |
    */

    'max_size' => (int) env('VERIFICATION_MAX_SIZE', 5120),

    'mimes' => ['jpg', 'jpeg', 'png', 'pdf'],

    /*
    |--------------------------------------------------------------------------
    | Emergency Contacts
    |--------------------------------------------------------------------------
    |
    | The number of emergency contacts one user - driver or passenger - may
    | hold. Exactly one of them is flagged primary; the service keeps that
    | invariant.
    |
    */

    'emergency_contacts' => [
        'max' => (int) env('EMERGENCY_CONTACTS_MAX', 5),
    ],

];
