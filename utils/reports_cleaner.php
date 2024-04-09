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
 * This script deletes old reports from DB.
 * The conditions for removal must be specified in the configuration file.
 * The best place to use it is cron.
 * Note: You must leave enough reports if you want to get correct summary report.
 *
 * @category Utilities
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Report\ReportList;
use Liuch\DmarcSrg\Exception\RuntimeException;

require realpath(__DIR__ . '/..') . '/init.php';

if (php_sapi_name() !== 'cli') {
    echo 'Forbidden';
    exit(1);
}

$core = Core::instance();
$core->user('admin');

$days = $core->config('cleaner/reports/days_old', -1);
if (gettype($days) !== 'integer' || $days < 0) {
    exit(0);
}
$days_date = (new DateTime())->sub(new \DateInterval("P{$days}D"));

$maximum = $core->config('cleaner/reports/delete_maximum', 0);
if (gettype($maximum) !== 'integer' || $maximum < 0) {
    exit(0);
}

$leave = $core->config('cleaner/reports/leave_minimum', 0);
if (gettype($leave) !== 'integer' || $leave < 0) {
    exit(0);
}

try {
    $rl = new ReportList($core->user());
    $cnt = $rl->count() - $leave;
    if ($cnt > 0) {
        $rl->setFilter([ 'before_time' => $days_date ]);
        if ($leave * $maximum !== 0) {
            if ($maximum > 0 && $cnt > $maximum) {
                $cnt = $maximum;
            }
            $rl->setMaxCount($cnt);
            $rl->setOrder(ReportList::ORDER_BEGIN_TIME, ReportList::ORDER_ASCENT);
        }
        $rl->delete();
    }
} catch (RuntimeException $e) {
    echo ErrorHandler::exceptionText($e);
    exit(1);
}

exit(0);
