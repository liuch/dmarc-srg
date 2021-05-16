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
 * This script returns the log records
 *
 * HTTP GET query:
 *   ? TODO
 * Other HTTP methods:
 *   it returns an error
 *
 * All the data is in json format.
 *
 * @category Web
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg;

use Exception;
use Liuch\DmarcSrg\ReportLog\ReportLog;
use Liuch\DmarcSrg\ReportLog\ReportLogItem;

require 'init.php';

if (Core::isJson()) {
    if (Core::method() == "GET") {
        try {
            Core::auth()->isAllowed();
            if (isset($_GET['id'])) {
                return Core::sendJson(ReportLogItem::byId(intval($_GET['id']))->toArray());
            }
            $pos = isset($_GET['position']) ? $_GET['position'] : 0;
            $dir = isset($_GET['direction']) ? $_GET['direction'] : 'ascent';
            $log = new ReportLog(null, null);
            $log->setOrder($dir === 'ascent' ? ReportLog::ORDER_ASCENT : ReportLog::ORDER_DESCENT);
            $res = $log->getList($pos);
            Core::sendJson($res);
            return;
        } catch (Exception $e) {
            Core::sendJson(
                [
                    'error_code' => $e->getCode(),
                    'message'    => $e->getMessage()
                ]
            );
            return;
        }
    }
    Core::sendBad();
    return;
}

Core::sendHtml();

