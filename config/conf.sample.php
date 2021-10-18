<?php
// Set 1 to enable debug messages
$debug = 0;

// Settings for assessing the database in which reports will be saved
$database = [
    'host'     => 'localhost',
    'type'     => 'mysql',
    'name'     => 'dmarc',
    'user'     => 'dmarc_user',
    'password' => 'password'
];

// This needs only if you want to get reports from a mailbox automatically.
// In order to collect reports from several mailboxes, you should put each
// mailbox settings in an array.
$mailboxes = [
    // Just for displaying in web-admin. Not necessary.
    'name'            => 'Dmarc-Rua',
    // Host of the email server. You can specify a port separated by a colon.
    'host'            => 'yourdomain.net',
     // Connection encryption method. The valid values are:
     // 'none'     - without encryption (strongly not recommend).
     // 'ssl'      - SSl/TLS on a separate port, for IMAP it is usually port 993.
     // 'starttls' - STARTTLS method, usually on the standard IMAP port 143. Default value.
    'encryption'      => 'starttls',
    // Set true if you want to connect to the IMAP server without certificate validation
    'novalidate-cert' => false,
    // Mailbox user name.
    'username'        => 'dmarc-rua@yourdomain.net',
    // Mailbox password.
    'password'        => 'password',
    // Mailbox name
    'mailbox'         => 'INBOX'
];

$admin = [
    // Set this value to null or remove this parameter to disable authentication
    'password' => '',
];

//
$fetcher = [
    'mailboxes' => [
        // How many messages will be fetched at once maximum.
        'messages_maximum' => 10
    ]
];

// Settings for sending summary reports if it is necessary.
// It uses in utils/summary_report.php
$mailer = [
    'from'    => 'postmaster@yourdomain.net',
    'default' => 'user@yourdomain.net'
];

//
$cleaner = [
    // It uses in utils/mailbox_cleaner.php
    'mailboxes' => [
        // Will remove messages older than (days)
        'days_old'       => 30,
        // How many messages will be removed at once maximum.
        'delete_maximum' => 50,
        // How many messages must be leave in the mailbox minimum.
        'leave_minimum'  => 100
    ],
    // It uses in utils/reports_cleaner.php
    'reports' => [
        'days_old'       => 30,
        'delete_maximum' => 50,
        'leave_minimum'  => 100
    ],
    // It uses in utils/reportlog_cleaner.php
    'reportlog' => [
        'days_old'       => 30,
        'delete_maximum' => 50,
        'leave_minimum'  => 100
    ]
];
