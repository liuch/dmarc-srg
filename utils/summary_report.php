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

require 'init.php';

if (php_sapi_name() !== 'cli') {
    echo 'Forbidden';
    exit(1);
}

$num2percent = function (int $per, int $cent) {
    if (!$per) {
        return '0';
    }
    return sprintf('%.0f%%(%d)', $per / $cent * 100, $per);
};

$domain = null;
$period = null;
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
        }
    }
}


if (!$domain) {
    echo 'Error: Parameter "domain" is not specified';
    exit(1);
}
if (!$period) {
    echo 'Error: Parameter "period" is not specified';
    exit(1);
}

$dom = new Domain($domain);
if (!$dom->exists()) {
    echo 'Error: Domain "' . htmlspecialchars($dom->fqdn()) . '" does not exist';
    exit(2);
}

$stat = null;
$subject = '';
switch ($period) {
    case 'lastweek':
        $stat = Statistics::lastWeek($dom);
        $subject = ' weekly';
        break;
    case 'lastmonth':
        $stat = Statistics::lastMonth($dom);
        $subject = ' monthly';
        break;
    default:
        $av = explode(':', $period);
        if (count($av) == 2 && $av[0] == 'lastndays') {
            $ndays = intval($av[1]);
            if ($ndays <= 0) {
                echo 'Error: "days" parameter has an incorrect value';
                exit(1);
            }
            $stat = Statistics::lastNDays($dom, $ndays);
            $subject = sprintf(' %d day%s', $ndays, ($ndays > 1 ? 's' : ''));
        }
        break;
}
if (!$stat) {
    echo 'Error: Parameter "period" has an incorrect value';
    exit(1);
}

$subject = sprintf('DMARC%s digest for %s', $subject, $domain);

$body = [];

$body[] = '# Domain: ' . $dom->fqdn();

$range = $stat->range();
$body[] = ' Range: ' . $range[0]->format('M d') . ' - ' . $range[1]->format('M d');
$body[] = '';

$summ = $stat->summary();
$total = $summ['emails']['total'];
$aligned = $summ['emails']['dkim_spf_aligned'] +
    $summ['emails']['dkim_aligned'] +
    $summ['emails']['spf_aligned'];
$n_aligned = $total - $aligned;
$body[] = '## Summary';
$body[] = sprintf(' Total: %d', $total);
if ($total > 0) {
    $body[] = sprintf(' DKIM or SPF aligned: %s', $num2percent($aligned, $total));
    $body[] = sprintf(' Not aligned: %s', $num2percent($n_aligned, $total));
} else {
    $body[] = sprintf(' DKIM or SPF aligned: %d', $aligned);
    $body[] = sprintf(' Not aligned: %d', $n_aligned);
}
$body[] = sprintf(' Organizations: %d', $summ['organizations']);
$body[] = '';

if (count($stat->ips()) > 0) {
    $body[] = '## Sources';
    $body[] = sprintf(
        ' %-25s %13s %13s %13s',
        '',
        'Total',
        'SPF aligned',
        'DKIM aligned'
    );
    foreach ($stat->ips() as &$it) {
        $total = $it['emails'];
        $spf_a = $it['spf_aligned'];
        $dkim_a = $it['dkim_aligned'];
        $spf_str = $num2percent($spf_a, $total);
        $dkim_str = $num2percent($dkim_a, $total);
        $body[] = sprintf(
            ' %-25s %13d %13s %13s',
            $it['ip'],
            $total,
            $spf_str,
            $dkim_str
        );
    }
    unset($it);
    $body[] = '';
}

if (count($stat->organizations()) > 0) {
    $body[] = '## Organizations';
    $body[] = sprintf(' %-15s %8s %8s', '', 'emails', 'reports');
    foreach ($stat->organizations() as &$org) {
        $body[] = sprintf(
            ' %-15s %8d %8d',
            $org['name'],
            $org['emails'],
            $org['reports']
        );
    }
    unset($org);
    $body[] = '';
}

global $mailer;
$headers = [
    'From'         => $mailer['from'],
    'MIME-Version' => '1.0',
    'Content-Type' => 'text/plain; charset=utf-8'
];
mail(
    $mailer['default'],
    mb_encode_mimeheader($subject, 'UTF-8'),
    implode("\r\n", $body),
    $headers
);

exit(0);
