<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2020-2025 Aleksey Andreev (liuch)
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
 * The script have two required parameters: `domain` and `period`,
 *   and five optional: `offset`, `emailto`, `format`, `user`, `subject`.
 * The `domain` parameter must contain a domain name, a comma-separated list of domains, or `all`.
 * The `period` parameter must have one of these values:
 *   `lastmonth`   - to make a report for the last month;
 *   `lastweek`    - to make a report for the last week;
 *   `lastndays:N` - to make a report for the last N days;
 * The `offset` parameter is optional. It is intended to be used in conjunction with the `period` parameter
 *   and allows you to specify a date range offset to the past. Must be the number days, weeks or months
 *   for `lastNDays`, `lastWeek` and `lastMonth` respectively. The default value is 0.
 * The `emailto` parameter is optional. Set it if you want to use a different email address to sent the report to.
 * The `format` parameter is optional. It provides the ability to specify the email message format.
 *   Possible values are: `text`, `html`, `text+html`, `markdown`. The default value is `text`.
 * The `user` parameter is optional. It can be useful for specifying a list of assigned domains for a single user
 *   when the `domain` options is set to `all`. Only makes sense if the user_management mode is active.
 *   The default value is `admin`.
 * The `subject` parameter is optional. It allows you to specify the subject line of the summary report message
 *   instead of the default subject line. Remember to escape spaces and special characters using HTML entities!
 *
 * Some examples:
 *
 * $ php utils/summary_report.php domain=example.com period=lastweek
 * will send a summary report for the last week for the domain example.com via email
 *
 * $ php utils/summary_report.php domain=example.com period=lastweek offset=1
 * will send a summary report for the week before last week for the domain example.com via email
 *
 * $ php utils/summary_report.php domain=example.com period=lastndays:10 subject="My cool summary report"
 * will send a summary report for last 10 days for the domain example.com via email with the specified subject line
 *
 * $ php utils/summary_report.php domain=all user=frederick1 period=lastmonth emailto=frederick@example.com
 * will send a summary report for the last month for all domains assigned to user frederick1 to frederick@example.com.
 *
 * The best place to use it is cron.
 *
 * @category Utilities
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Mail\Mailer;
use Liuch\DmarcSrg\Mail\MailBody;
use Liuch\DmarcSrg\Domains\Domain;
use Liuch\DmarcSrg\Domains\DomainList;
use Liuch\DmarcSrg\Report\SummaryReport;
use Liuch\DmarcSrg\Report\OverallReport;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\RuntimeException;

require realpath(__DIR__ . '/..') . '/init.php';

if (Core::isWEB()) {
    echo 'Forbidden';
    exit(1);
}

if (!isset($argv)) {
    echo 'Cannot get the script arguments. Probably register_argc_argv is disabled.', PHP_EOL;
    exit(1);
}

$domain  = null;
$all_dom = false;
$period  = null;
$offset  = '0';
$emailto = null;
$format  = 'text';
$uname   = null;
$acount  = count($argv);
$subject = '';
if ($acount <= 1) {
    echo "Usage: {$argv[0]} domain=<domains>|all", PHP_EOL;
    echo '           period=lastmonth|lastweek|lastndays:<days>', PHP_EOL;
    echo '           [offset=<days>] [format=text|html|text+html|markdown]', PHP_EOL;
    echo '           [emailto=<email address>] [user=<username>]', PHP_EOL;
    echo '           [subject="<subject line>"]', PHP_EOL;
    exit(1);
}
for ($i = 1; $i < $acount; ++$i) {
    $av = explode('=', $argv[$i]);
    if (count($av) == 2) {
        switch ($av[0]) {
            case 'domain':
                $domain = $av[1];
                break;
            case 'period':
                $period = $av[1];
                break;
            case 'offset':
                $offset = $av[1];
                break;
            case 'emailto':
                $emailto = $av[1];
                break;
            case 'format':
                $format = $av[1];
                break;
            case 'user':
                $uname = $av[1];
                break;
            case 'subject':
                $subject = $av[1];
                break;
        }
    }
}

