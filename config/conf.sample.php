<?php
// Set 1 to enable debug messages
$debug = 0;

// Settings for assessing the database in which reports will be saved
$database = [
    'host'         => '127.0.0.1', // You can use a domain name here
    'type'         => 'mysql',
    'name'         => 'dmarc',
    'user'         => 'dmarc_user',
    'password'     => 'password',
    /**
     * This parameter can be useful if the database is shared with other applications
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
    // Just for displaying in the web-admin and utilities. Not necessary.
    'name'            => 'Dmarc-Rua',
    // Host of the email server. You can specify a port separated by a colon.
    'host'            => 'yourdomain.net',
    // Connection encryption method. The valid values are:
    // 'none'     - without encryption (strongly not recommend).
    // 'ssl'      - SSL/TLS on a separate port, for IMAP it is usually port 993. Default value.
    // 'starttls' - STARTTLS method, usually on the standard IMAP port 143.
    'encryption'      => 'ssl',
    // Set true if you want to connect to the IMAP server without certificate validation
    'novalidate-cert' => false,
    // Mailbox user name.
    'username'        => 'dmarc-rua@yourdomain.net',
    // Mailbox password or OAuth token when the authentication method is 'oauth'.
    'password'        => 'password',
    // Authentication method. The valid values are:
    // 'plain' - authentication with username and password. Default value.
    // 'oauth' - OAuth authentication. Pass the token as the password.
    //           Only available with the imap-engine library (see the fetcher->library setting).
    'authentication'  => 'plain',
    // Mailbox name
    'mailbox'         => 'INBOX',
    // IMAP authentication methods to be excluded.
    // For example: 'auth_exclude' => [ 'GSSAPI', 'NTLM' ]
    'auth_exclude'    => []
];

/**
 * This option is NOT INTENDED for direct access to MAILBOX directories.
 * It is only required if you want to get reports files (xml, zip, gz) from a server directory.
 * In order to collect reports from several directories, you should put each
 * directory settings in an array. Processing of the directories is not recursive.
 * It is recommended to use atomic methods for adding files to these directories.
 * Attention! All successfully processed files will be deleted from the directories
 * and all others will be moved to subdirectory `failed`, that will be created
 * automatically if it does not exist. You can change this behavior under fetcher->directories.
 */
//$directories = [
//    // Just for displaying in the web-admin and utilities. Not necessary.
//    'name'     => 'Rua-Dir',
//    // The directory location
//    'location' => '/var/spool/dmarc-srg/rua'
//];

/**
 * It is only required if you want to get reports from a remote filesystem.
 * In order to collect reports from several filesystems, you should put each
 * filesystem settings in an array. Processing of remote filesystems is not recursive.
 * It uses the flysystem library to access remote file systems. Make sure this library is installed.
 */
//$remote_filesystems = [
//    // Just for displaying in the web-admin and utilities. Not necessary.
//    'name' => 'AWS-S3',
//
//    /**
//     * Type of remote filesystem. Required. Only 's3' is supported at this time.
//     * Before using the S3 filesystem, you will need to install the Flysystem S3 package
//     * via the Composer package manager or your OS package manager.
//     * For the Composer package manager run:
//     * composer require league/flysystem-aws-s3-v3
//     */
//    'type' => 's3',
//
//    // Bucket name. Required.
//    'bucket' => 'your-bucket-name',
//
//    // Path where the reports are located. Required.
//    'path' => '/',
//
//    /**
//     * They do not recommend to add AWS access keys directly to configuration files. Use credentials provider or
//     * environment variables for that. However, you can list your credentials in the following options instead.
//     */
//    //'key'    => 'YEpoT...',
//    //'secret' => 'uyASUDf...',
//    //'token'  => '...',
//
//    // The full URI of the webservice. This is only required when connecting to a custom endpoint.
//    //'endpoint' => 'http://localhost:9000',
//
//    // Region to connect to. Required.
//    // See http://docs.aws.amazon.com/general/latest/gr/rande.html for a list of available regions.
//    'region' => 'us-east-1'
//];

$admin = [
    // Set this value to null or remove this parameter to disable authentication
    // Note: The authentication always fails with an empty string password. Change it if you want to use the web UI.
    'password' => ''
];

$users = [
    // Enables the use of multiple users in the web interface. The authentication dialog will ask for a username and
    // password. Use `admin` as the username for the above password. To add new users, use Administration -> Users.
    // The default value is false.
    'user_management' => false,

    /**
     * Domain ownership verification method for users who are authorized to add domains.
     * This option has no effect on the admin. The valid values are:
     * 'none' - There is no verification.
     * 'dns'  - Verification by adding DNS TXT record like dmarcsrg-verification=...
     * The default value is 'none'.
     */
    'domain_verification' => 'none'
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
        'when_failed' => 'move_to:failed',

        /**
         * Library for connecting to a mailbox via IMAP.
         * The following values are available:
         * 'imap-engine'   - use the PHP library ImapEngine. You can install it with composer.
         * 'php-extension' - use the built-in PHP IMAP extension. Warning! This extension is DEPRECATED.
         * 'auto'          - use ImapEngine, if it is not installed then try to use the PHP IMAP extension.
         * The default value is 'auto'.
         */
        'library' => 'auto'
    ],
    'directories' => [
        // How many report files will be processed at once maximum. 0 to disable any limiting.
        'files_maximum' => 50,

        /**
         * What to do with the report file when it has been successfully processed.
         * The following actions are available: 'delete', 'move_to'. See the when_done for mailboxes
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
    'remote_filesystems' => [
        // See directories/files_maximum
        'files_maximum' => 50,

        // See directories/when_done
        'when_done'        => 'delete',

        // See directories/when_failed
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
    /**
     * The library used to send e-mails. The following values are currently supported:
     * 'internal'  - use the PHP internal functions. Default value.
     * 'phpmailer' - use the PHPMailer library. You can install it with composer.
     */
    'library' => 'internal',

    /**
     * The method used to send email. Note: The 'smtp' method requires the PHPMailer library. Make sure it is installed.
     * 'mail' - use the standard PHP mail() function. Default value.
     * 'smtp' - sent via SMTP. This method required the PHPMailer library. See below for required parameters.
     */
    'method' => 'mail',

    /**
     * Sender's e-mail address
     */
    'from'    => 'postmaster@yourdomain.net',

    /**
     * Recepient's default e-mail address
     */
    'default' => 'user@yourdomain.net',

    /*
     * For method 'smtp' the following parameters must be specified:
     */

    /**
     * SMTP host to connect to.
     */
    //'host' => 'mailhost.net',

    /**
     * TCP port to connect to.
     * Typically it is 465 for SSL/TLS, 587 for STARTTLS, or 25.
     */
    //'port' => 465,

    /**
     * Connection encryption method. The valid values are:
     * 'none'     - without encryption (strongly not recommend).
     * 'ssl'      - SSL/TLS on a separate port, for SMTP it is usually port 465. Default value.
     * 'starttls' - STARTTLS method, usually on the standard SMTP port 587.
     */
    //'encryption' => 'ssl',

    /**
     * Set true if you want to connect to the SMTP server without certificate validation
     */
    //'novalidate-cert' => false,

    /**
     * User name. Specify an empty string if authentication is not required.
     */
    //'username' => 'someusername',

    /**
     * User password. Specify an empty string if authentication is not required.
     */
    //'password'  => 'somepasword'
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
         * 'seen' - only seen messages will be removed. The default value for successfully processed mail messages.
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

/**
 * Path to a custom CSS file to add it to the HTML header in order to use custom styles.
 * The file must be a regular CSS file and its name must end with ".css".
 */
//$custom_css = 'css/custom.css';
