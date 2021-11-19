# DmarcSrg
A php parser, viewer and summary report generator for incoming DMARC reports.

### Features
* View a table of parsed reports;
* Easily identify potential DMARC related issues through colors;
* Filter report list by domain, month, reporting organization and more;
* View DKIM/SPF details for each report;
* Password protection of the web interface (can be disabled);
* Receiving and processing incoming DMARC reports from specified mailboxes;
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
There are two ways to do that: by using the web-interface or by running the follow command:

`$ php utils/database_admin.php init`

**Note:** This command must be run from the directory which contains the directories classes and config.

## Usage
In general, DmarcSrg is designed to automatically receive incoming DMARC reports, process them and send summary reports to the specified e-mail address, so a web-interface as well as a web-server is optional. Most of the work is done by periodically running php scripts, which are located in the utils directory.

### Utils
- `utils/database_admin.php` - performs some administration functions with the database.
- `utils/fetch_reports.php` - fetches DMARC reports from mailboxes and saves them to the database.
- `utils/mailbox_cleaner.php` - deletes old DMARC report email messages in mailboxes.
- `utils/reportlog_cleaner.php` - deletes old log entries.
- `utils/reports_cleaner.php` - deletes old reports from the database.
- `utils/summary_report.php` - creates a summary report and sents it by email.

You can find more detailed information about each script in the comments to it.

**Note:** These scripts must be run from the directory which contains the directories classes and config.

**Note:** Despite the fact that these scripts can only be run from the console, it is recommended to close access to the utils directory from the web server.

For example, if you want to get a summary report for the last week, you should run a command like this:

`$ cd /usr/local/share/dmarc-srg && php utils/summary_report.php domain=example.com period=lastweek`

# Web-interface
Navigate in your browser to the location of the `index.html` file. You will see the basic Report List view, allowing you to navigate through the reports that have been parsed. Using the menu go to the Admin section and create tables in the database and check the accessibility of the mailboxes if necessary.

