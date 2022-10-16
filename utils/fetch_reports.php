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
 * This script fetches DMARC reports from mailboxes and server local directories and saves them to the DB.
 * The parameters of mailboxes and directories must be specified in the configuration file.
 * The mailboxes and directories accessibility can be checked on the administration page in the web interface.
 * The script has one optional parameter: `source` - the type of the source. The valid types are `email`, `directory`.
 *
 * Some examples:
 * $ utils/fetch_reports
 * will fetch reports from both the mailboxes and the local server directories.
 *
 * $ utils/fetch_reports source=email
 * will only fetch reports from the mailboxes.
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

use Liuch\DmarcSrg\Mail\MailBoxes;
use Liuch\DmarcSrg\Report\ReportFetcher;
use Liuch\DmarcSrg\Sources\MailboxSource;
use Liuch\DmarcSrg\Sources\DirectorySource;
use Liuch\DmarcSrg\Directories\DirectoryList;

require 'init.php';

if (php_sapi_name() !== 'cli') {
    echo 'Forbidden';
    exit(1);
}

$usage  = false;
$source = null;
if (isset($argv)) {
    for ($i = 1; $i < count($argv); ++$i) {
        $av = explode('=', $argv[$i]);
        if (count($av) !== 2) {
            echo 'Invalid parameter format' . PHP_EOL;
            $usage = true;
            break;
        }
        switch ($av[0]) {
            case 'source':
                $source = $av[1];
                break;
            default:
                echo 'Unknown parameter "' . $av[0] . '"' . PHP_EOL;
                $usage = true;
                break;
        }
    }
    if ($source && $source !== 'email' && $source !== 'directory') {
        echo 'Invalid source type "' . $source . '". "email" or "directory" expected.' . PHP_EOL;
        exit(1);
    }
}

if ($usage) {
    echo PHP_EOL;
    echo 'Usage: ' . basename(__FILE__) . ' [source=email|directory]' . PHP_EOL;
    exit(1);
}

$sou_list = [];
$problems = [];

const MAILBOX_LIST   = 1;
const DIRECTORY_LIST = 2;
const FETCHER        = 3;

$state = MAILBOX_LIST;
if (!$source || $source === 'email') {
    $mb_list = new MailBoxes();
    $failed = null;
    for ($mb_num = 1; $mb_num <= $mb_list->count(); ++$mb_num) {
        try {
            $sou_list[] = new MailboxSource($mb_list->mailbox($mb_num));
        } catch (\Exception $e) {
            if (!$failed) {
                $failed = [ 'state' => $state, 'messages' => [] ];
            }
            $failed['messages'][] = $e->getMessage();
        }
    }
    if ($failed) {
        $problems[] = $failed;
    }
}

$state = DIRECTORY_LIST;
if (!$source || $source === 'directory') {
    $failed = null;
    foreach ((new DirectoryList())->list() as $dir) {
        try {
            $sou_list[] = new DirectorySource($dir);
        } catch (\Exception $e) {
            if (!$failed) {
                $failed = [ 'state' => $state, 'messages' => [] ];
            }
            $failed['messages'][] = $e->getMessage();
        }
    }
    if ($failed) {
        $problems[] = $failed;
    }
}

$state = FETCHER;
try {
    foreach ($sou_list as $source) {
        $results = (new ReportFetcher($source))->fetch();
        foreach ($results as &$res) {
            $messages = [];
            if (isset($res['source_error'])) {
                $messages[] = $res['source_error'];
            }
            if (isset($res['post_processing_message'])) {
                $messages[] = $res['post_processing_message'];
            }
            if (isset($res['error_code']) && $res['error_code'] !== 0 && isset($res['message'])) {
                $messages[] = $res['message'];
            }
            if (count($messages) > 0) {
                $pr = [ 'state' => $state, 'messages' => $messages ];
                foreach ([ 'report_id', 'emailed_from', 'emailed_date'] as $it) {
                    if (isset($res[$it])) {
                        $pr[$it] = $res[$it];
                    }
                }
                $problems[] = $pr;
            }
        }
        unset($res);
    }
} catch (\Exception $e) {
    $problems[] = [ 'state' => $state, 'messages' => [ $e->getMessage() ] ];
}

if (count($problems) > 0) {
    foreach ($problems as $i => $pr) {
        if ($i > 0) {
            echo PHP_EOL;
        }
        switch ($pr['state']) {
            case MAILBOX_LIST:
                echo 'Failed to get mailbox list:';
                break;
            case DIRECTORY_LIST:
                echo 'Failed to get directory list:';
                break;
            case FETCHER:
                echo 'Failed to get incoming report:';
                break;
        }
        echo PHP_EOL;
        echo '  Error message:', PHP_EOL;
        $messages = array_map(function ($msg) {
            return "    - {$msg}";
        }, $pr['messages']);
        echo implode(PHP_EOL, $messages), PHP_EOL;
        if (isset($pr['report_id'])) {
            echo "  Report ID: {$pr['report_id']}", PHP_EOL;
        }
        if (isset($pr['emailed_from']) || isset($pr['emailed_date'])) {
            echo '  Email message metadata:', PHP_EOL;
            echo '    - From: ' . ($pr['emailed_from'] ?? '-'), PHP_EOL;
            echo '    - Date: ' . ($pr['emailed_date'] ?? '-'), PHP_EOL;
        }
    }
}

exit(0);
