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
use Liuch\DmarcSrg\Database\Database;
use Liuch\DmarcSrg\Database\DatabaseUpgrader;
use Liuch\DmarcSrg\Exception\RuntimeException;

require 'init.php';

if (Core::isJson()) {
    try {
        $core = Core::instance();
        $core->auth()->isAllowed();
        if (Core::method() == 'GET') {
            Core::sendJson($core->admin()->state());
            return;
        } elseif (Core::method() == 'POST') {
            $data = Core::getJsonData();
            if ($data) {
                $cmd = $data['cmd'];
                if (in_array($cmd, [ 'initdb', 'droptables', 'upgradedb' ])) {
                    if ($core->auth()->isEnabled()) {
                        $pwd = isset($data['password']) ? $data['password'] : null;
                        $core->auth()->checkAdminPassword($pwd);
                    }
                }
                if ($cmd === 'initdb') {
                    Core::sendJson(Database::initDb());
                    return;
                } elseif ($cmd === 'droptables') {
                    Core::sendJson(Database::dropTables());
                    return;
                } elseif ($cmd === 'checksource') {
                    if (isset($data['id']) && isset($data['type'])) {
                        $id = $data['id'];
                        $type = $data['type'];
                        if (gettype($id) === 'integer' && gettype($type) === 'string') {
                            Core::sendJson(
                                $core->admin()->checkSource($id, $type)
                            );
                            return;
                        }
                    }
                } elseif ($cmd === 'upgradedb') {
                    DatabaseUpgrader::go();
                    Core::sendJson(
                        [
                            'error_code' => 0,
                            'message'    => 'Upgrated successfully'
                        ]
                    );
                    return;
                }
            }
        }
        Core::sendJson([ 'error_code' => -1, 'message' => 'Bad request' ]);
    } catch (RuntimeException $e) {
        Core::sendJson(ErrorHandler::exceptionResult($e));
    }
    return;
} elseif (Core::method() == 'GET') {
    Core::sendHtml();
    return;
}

Core::sendBad();
