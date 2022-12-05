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
 * This script deletes old DMARC report email messages in mailboxes.
 * The mailbox parameters and conditions for removal must be specified
 * in the configuration file. The mailbox accessibility can be checked
 * on the administration page in the web interface.
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
use Liuch\DmarcSrg\Sources\SourceAction;
use Liuch\DmarcSrg\Exception\RuntimeException;

require 'init.php';

if (php_sapi_name() !== 'cli') {
    echo 'Forbidden';
    exit(1);
}

$core = Core::instance();

$days = $core->config('cleaner/mailboxes/days_old', -1);
if (gettype($days) !== 'integer' || $days < 0) {
    exit(0);
}
$days_date = (new DateTime())->sub(new \DateInterval("P{$days}D"));

$maximum = $core->config('cleaner/mailboxes/delete_maximum', 0);
if (gettype($maximum) !== 'integer' || $maximum < 0) {
    exit(0);
}

$leave = $core->config('cleaner/mailboxes/leave_minimum', 0);
if (gettype($leave) !== 'integer' || $leave < 0) {
    exit(0);
}

// Get a list of the configured email boxes
$mb_list = new MailBoxes();
$mb_cnt  = $mb_list->count();
if ($mb_cnt === 0) {
    // There are no configured mailboxes
    exit(0);
}

// Get the names of mailboxes where processed messages are moved to.
$dirs = [];
foreach ([ [ 'done', '', 1 ], [ 'failed', 'failed', 0 ] ] as $it) {
    $opt_nm  = $it[0];
    $def_opt = $it[1];
    $def_cri = $it[2];
    $actions = SourceAction::fromSetting($core->config("fetcher/mailboxes/when_{$opt_nm}", ''), 0, '');
    if (count($actions) === 0) {
        $dir = $def_opt;
    } else {
        $dir = null;
        foreach ($actions as $act) {
            if ($act->type === SourceAction::ACTION_MOVE) {
                $dir = $act->param;
                break;
            }
        }
        if (is_null($dir)) {
            continue;
        }
    }
    switch (strtolower($core->config("cleaner/mailboxes/{$opt_nm}", ''))) {
        case 'any':
            $cri = 2;
            break;
        case 'seen':
            $cri = 1;
            break;
        case '':
            $cri = $def_cri;
            break;
        default:
            $cri = 0;
            break;
    }
    if (empty($dir) && $cri > 1) {
        $cri = 1;
    }
    $dirs[$dir] = min(($dirs[$dir] ?? $cri), $cri);
}

try {
    for ($mb_idx = 1; $mb_idx <= $mb_cnt; ++$mb_idx) {
        foreach ($dirs as $dir_name => $i_criteria) {
            if ($i_criteria > 0) {
                $criteria = $i_criteria === 2 ? 'ALL' : 'SEEN';
                $mbox = $mb_list->mailbox($mb_idx);
                if (!empty($dir_name)) {
                    if (!($mbox = $mbox->childMailbox($dir_name))) {
                        continue;
                    }
                }
                $s_res = $mbox->sort(SORTDATE, $criteria, false);
                $max = $maximum > 0 ? $maximum : -1;
                $lv = $leave === 0 ? count($s_res) : count($s_res) - $leave;
                $i = 0;
                while ($lv-- > 0) {
                    $m_num = $s_res[$i++];
                    $msg = $mbox->message($m_num);
                    $mo = $msg->overview();
                    if (isset($mo->date)) {
                        try {
                            $md = new DateTime($mo->date);
                        } catch (\Exception $e) {
                            $md = false;
                        }
                        if ($md !== false) {
                            if ($md > $days_date) {
                                break;
                            }
                            $mbox->deleteMessage($m_num);
                            if ($max > 0) {
                                if (--$max === 0) {
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
} catch (RuntimeException $e) {
    echo ErrorHandler::exceptionText($e);
    exit(1);
}

exit(0);
