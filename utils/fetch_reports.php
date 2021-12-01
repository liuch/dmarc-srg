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
 * This script fetches DMARC reports from mailboxes and saves them to the DB.
 * The mailbox parameters must be specified in the configuration file.
 * The mailbox accessibility can be checked on the administration page in
 * the web interface.
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

require 'init.php';

if (php_sapi_name() !== 'cli') {
    echo 'Forbidden';
    exit(1);
}

$sou_list = [];
$messages = [];

$mb_list = new MailBoxes();
for ($mb_num = 1; $mb_num <= $mb_list->count(); ++$mb_num) {
    try {
        $sou_list[] = new MailboxSource($mb_list->mailbox($mb_num));
    } catch (\Exception $e) {
        $messages[] = $e->getMessage();
    }
}

try {
    foreach ($sou_list as $source) {
        $results = (new ReportFetcher($source))->fetch();
        foreach ($results as &$res) {
            if (isset($res['source_error'])) {
                $messages[] = $res['source_error'];
            }
            if (isset($res['post_processing_message'])) {
                $messages[] = $res['post_processing_message'];
            }
            if (isset($res['error_code']) && $res['error_code'] !== 0 && isset($res['message'])) {
                $messages[] = $res['message'];
            }
        }
        unset($res);
    }
} catch (\Exception $e) {
    $messages[] = $e->getMessage();
}

if (count($messages) > 0) {
    echo implode(PHP_EOL, $messages) . PHP_EOL;
}

exit(0);