$core = Core::instance();
try {
    $core->setCurrentUser($uname ?? 'admin');
    if (!$domain) {
        throw new SoftException('Parameter "domain" is not specified');
    }
    if (!$period) {
        throw new SoftException('Parameter "period" is not specified');
    }
    if (filter_var($offset, FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 0 ] ]) === false) {
        throw new SoftException('Parameter "offset" must be a positive integer');
    }
    if (!in_array($format, [ 'text', 'html', 'text+html', 'markdown' ], true)) {
        throw new SoftException('Unknown email message format: ' . $format);
    }
    if (!$emailto) {
        $emailto = $core->config('mailer/default', '');
    }

    if ($domain === 'all') {
        $domains = (new DomainList($core->getCurrentUser()))->getList()['domains'];
        $all_dom = true;
    } else {
        $domains = array_map(function ($d) {
            return new Domain($d);
        }, explode(',', $domain));
    }

    $rep = new SummaryReport($period, intval($offset));
    switch ($format) {
        case 'text':
        case 'markdown':
            $text = [];
            $html = null;
            break;
        case 'html':
            $text = null;
            $html = [];
            break;
        default:
            $text = [];
            $html = [];
            break;
    }
    $dom_cnt = count($domains);
    if ($dom_cnt == 0) {
        throw new SoftException('The user has no assigned domains');
    }
    $overall = null;
    if ($dom_cnt > 1) {
        $overall = new OverallReport();
        $rep->bindSection($overall);
    }
    $add_sep = function () use (&$text, &$html) {
        if (!is_null($text)) {
            $text[] = '-----------------------------------';
            $text[] = '';
        }
        if (!is_null($html)) {
            $html[] = '<hr style="margin:2em 0;" />';
        }
    };
    $rep_cnt = 0;
    $err_cnt = 0;
    for ($i = 0; $i < $dom_cnt; ++$i) {
        $domain = $domains[$i];
        if ($domain->isAssigned($core->getCurrentUser())) {
            $rep->setDomain($domain);
            if (!$all_dom || $domain->active() || !$rep->isEmpty()) {
                if ($rep_cnt || $err_cnt) {
                    $add_sep();
                }
                if (!is_null($text)) {
                    if ($format === 'markdown') {
                        foreach ($rep->markdown() as &$row) {
                            $text[] = $row;
                        }
                    } else {
                        foreach ($rep->text() as &$row) {
                            $text[] = $row;
                        }
                    }
                    unset($row);
                }
                if (!is_null($html)) {
                    foreach ($rep->html() as &$row) {
                        $html[] = $row;
                    }
                    unset($row);
                }
                ++$rep_cnt;
            } else {
                $overall->removeRow($domain);
            }
        } else {
            if ($rep_cnt || $err_cnt) {
                $add_sep();
            }
            $nf_message = "Domain \"{$domain->fqdn()}\" does not exist";
            if ($dom_cnt === 1) {
                throw new SoftException($nf_message);
            }
            if (!is_null($text)) {
                $text[] = "# {$nf_message}";
                $text[] = '';
            }
            if (!is_null($html)) {
                $html[] = '<h2>' . htmlspecialchars($nf_message) . '</h2>';
            }
            ++$err_cnt;
        }
    }
    if (!is_null($text)) {
        if (!$rep_cnt && !$err_cnt) {
            $text[] = 'There are no active domains';
        } elseif ($overall) {
            if ($format === 'markdown') {
                $text = array_merge($overall->markdown(), [ '---', '' ], $text);
            } else {
                $text = array_merge($overall->text(), [ '-----------------------------------', '' ], $text);
            }
        }
    }
    if (!is_null($html)) {
        if (!$rep_cnt && !$err_cnt) {
            $html[] = '<p>There are no active domains</p>';
        } elseif ($overall) {
            $html = array_merge($overall->html(), [ '<hr style="margin:2em 0;" />' ], $html);
        }
    }
    $mbody = new MailBody();
    $mbody->text = $text;
    $mbody->html = $html;

    $mailer = Mailer::get($core->config('mailer', []));
    $mailer->setFrom($core->config('mailer/from', ''));
    $mailer->addAddress($emailto);
    if (!empty($subject)) {
        $mailer->setSubject($subject);
    } elseif ($dom_cnt === 1) {
        $mailer->setSubject("{$rep->subject()} for {$domain->fqdn()}");
    } elseif ($uname && $all_dom) {
        $mailer->setSubject("{$rep->subject()} for {$uname}'s domains");
    } else {
        $dom_cnt = $rep_cnt + $err_cnt;
        $mailer->setSubject("{$rep->subject()} for {$dom_cnt} domains");
    }
    $mailer->setBody($mbody);
    $mailer->send();
} catch (SoftException $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    exit(1);
} catch (RuntimeException $e) {
    echo ErrorHandler::exceptionText($e);
    exit(1);
}

exit(0);
