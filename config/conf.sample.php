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
    'name'            => 'Dmarc-Rua', // Just for displaying in web-admin. Not necessary.
    'host'            => 'yourdomain.net', // Host of the email server.
    'novalidate-cert' => false, // Set true if you want to connect to the IMAP server without certificate validation
    'username'        => 'dmarc-rua@yourdomain.net', // Mailbox user name.
    'password'        => 'password', // Mailbox password.
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
