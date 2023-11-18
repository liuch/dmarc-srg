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
 */

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\ErrorHandler;
use Liuch\DmarcSrg\Exception\RuntimeException;

require realpath(__DIR__ . '/..') . '/init.php';

if (Core::method() == 'POST' && Core::isJson()) {
    $jdata = Core::getJsonData();
    if ($jdata && isset($jdata['password'])) {
        try {
            Core::sendJson(
                Core::instance()->auth()->login(strval($jdata['username'] ?? ''), strval($jdata['password'] ?? ''))
            );
        } catch (RuntimeException $e) {
            Core::sendJson(ErrorHandler::exceptionResult($e));
        }
        return;
    }
}

Core::sendBad();
