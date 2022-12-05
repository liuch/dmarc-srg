<?php
// Set 1 to enable debug messages
$debug = 0;

// Settings for assessing the database in which reports will be saved
$database = [
    'host'         => 'localhost',
    'type'         => 'mysql',
    'name'         => 'dmarc',
    'user'         => 'dmarc_user',
    'password'     => 'password',
    /**
     * This pamemeter can be usefull if the database is shared with other applications
     * to avoid conflicting table names. You do not need to specify this parameter
     * if you use a dedicated database.
     * Example value: dmarc_
     * Caution! Do not use an empty string as the table prefix value if you share the database with
     * other applications.
     * Caution! Do not change this parameter if you have already created the tables in the database
     * because the tables will not be renamed automatically.
     */
    'table_prefix' => ''
];

/**
 * It is only required if you want to get reports from a mailbox automatically.
 * In order to collect reports from several mailboxes, you should put each
 * mailbox settings in an array.
 */
$mailboxes = [
    // Just for displaying in web-admin. Not necessary.
    'name'            => 'Dmarc-Rua',
    // Host of the email server. You can specify a port separated by a colon.
    'host'            => 'yourdomain.net',
     // Connection encryption method. The valid values are:
     // 'none'     - without encryption (strongly not recommend).
     // 'ssl'      - SSL/TLS on a separate port, for IMAP it is usually port 993.
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

/**
 * It is only required if you want to get reports from a server directory.
 * In order to collect report from several directories, you should put each
 * directory settings in an array. Processing of the directories is not recursive.
 * It is recommended to use atomic methods for adding files to these directories.
 * Attention! All successfully processed files will be deleted from the directories
 * and all others will be moved to subdirectory `failed`, that will be created
 * automatically if it does not exist.
 */
//$directories = [
//    // Just for displaying in web-admin. Not necessary.
//    'name'     => 'Rua-Dir',
//    // The directory location
//    'location' => '/var/spool/dmarc-srg/rua'
//];

$admin = [
    // Set this value to null or remove this parameter to disable authentication
    // Note: The authentication always fails with an empty string password. Change it if you want to use the web ui.
    'password' => '',
];

//
$fetcher = [
    'mailboxes' => [
        // How many messages will be fetched at once maximum. 0 to disable any limiting.
        'messages_maximum' => 10,

        /**
         * What to do with the email message when a report from it has been successfully processed
         * and saved to the database. The following actions are available:
         * 'mark_seen'   - Mark the email message as seen.
         * 'delete'      - Delete email message from the mailbox.
         * 'move_to:dir' - Move the email message to child mailbox with name 'dir'.
         *                 The child mailbox will be created it doesn't exist.
         * Note: You can specify multiple actions by putting them in an array. For example:
         * 'when_done' => [ 'mark_seen', 'move_to:done' ],
         * The default value is 'mark_seen'.
         */
        'when_done' => 'mark_seen',

        /**
         * What to do with the email message when a report from it has been rejected.
         * The same actions are available as for the when_done.
         * The default value is 'move_to:failed'.
         */
        'when_failed' => 'move_to:failed'
    ],
    'directories' => [
        // How many report files will be processed at once maximum. 0 to disable any limiting.
        'files_maximum' => 50,

        /**
         * What to do with the report file when it has been successfully processed.
         * The folowing actions are available: 'delete', 'move_to'. See the when_done for mailboxes
         * for detailed description.
         * The default value is 'delete'.
         */
        'when_done'        => 'delete',

        /**
         * What to do with the report file when it has been rejected.
         * The same actions are available as for the when_done.
         * The default value is 'move_to:failed'.
         *
         */
        'when_failed'      => 'move_to:failed'
    ],
    /**
     * Domains matching this regular expression will be automatically added to the database from processed
     * reports. This option does not affect domains that have already been added to the database.
     * It is not necessary to use this option in most cases. The option can be useful if you have many domains
     * or subdomains and do not want to add them manually in the GUI. An empty string or null doesn't match any domain.
     * Note: The first domain from the first report will be automatically added anyway.
     * Some examples:
     *   '.+\\.example\\.net$'  - Matches any subdomain of the domain example.net
     *   '^mymail[0-9]+\\.net$' - Matches the domains mymail01.net, mymail02.net, mymail99999.net, etc.
     */
    'allowed_domains' => ''
];

// Settings for sending summary reports if it is necessary.
// It uses in utils/summary_report.php
$mailer = [
    'from'    => 'postmaster@yourdomain.net',
    'default' => 'user@yourdomain.net'
];

//
$cleaner = [
    // It is used in utils/mailbox_cleaner.php
    'mailboxes' => [
        // Will remove messages older than (days)
        'days_old'       => 30,

        // How many messages will be removed at once maximum.
        'delete_maximum' => 50,

        // How many messages must be leave in the mailbox minimum.
        'leave_minimum'  => 100,

        /**
         * Status of emails to be deleted for mailboxes where successfully processed emails and rejected emails
         * are located. If it is the same mailbox, then the more forgiving criteria of the two will be used.
         * The valid values are:
         * 'none' - no action with it. The default value for rejected mail messages.
         * 'seen' - only seen messages will be removed. The default value for successully processed mail messages.
         * 'any'  - all messages will be removed.
         * Note: In the mailbox, where letters with incoming DMARC reports initially come in, unseen messages
         * will not be deleted in any case.
         */
        'done'           => 'seen',
        'failed'         => 'none'
    ],

    // It is used in utils/reports_cleaner.php
    'reports' => [
        'days_old'       => 30,
        'delete_maximum' => 50,
        'leave_minimum'  => 100
    ],

    // It is used in utils/reportlog_cleaner.php
    'reportlog' => [
        'days_old'       => 30,
        'delete_maximum' => 50,
        'leave_minimum'  => 100
    ]
];
