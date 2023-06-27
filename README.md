# DmarcSrg
A php parser, viewer and summary report generator for incoming DMARC reports.

## Features
* View a table of parsed reports
* Easily identify potential DMARC related issues through colors;
* Filter report list by domain, month, reporting organization and more;
* View DKIM/SPF details for each report;
* Password protection of the web interface (can be disabled);
* Receiving and processing incoming DMARC reports from specified mailboxes;
* Receiving and processing incoming DMARC reports from specified server local directories;
* Uploading and processing incoming DMARC reports by using the web interface;
* Ability to configure deletion of old reports from the database and mailboxes;
* Generation of summary reports for the last week, last month or last N days and sending them to the specified mailbox;
* Uses AJAX calls to the backend; no external Javascript libraries are needed;

## Screenshots

### Screenshot: Report List
![Report list](/screenshots/dmarc-srg-report-list-small.png?raw=true "Screenshot: Report List")

[Larger version](/screenshots/dmarc-srg-report-list.png?raw=true)

### Screenshot: Report Detail
![Report detail](/screenshots/dmarc-srg-report-detail-small.png?raw=true "Screenshot: Report Detail")

[Larger version](/screenshots/dmarc-srg-report-detail.png?raw=true)

### Screenshot: Admin Panel
![Admin Panel](/screenshots/dmarc-srg-admin-panel-small.png?raw=true "Screenshot: Admin Panel")

[Larger version](/screenshots/dmarc-srg-admin-panel.png?raw=true)

## Installation and Configuration

Ensure that all the files are in their own sub-folder.

### Requirements
* MariaDB or MySQL
* PHP 7.3 or higher
* Installed php-mysql, php-xml, php-zip, php-json, and php-imap
* A working webserver (not necessary)

### Create the database
Login as mysql root user to the MariaDB/MySQL server using the shell, run:

`# mysql -u root -p`

and type the password.

Once you have a MariaDB or MySQL prompt, create a new database, where `dmarc` is a new database name (you can specify a different database name):

`CREATE database dmarc;`

Create a new user called `dmarc_user` for the new database (you can specify a different user name):

`GRANT all on dmarc.* to dmarc_user@localhost identified by 'new_user_password';`

**Remember** to replace `new_user_password` with a more secure one!

**Note:** If there is a need to use an existing database with other tables, you can specify the table prefix in the `conf/conf.php` file.

### conf.php
Copy `conf/conf.sample.php` to `conf/conf.php` and configure it. Learn more by reading the comments in it.

### Database initialization
There are two ways to do that: by using the web interface or by running the follow command:

`$ php utils/database_admin.php init`

**Note:** This command must be run from the directory which contains the directories classes and config.

## Usage
In general, DmarcSrg is designed to automatically receive incoming DMARC reports, process them and send summary reports to the specified e-mail address, so a web interface as well as a web-server is optional. Most of the work is done by periodically running php scripts, which are located in the utils directory.

### Utils
- `utils/check_config.php` - checks your configuration.
- `utils/database_admin.php` - performs some administration functions with the database.
- `utils/fetch_reports.php` - fetches DMARC reports from mailboxes and server local directories and saves them to the database.
- `utils/mailbox_cleaner.php` - deletes old DMARC report email messages in mailboxes.
- `utils/reportlog_cleaner.php` - deletes old log entries.
- `utils/reports_cleaner.php` - deletes old reports from the database.
- `utils/summary_report.php` - creates a summary report and sends it by email.

You can find more detailed information about each script in the comments to it.

**Note:** These scripts must be run from the directory which contains the directories classes and config.

**Note:** Despite the fact that these scripts can only be run from the console, it is recommended to close access to the utils directory from the web server.

For example, if you want to get a summary report for the last week, you should run a command like this:

`$ cd /usr/local/share/dmarc-srg && php utils/summary_report.php domain=example.com period=lastweek`

# Web interface
Navigate in your browser to the location of the `index.html` file. You will see the basic Report List view, allowing you to navigate through the reports that have been parsed. Using the menu go to the Admin section and create tables in the database and check the accessibility of the mailboxes if necessary.

If Content Security Policy (CSP) is used on your web server, it is enough to add the following permissions:
- style-src 'self';
- img-src 'self';
- script-src 'self';
- connect-src 'self';
- media-src 'self';
- form-action 'self';

That is, this rather strict policy will work well with the current web interface: `Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; script-src 'self'; connect-src 'self'; media-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'`

# How the report file processing works

## General rules for processing report files
- Only files of the following formats are accepted: zip, gzip, xml.
- Reports that do not have required fields (domain, time, org, id, records, and so on) are rejected.
- Reports that have already been accepted are also rejected.
- Reports for domains that are not listed in the database as allowed for processing are rejected. The first domain is automatically added to the database from the first correct report.
- For any attempt to download a report file, an entry is added to the internal log that can be viewed in the web interface (Administration --> Logs).

## Mailboxes
An IMAP connection is sequentially established to each mailbox, and the following actions are performed:
- Obtaining a list of unread messages.
- Checking the content of each message (number of attachments, attachment size, file extension).
- Extracting a report file from the message and parsing it and adding the report data to the database.
- If the report is successfully added to the database, the message is set as SEEN.
- If the report is rejected, the message remains marked as UNSEEN and is moved to the `failed` folder of the current mailbox. If the folder does not exist, it will be created.

**Note:** The total number of processed messages depends on the limit specified in the configuration file. The limitation is valid for each mail box separately.

## Local directories of the server
Each directory specified in the configuration file is scanned for presence of files in it (not recursively). Each file in each directory is processed as follows:
- Parsing the report file and adding the report data to the database.
- If the report is successfully added to the database, the file is removed from the directory.
- If the report is rejected, the file is moved to the `failed` subdirectory of the directory in which the file is currently located. If the subdirectory does not exist, it will be created.

**Note:** The total number of processed report files depends on the limit specified in the configuration file. The limitation is valid for each directory separately.

## Uploaded report files from the web interface
Uploading report files via the web interface is pretty standard. The upload result can be seen in a popup message and in the internal log. The number of simultaneously uploaded files and their size are limited only by the settings of your server (See `upload_max_filesize` and `post_max_size` in your `php.ini` file).
