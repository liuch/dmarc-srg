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

use Liuch\DmarcSrg\Users\User;
use Liuch\DmarcSrg\Users\AdminUser;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\RuntimeException;

require realpath(__DIR__ . '/..') . '/init.php';

$core = Core::instance();
if (Core::isJson()) {
    try {
        $core->auth()->isAllowed(User::LEVEL_ADMIN);

        if (Core::requestMethod() == 'GET') {
            Core::sendJson($core->admin()->state());
            return;
        } elseif (Core::requestMethod() == 'POST') {
            $data = Core::getJsonData();
            if ($data) {
                $cmd = $data['cmd'];
                if (in_array($cmd, [ 'initdb', 'cleandb', 'upgradedb' ])) {
                    if ($core->auth()->isEnabled()) {
                        $pwd = isset($data['password']) ? $data['password'] : '';
                        if (!(new AdminUser($core))->verifyPassword($pwd)) {
                            throw new SoftException('Incorrect password');
                        }
                    }
                }
                if ($cmd === 'initdb') {
                    Core::sendJson($core->database()->initDb());
                    return;
                } elseif ($cmd === 'cleandb') {
                    Core::sendJson($core->database()->cleanDb());
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
                    $db = Core::instance()->database();
                    $db->getMapper('upgrader')->go($db::REQUIRED_VERSION);
                    Core::sendJson(
                        [
                            'error_code' => 0,
                            'message'    => 'Upgraded successfully'
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
} elseif (Core::requestMethod() == 'GET') {
    $core->sendHtml();
    return;
}

Core::sendBad();
