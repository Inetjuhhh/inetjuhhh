<?php

return [

    /*
    | Email addresses (comma separated) of the accounts that may log in to the admin.
    */
    'admin_emails' => array_filter(array_map(
        fn (string $email) => strtolower(trim($email)),
        explode(',', (string) env('ADMIN_EMAILS', 'ine@inetjuhhh.nl')),
    )),

];
