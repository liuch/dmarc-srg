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

use Exception;

require 'init.php';

if (Core::method() == 'POST' && Core::isJson()) {
    $jdata = Core::getJsonData();
    if ($jdata && isset($jdata['password'])) {
        $username = isset($jdata['username']) ? strval($jdata['username']) : '';
        try {
            Core::sendJson(
                Core::auth()->login($username, strval($jdata['password']))
            );
        } catch (Exception $e) {
            Core::sendJson(
                [
                    'error_code'=> $e->getCode(),
                    'message' => $e->getMessage()
                ]
            );
        }
        return;
    }
}

Core::sendBad();

