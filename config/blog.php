<?php

return [

    /*
    | Email addresses (comma separated) of the accounts that may log in to the admin.
    | New comments that need approval are also sent to these addresses.
    */
    'admin_emails' => array_filter(array_map(
        fn (string $email) => strtolower(trim($email)),
        explode(',', (string) env('ADMIN_EMAILS', 'ine@inetjuhhh.nl')),
    )),

    /*
    | Spam protection for comments.
    */
    'comments' => [
        // A form sent faster than this (seconds after the page loaded) comes from a bot
        'min_seconds' => 3,

        // Per IP address: this many comments per 10 minutes
        'per_ten_minutes' => 3,

        'max_length' => 3000,

        // At most this many "new comment" mails to the admin per hour; more still show in the admin
        'mails_per_hour' => 10,

        // More links than this marks the comment as spam
        'max_links' => 1,

        // Comments containing one of these words go straight to spam (lower case)
        'blocked_words' => [
            'viagra', 'cialis', 'casino', 'crypto', 'bitcoin', 'forex', 'loan', 'porn', 'escort',
            'seo service', 'backlinks', 'payday',
        ],
    ],

    /*
    | Spam protection for the newsletter sign-up (the hidden field and the timing check are shared).
    */
    'newsletter' => [
        // Per IP address: this many sign-ups per hour, so nobody can make the site mail a list of strangers
        'per_hour' => 5,
    ],

];
