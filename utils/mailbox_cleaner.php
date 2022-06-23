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

use Exception;
use Liuch\DmarcSrg\Mail\MailBoxes;

require 'init.php';

if (php_sapi_name() !== 'cli') {
    echo 'Forbidden';
    exit(1);
}

if (!isset($cleaner['mailboxes']['days_old'])) {
    exit(0);
}
$days = $cleaner['mailboxes']['days_old'];
if (gettype($days) !== 'integer' || $days < 0) {
    exit(0);
}
$days_date = strtotime('- ' . $days . ' days');
$maximum = isset($cleaner['mailboxes']['delete_maximum']) ?
    $cleaner['mailboxes']['delete_maximum'] : 0;
if (gettype($maximum) !== 'integer' || $maximum < 0) {
    exit(0);
}
$leave = isset($cleaner['mailboxes']['leave_minimum']) ?
    $cleaner['mailboxes']['leave_minimum'] : 0;
if (gettype($leave) !== 'integer' || $leave < 0) {
    exit(0);
}
switch ($cleaner['mailboxes']['failed'] ?? 'no') {
    case 'seen':
        $f_criteria = 'SEEN';
        break;
    case 'any':
        $f_criteria = 'ALL';
        break;
    default:
        $f_criteria = null;
        break;
}

// Get a list of the configured email boxes
$mb_list = new MailBoxes();

if ($mb_list->count() === 0) {
    // There are no configured mailboxes
    exit(0);
}

try {
    foreach ($mb_list as $mbox) {
        $cr_a = [ 'SEEN', $f_criteria ];
        for ($cr_i = 0; $cr_i < 2; ++$cr_i) {
            $criteria = $cr_a[$cr_i];
            if ($cr_i === 1) {
                $mbox = $criteria ? $mbox->childMailbox('failed') : null;
            }
            if ($mbox) {
                $s_res = $mbox->sort(SORTDATE, $criteria, false);

                $max = $maximum > 0 ? $maximum : -1;
                $lv = $leave === 0 ? count($s_res) : count($s_res) - $leave;
                $i = 0;
                while ($lv-- > 0) {
                    $m_num = $s_res[$i++];
                    $msg = $mbox->message($m_num);
                    $mo = $msg->overview();
                    if (isset($mo->date)) {
                        $md = strtotime($mo->date);
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
} catch (Exception $e) {
    echo $e->getMessage() . ' (' . $e->getCode() . ')';
}

exit(0);
