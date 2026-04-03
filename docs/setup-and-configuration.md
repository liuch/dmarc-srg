# Install and basic setup

This was written assuming a Debian like system, using Apache. The web server was already configured to run PHP 8.2.

## Requirements

 * Webserver (E.g. Apache), configured to run PHP (v8.1 up to 8.5+)
 * MySQL or MariaDB database
 * PHP 8.1+ with PDO and XML support.

## Debian Users

You can `apt install dmarc-srg`

This puts the code in **/usr/share/dmarc-srg**. You'll need to follow the steps from "Configure the webserver" onwards below.

Your configuration file is in /etc/dmarc-srg - so update the below references to ./config as appropriate.

## Manual install

It's left as an exercise to the end user to decide where exactly they wish to install this. `/var/www/html/` probably won't work for everyone. Other versions of PHP should work.

```shell
cd /var/www/html
```

### Downloading dmarc-srg

Either :
```shell
git clone git@github.com:liuch/dmarc-srg.git
cd dmarc-srg
```

OR ... download a release from : https://github.com/liuch/dmarc-srg/releases, e.g.

```shell
wget -O dmarc.tgz https://github.com/liuch/dmarc-srg/archive/refs/tags/v3.0-pre2.tar.gz
tar -zvxf dmarc.tgz
mv dmarc-srg-3.0-pre2 dmarc-srg
cd dmarc-srg
```

#### Installing libraries etc

My intention was to collect reports from an imap mailbox, so I needed to install the directorytree/imapengine library.

You can do this using the **composer** package manager for PHP.

```shell
curl -o composer https://getcomposer.org/download/latest-stable/composer.phar
php composer selfupdate
php composer install -n
```

At this point, *composer* should warn if you are missing any required extensions (like PDO or XML support). You may have to do something like :

```shell
apt install php8.2-xml php8.2-mysql
service apache2 restart
```

## Configure the webserver (Apache)

A simple configuration for a webserver is below, it just needs to have the dmarc-srg/**public** folder as the DocumentRoot.

If you're using Apache, an appropriate VirtualHost configuration in /etc/apache2/sites-available/dmarc.conf might look like the below.

Debian package users: reference **/usr/share/dmarc-srg/public** instead.

You may also wish to apply other security restrictions in this - e.g. to only allow access from specific IP addresses, or to apply a PHP open_basedir restriction.

```apacheconf
<VirtualHost *:80>
    ServerName dmarc.example.com
    DocumentRoot /var/www/html/dmarc-srg/public
    <Directory /var/www/html/dmarc-srg/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

then perhaps :

```shell
apache2ctl configtest
a2ensite dmarc
service apache2 restart
```

# Create MySQL/MariaDB database

Connect to your MySQL compatible database and run the below as the MySQL 'root' (or similar) user with privileges to create databases and a user

Change the database or username to suit your requirements.

```SQL
CREATE DATABASE dmarc;
GRANT ALL ON dmarc.* TO dmarc_user@localhost identified by 'SomeComplexPasswordHere';
FLUSH PRIVILEGES;
```

# Configuring dmarg-srg

For Debian package users, see /etc/dmarc-srg/  instead

```shell
cd /path/to/dmarc-srg/config
cp config.sample.php config.php
```

Edit the config.php file.

 * Add database connection details.
 * Configure an 'admin' password (needed for the web ui)

# Check we're ready to go

```shell
php -f utils/check_config.php
```

(Debian package users run: `php -f /usr/share/dmarc-srg/utils/check_config.php` )

which might output something like :

```txt
=== GENERAL INFORMATION ===
  * OS information: Linux 6.12.77 #1 SMP PREEMPT_DYNAMIC Mon Mar 23 19:24:03 UTC 2026 x86_64
  * PHP version:    8.2.30

=== EXTENSIONS ===
  * pdo_mysql...................... Ok
  * xmlreader...................... Ok
  * zip............................ Ok
  * json........................... Ok

....

=== MAILBOXES ===
  * Checking mailboxes config...... Ok
    Message: 1 mailbox found
  * Imap library................... Fail
    Message: Neither ImapEngine nor PHP IMAP extension is installed

=== DIRECTORIES ===
  * Checking directories config.... Ok
    Message: No directories found

=== REMOTE FILESYSTEMS ===
  * Getting configuration.......... Skipped
    Message: Configuration not found

=== REPORT MAILER ===
  * Getting configuration.......... Ok
  * Checking mailer/method......... Ok
  * Checking mailer/library........ Ok
  * Checking mailer/default........ Ok
  * Checking mailer/from........... Ok

===
There are 1 error and 2 warnings!

```

Fix the above issues as appropriate e.g. to fix the IMAP related warning you could :

 * install the 'php-imap' package `apt install php-imap`
 * or run `php composer require directorytree/imapengine`


# Initialise MySQL database

Run this :

```shell
php -f utils/database_admin.php init
```

# Browse to the web ui

Your equivalent of http://dmarc.example.com

Login with the admin password you defined in conf/conf.php.

Note: If you have only one domain, it will be added automatically and you can skip adding a domain.

Add your domain(s) by going : menu -> settings -> domains -> "New domain"

![Add domain](_img/add-domain.gif)

Alternatively, you can do it on the command line using :
```shell
php -f utils/domains_admin.php add_domain name=example.com active=1
```

# Enable DMARC reports for your domain(s) (DNS)

Your domain's **_dmarc.example.com** TXT record should look something like :

```txt
v=DMARC1; p=reject; rua=mailto:dmarc-rua@example.com
```

Where dmarc-rua@example.com is an IMAP mailbox you've already created, and configured as a mailbox within dmarc-srg in `config/conf.php`

If the domain the notification(s) are going to does NOT match the domain the DMARC record is for, you'll need to read https://dmarc.org/2015/08/receiving-dmarc-reports-outside-your-domain/


# Enable automated collection of dmarc reports from your imap folder

This will retrieve reports from the imap folder every hour.

```shell
cat <<EOF > /etc/cron.hourly/dmarc-fetch
#!/bin/sh -eu
php -f /path/to/dmarc-srg/utils/fetch_reports.php
EOF

chmod 750 /etc/cron.hourly/dmarc-fetch
```


# Enable automated summary reports

For example, add a /etc/cron.weekly job like the below to trigger a weekly report:

```shell
cat <<EOF > /etc/cron.weekly/dmarc-srg-summary
#!/bin/sh -eu
php -f /path/to/dmarc-srg/utils/summary_report.php domain=all period=lastweek
EOF

chmod 750 /etc/cron.daily/dmarc-srg-summary
```

(run: `php -f /path/to/utils/summary_report.php` for a full list of options and how to override the configuration to send a report to someone else for specific domains)

# Tips if things don't behave ...

Try editing `config/conf.php` and set `$debug = 1;` near the top.

Then inspecting traffic to the server with your web browser - e.g. in Firefox you might see something like this :

![Firefox - network inspector](_img/browser-network-inspection-error.png)

(In this specific instance, Apache hadn't been restarted since the PHP PDO + MySQL extension was installed).
