<?php

return [
    'site_url' => 'https://deine-domain.at',
    'recipient_email' => 'feuerwehr@deine-domain.at',
    'recipient_name' => 'FF Viehdorf',
    'hcaptcha' => [
        'sitekey' => '06b08eb0-2b5c-4510-b4ea-69d4c5cc7880',
        'secret' => 'ES_70dd7ac05e0441e4a2511e1a2e633246',
    ],
    'smtp' => [
        'host' => 'smtp.hostinger.com',
        'port' => 465,
        'security' => 'ssl',
        'username' => 'dein-postfach@deine-domain.at',
        'password' => 'HIER_HOSTINGER_SMTP_PASSWORT_EINTRAGEN',
        'from_email' => 'dein-postfach@deine-domain.at',
        'from_name' => 'FF Viehdorf Website',
        'reply_to_email' => 'dein-postfach@deine-domain.at',
        'reply_to_name' => 'FF Viehdorf Website',
        'timeout' => 20,
    ],
];
