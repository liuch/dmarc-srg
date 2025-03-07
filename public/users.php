<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2023-2025 Aleksey Andreev (liuch)
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
 * This script returns the users information
 *
 * HTTP GET query:
 *   when the header 'Accept' is 'application/json':
 *     It returns a list of the users or data for the user specified in the parameter user.
 *   otherwise:
 *     it returns the content of the index.html file
 * HTTP POST query:
 *   Inserts or updates data for specified user. The data must be in json format with the following fields:
 *     `name`        string  User name.
 *     `action`      string  Must be one of the following values: `add`, `update`, `delete`, `set_password`
 *                           If the value is `delete`, all the fields below will be ignored.
 *     `level`       integer One of the User::LEVEL_* values
 *     `enabled`     boolean Set `false` to temporarily deactivate the user
 *     `domains`     array   Array of domain names to assign. If undefined, no changes are required.
 *   Example:
 *     { "name": "user194", "action": "update", "enabled": true, "domains": [ "domain1.org", "domain2.org" ] }
 * Other HTTP methods:
 *   It returns an error
 *
 * All the data is in json format.
 *
 * @category Web
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Users\User;
use Liuch\DmarcSrg\Users\DbUser;
use Liuch\DmarcSrg\Users\UserList;
use Liuch\DmarcSrg\Domains\DomainList;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\RuntimeException;

require realpath(__DIR__ . '/..') . '/init.php';

if (Core::isJson()) {
    try {
        $core = Core::instance();

        if (Core::requestMethod() == 'GET') {
            if (!isset($_GET['user'])) {
                // Retrieving a list of all users
                $core->auth()->isAllowed(User::LEVEL_ADMIN);

                $list = array_map(function ($user) {
                    $ua = $user->toArray();
                    $ua['level'] = User::levelToString($ua['level']);
                    return $ua;
                }, (new UserList())->getList()['users']);

                Core::sendJson([
                    'users' => $list,
                    'more'  => false
                ]);
                return;
            }

            $uname = $_GET['user'];
            if (empty($uname)) {
                // New user
                $core->auth()->isAllowed(User::LEVEL_ADMIN);

                Core::sendJson([
                    'domains' => [ 'available' => (new DomainList(UserList::getUserByName('admin')))->names() ]
                ]);
                return;
            }

            $core->auth()->isAllowed(User::LEVEL_USER);
            if ($core->getCurrentUser()->name() === $uname && $core->getCurrentUser()->level() < User::LEVEL_ADMIN) {
                // The current user and not Admin
                $udata = (new DbUser($uname))->toArray();
                Core::sendJson([
                    'name'     => $udata['name'],
                    'level'    => User::levelToString($udata['level']),
                    'password' => $udata['password'] // bool value
                ]);
                return;
            }

            // Retrieving user data by Admin
            $core->auth()->isAllowed(User::LEVEL_ADMIN);

            $user = new DbUser($uname);
            $res  = $user->toArray();
            $res['level'] = User::levelToString($res['level']);
            $res['domains'] = [
                'available' => (new DomainList(UserList::getUserByName('admin')))->names(),
                'assigned'  => (new DomainList($user))->names()
            ];
            Core::sendJson($res);
            return;
        } elseif (Core::requestMethod() == 'POST') {
            $data = Core::getJsonData();
            if ($data) {
                $uname = $data['name'] ?? null;
                $action = $data['action'] ?? '';
                if ($action === 'set_password') {
                    $core->auth()->isAllowed(User::LEVEL_USER);

                    $user = new DbUser($uname);
                    $c_user = $core->getCurrentUser();
                    if ($c_user->name() === $user->name()) {
                        if (!$c_user->verifyPassword($data['password'] ?? '')) {
                            throw new SoftException('The current password is incorrect');
                        }
                    } else {
                        $core->auth()->isAllowed(User::LEVEL_ADMIN);
                        if ($c_user->level() <= $user->level()) {
                            throw new ForbiddenException('Forbidden');
                        }
                    }
                    if (empty($data['new_password'])) {
                        throw new SoftException('New password must not be empty');
                    }
                    $user->setPassword($data['new_password']);
                    Core::sendJson([
                        'error_code' => 0,
                        'message'    => 'The password has been successfully updated'
                    ]);
                    return;
                }

                $core->auth()->isAllowed(User::LEVEL_ADMIN);

                if (!empty($data['level']) && gettype($data['level']) == 'string') {
                    $data['level'] = User::stringToLevel($data['level']);
                }
                $user = new DbUser([
                    'name'    => $uname,
                    'level'   => $data['level'] ?? null,
                    'enabled' => $data['enabled'] ?? null
                ]);
                $check_level = function () use ($core, $user, $action) {
                    if ($core->getCurrentUser()->level() <= $user->level()) {
                        throw new SoftException("Insufficient access level to {$action} this user");
                    }
                };
                switch ($action) {
                    case 'add':
                        $check_level();
                        $user->ensure('nonexist');
                        $user->save();
                        break;
                    case 'update':
                        $check_level();
                        $user->ensure('exist');
                        $user->save();
                        break;
                    case 'delete':
                        $check_level();
                        $user->delete();
                        unset($user);
                        break;
                    default:
                        throw new SoftException(
                            'Unknown action. Valid values are "add", "update", "delete", "set_password".'
                        );
                }

                $domains = $data['domains'] ?? null;
                if (is_array($domains)) {
                    $domains = array_values(array_unique(array_filter($domains, function ($val) {
                        return is_string($val);
                    })));
                    $user->assignDomains($domains);
                }

                $res = [
                    'error_code' => 0,
                    'message'    => 'Successfully'
                ];
                if (isset($user)) {
                    $ua = $user->toArray();
                    $ua['level'] = User::levelToString($ua['level']);
                    $res['user'] = $ua;
                }
                Core::sendJson($res);
                return;
            }
        }
    } catch (RuntimeException $e) {
        Core::sendJson(ErrorHandler::exceptionResult($e));
        return;
    }
} elseif (Core::requestMethod() == 'GET') {
    Core::instance()->sendHtml();
    return;
}

Core::sendBad();
