<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2020 Aleksey Andreev (liuch)
 *
 * Available at:
 * https://github.com/liuch/dmarc-srg
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 3 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of  MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * =========================
 *
 * This script creates a summary report and sends it by email.
 * The email addresses must be specified in the configuration file.
 * The script have two required parameters: `domain` and `period`.
 * The `domain` parameter must contain FQDN
 * The `period` parameter must have one of these values:
 * `lastmonth`   - to make a report for the last month;
 * `lastweek`    - to make a report for the last week;
 * `lastndays:N` - to make a report for the last N days;
 *
 * Some examples:
 *
 * $ php utils/summary_report.php domain=example.com period=lastweek
 * will send a weekly summary report by email for the domain example.com
 *
 * $ php utils/summary_report.php domain=example.com period=lastndays:10
 * will send a summary report by email for last 10 days for the domain example.com
 *
 * The best place to use it is cron.
 * Note: the current directory must be the one containing the classes directory.
 *
 * @category Utilities
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Domains\Domain;
use Liuch\DmarcSrg\Report\SummaryReport;

require 'init.php';

if (php_sapi_name() !== 'cli') {
    echo 'Forbidden' . PHP_EOL;
    exit(1);
}

$domain = null;
$period = null;
$emailto = null;
for ($i = 1; $i < count($argv); ++$i) {
    $av = explode('=', $argv[$i]);
    if (count($av) == 2) {
        switch ($av[0]) {
            case 'domain':
                $domain = $av[1];
                break;
            case 'period':
                $period = $av[1];
                break;
            case 'emailto':
              $emailto = $av[1];
              break;
        }
    }
}

if (!$domain) {
    echo 'Error: Parameter "domain" is not specified' . PHP_EOL;
    exit(1);
}
if (!$period) {
    echo 'Error: Parameter "period" is not specified' . PHP_EOL;
    exit(1);
}
if (!$emailto){
  $emailto = $mailer['default'];
} else {
  $emailto = $emailto;
}

try {
    $rep = new SummaryReport(new Domain($domain), $period);
    $body = $rep->text();
    $subject = $rep->subject();

    global $mailer;
    $headers = [
        'From'         => $mailer['from'],
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=utf-8'
    ];
    mail(
        $emailto,
        mb_encode_mimeheader($rep->subject(), 'UTF-8'),
        implode("\r\n", $rep->text()),
        $headers
    );
} catch (\Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

exit(0);
