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

require 'debug.php';
require 'init.php';

if (Core::isJson()) {
    if (Core::method() == 'GET') {
        try {
            Core::auth()->isAllowed();
            Core::sendJson(Core::status()->get());
        } catch (Exception $e) {
            $r = [ 'error_code' => $e->getCode(), 'message' => $e->getMessage() ];
            if ($e->getCode() == -2) {
                $r['authenticated'] = 'no';
            }
            Core::sendJson($r);
        }
        return;
    }
}

Core::sendBad();

