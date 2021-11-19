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

use Exception;
use Liuch\DmarcSrg\Mail\MailBoxes;
use Liuch\DmarcSrg\Report\Report;
use Liuch\DmarcSrg\ReportFile\ReportFile;
use Liuch\DmarcSrg\ReportLog\ReportLogItem;

require 'init.php';

if (php_sapi_name() !== 'cli') {
    echo 'Forbidden';
    exit(1);
}

/**
 * Marks an email message as seen.
 *
 * @param MailMessage $msg Email message to be flagged.
 *
 * @return boolean
 */
function markMessageSeen($msg)
{
    try {
        $msg->setSeen();
    } catch (Exception $e) {
        ReportLogItem::failed(
            ReportLogItem::SOURCE_EMAIL,
            null,
            null,
            $e->getMessage()
        )->save();
        return false;
    }
    return true;
}

/**
 * Moves an email message to 'failed' folder.
 *
 * It moves the specified message to 'failed' folder.
 * If such a folder does not exist, it creates it.
 *
 * @param MailBox $mbox   MailBox
 * @param int     $number The message number in IMAP
 *
 * @return boolean
 */
function moveMessage($mbox, $number)
{
    try {
        $mbox->ensureMailbox('failed');
        $mbox->moveMessage($number, 'failed');
    } catch (Exception $e) {
        ReportLogItem::failed(
            ReportLogItem::SOURCE_EMAIL,
            null,
            null,
            $e->getMessage()
        )->save();
        return false;
    }
    return true;
}

/**
 * Checks an email message.
 *
 * It checks the specified message (attachment, its size and extension).
 * If the check fails, saves the details to the log.
 *
 * @param MailMessage $msg Email message to check.
 *
 * @return boolean
 */
function checkMessage($msg)
{
    try {
        if (!$msg->isCorrect()) {
            throw new Exception('Incorrect message', -1);
        }
    } catch (Exception $e) {
        ReportLogItem::failed(
            ReportLogItem::SOURCE_EMAIL,
            null,
            null,
            $e->getMessage()
        )->save();
        return false;
    }
    return true;
}

/**
 * Extracts a DMARC report from the attachment of the specified email message.
 *
 * It extracts a DMARC report from the attachment of the specified email message
 * and save it to DB. Then an entry is added to the log.
 *
 * @param MailMessage $msg Email message to extract a report from.
 *
 * @return boolean
 */
function extractReport($msg)
{
    $rf = null;
    $rep = null;
    $fname = null;
    try {
        $att = $msg->attachment();
        $fname = $att->filename();
        $rf = ReportFile::fromStream($att->datastream(), $fname);
        $rep = Report::fromXmlFile($rf->datastream());
        $res = $rep->save($fname);
        if (isset($res['error_code']) && $res['error_code'] !== 0) {
            throw new Exception($res['message'], $res['error_code']);
        }
        ReportLogItem::success(
            ReportLogItem::SOURCE_EMAIL,
            $rep,
            $fname,
            null
        )->save();
    } catch (Exception $e) {
        ReportLogItem::failed(
            ReportLogItem::SOURCE_EMAIL,
            $rep,
            $fname,
            $e->getMessage()
        )->save();
        return false;
    } finally {
        unset($rep);
        unset($rf);
    }
    return true;
}

$maximum = isset($fetcher['mailboxes']['messages_maximum']) ?
    $fetcher['mailboxes']['messages_maximum'] : 0;
if (gettype($maximum) !== 'integer' || $maximum < 0) {
    exit(0);
}

// Get a list of the configured email boxes
$mb_list = new MailBoxes();

if ($mb_list->count() === 0) {
    // There are no configured mailboxes
    exit(0);
}

try {
    for ($mb_idx = 1; $mb_idx <= $mb_list->count(); ++$mb_idx) {
        $mbox = $mb_list->mailbox($mb_idx);
        $s_res = $mbox->sort(SORTDATE, 'UNSEEN', false);

        $max = $maximum;
        foreach ($s_res as $m_num) {
            $msg = $mbox->message($m_num);
            $succ = false;
            if (checkMessage($msg)) {
                $succ = extractReport($msg);
            }
            if (!markMessageSeen($msg)) {
                $succ = false;
            }
            if (!$succ) {
                moveMessage($mbox, $m_num);
            }
            if ($max > 0) {
                if (--$max === 0) {
                    break;
                }
            }
        }
    }
} catch (Exception $e) {
    echo $e->getMessage() . ' (' . $e->getCode() . ')';
}

exit(0);
