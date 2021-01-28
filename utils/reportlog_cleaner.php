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
 * This script deletes old log entries from the database.
 * The conditions for removal must be specified in the configuration file.
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
use Liuch\DmarcSrg\ReportLog\ReportLog;

require 'init.php';

if (php_sapi_name() !== 'cli') {
    echo 'Forbidden';
    exit(1);
}

if (!isset($cleaner['reportlog']['days_old'])) {
    exit(0);
}
$days = $cleaner['reportlog']['days_old'];
if (gettype($days) !== 'integer' || $days < 0) {
    exit(0);
}
$days_date = strtotime('- ' . $days . ' days');
$maximum = isset($cleaner['reportlog']['delete_maximum']) ?
    $cleaner['reportlog']['delete_maximum'] : 0;
if (gettype($maximum) !== 'integer' || $maximum < 0) {
    exit(0);
}
$leave = isset($cleaner['reportlog']['leave_minimum']) ?
    $cleaner['reportlog']['leave_minimum'] : 0;
if (gettype($leave) !== 'integer' || $leave < 0) {
    exit(0);
}

try {
    $log = new ReportLog(null, null);
    $cnt = $log->count() - $leave;
    if ($cnt > 0) {
        $log = new ReportLog(null, $days_date);
        if ($leave * $maximum !== 0) {
            if ($maximum > 0 && $cnt > $maximum) {
                $cnt = $maximum;
            }
            $log->setOrder(ReportLog::ORDER_ASCENT);
            $log->setMaxCount($cnt);
        }
        $log->delete();
    }
} catch (Exception $e) {
    echo $e->getMessage() . ' (' . $e->getCode() . ')';
}

exit(0);

