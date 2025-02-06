<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2025 Aleksey Andreev (liuch)
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
 * This script returns information about a host by its IP address
 *
 * HTTP GET query:
 *   when the header 'Accept' is 'application/json':
 *     It returns the host information defined by the passed parameters:
 *       `host`    string  Host IP address
 *       `fields`  string  Comma-separated list of fields to get information for.
 *                         Possible value are `main`, `stats`.
 *   otherwise:
 *     It returns an error.
 * Other HTTP methods:
 *   It returns an error.
 *
 * @category Web
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Hosts\Host;
use Liuch\DmarcSrg\Users\User;
use Liuch\DmarcSrg\Plugins\PluginManager;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\RuntimeException;

require realpath(__DIR__ . '/..') . '/init.php';

if (Core::isJson()) {
    try {
        $core = Core::instance();

        if (Core::requestMethod() == 'GET') {
            $core->auth()->isAllowed(User::LEVEL_USER);

            if (isset($_GET['host']) && isset($_GET['fields'])) {
                $host = new Host($_GET['host']);
                $fields = explode(',', trim($_GET['fields']));
                if (!count($fields) || count(array_diff(
                    $fields,
                    [ 'main.rdns', 'main.rip', 'stats.reports', 'stats.messages', 'stats.last_report' ]
                ))) {
                    throw new SoftException('Incorrect field list');
                }

                $res = [];
                if (count($fields)) {
                    $data = [ 'ip' => $host, 'fields' => $fields ];
                    PluginManager::dispatchEvent('Host', 'hostInformationStart', $data);
                    if (isset($data['fields'])) {
                        $data['result'] = $host->information($data['fields']);
                        PluginManager::dispatchEvent('Host', 'hostInformationFinish', $data);
                        $res['data'] = $data['result'] ?? [];
                        if (isset($data['dictionary'])) {
                            $res['dictionary'] = $data['dictionary'];
                        }
                    }
                }

                Core::sendJson($res);
                return;
            }

            Core::sendJson([ 'error_code' => -1, 'message' => 'Bad request' ]);
        }
    } catch (RuntimeException $e) {
        Core::sendJson(ErrorHandler::exceptionResult($e));
    }
    return;
}

Core::sendBad();
