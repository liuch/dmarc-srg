<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2021-2025 Aleksey Andreev (liuch)
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
 * This script returns the log items
 *
 * HTTP GET query:
 *   When the header 'Accept' is 'application/json':
 *     It returns the item specified in the GET parameter `id` if specified. The parameter must contain integer value.
 *     Otherwise it returns a list of the log items. The request may have the following GET parameters:
 *       `position`  integer The position from which the list will be returned. The default value is 0.
 *       `direction` string  The sort direction. Can be one of the following values: `ascent`, `descent'.
 *                           The default value is `ascent`. The list will be sorted by Event time.
 *     The data will be returned in json format.
 *   Otherwise:
 *     It returns the content of the template.html file.
 * Other HTTP methods:
 *   It returns an error.
 *
 * @category Web
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Users\User;
use Liuch\DmarcSrg\ReportLog\ReportLog;
use Liuch\DmarcSrg\ReportLog\ReportLogItem;
use Liuch\DmarcSrg\Settings\SettingsList;
use Liuch\DmarcSrg\Exception\RuntimeException;

require realpath(__DIR__ . '/..') . '/init.php';

if (Core::requestMethod() == "GET") {
    if (Core::isJson()) {
        try {
            Core::instance()->auth()->isAllowed(User::LEVEL_ADMIN);

            if (isset($_GET['id'])) {
                Core::sendJson(ReportLogItem::byId(intval($_GET['id']))->toArray());
                return;
            }

            $pos = $_GET['position'] ?? 0;
            $dir = $_GET['direction'] ?? '';
            if (empty($dir)) {
                $o_set = explode(',', SettingsList::getSettingByName('log-view.sort-list-by')->value());
                if ($o_set[0] === 'event_time') {
                    $dir = $o_set[1];
                }
            }
            if ($dir !== 'ascent') {
                $dir = 'descent';
            }

            $log = new ReportLog();
            if (($filter = Common::getFilter())) {
                if (isset($filter['success'])) {
                    $filter['success'] = $filter['success'] === 'true' ? true : false;
                }
                foreach ([ 'from_time', 'till_time' ] as $k) {
                    if (isset($filter[$k])) {
                        if (($d = DateTime::createFromFormat('!Y-m-d', $filter[$k]))) {
                            $filter[$k] = $d;
                        } else {
                            unset($filter[$k]);
                        }
                    }
                }
                $log->setFilter($filter);
            }
            $log->setOrder($dir === 'ascent' ? ReportLog::ORDER_ASCENT : ReportLog::ORDER_DESCENT);
            $res = $log->getList($pos);
            $res['sorted_by'] = [ 'column' => 'event_time', 'direction' => $dir ];
            Core::sendJson($res);
            return;
        } catch (RuntimeException $e) {
            Core::sendJson(ErrorHandler::exceptionResult($e));
            return;
        }
    } else {
        Core::instance()->sendHtml();
        return;
    }
}

Core::sendBad();
