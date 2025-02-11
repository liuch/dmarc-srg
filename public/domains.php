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
 * This script returns the domains information
 *
 * HTTP GET query:
 *   when the header 'Accept' is 'application/json':
 *     It returns a list of the domains or data for the domain specified in the parameter domain.
 *   otherwise:
 *     it returns the content of the template.html file
 * HTTP POST query:
 *   Inserts or updates data for specified domain. The data must be in json format with the following fields:
 *     `fqdn`        string  FQDN of the domain.
 *     `action`      string  Must be one of the following values: `add`, `update`, `delete`.
 *                           If the value is `delete`, all the fields below will be ignored.
 *     `active`      boolean Whether reports for the domain will be accepted or not.
 *     `description` string  Description of the domain.
 *   Example:
 *     { "fqnd": "example.com", "action": "update", "active": true, "description": "My description" }
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

use Liuch\DmarcSrg\Users\User;
use Liuch\DmarcSrg\Domains\Domain;
use Liuch\DmarcSrg\Domains\DomainList;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\RuntimeException;

require realpath(__DIR__ . '/..') . '/init.php';

if (Core::isJson()) {
    try {
        if (Core::requestMethod() == 'GET') {
            $core = Core::instance();
            $core->auth()->isAllowed(User::LEVEL_USER);

            $user = $core->user();
            if (isset($_GET['domain'])) {
                $fqdn = trim($_GET['domain']);
                if (empty($fqdn)) {
                    $core->auth()->isAllowed(User::LEVEL_MANAGER);
                    $res = [];
                    if ($user->id() !== 0 && $core->config('users/domain_verification', 'none') === 'dns') {
                        $res['verification'] = 'dns';
                        $res['verification_data'] = 'dmarcsrg-verification=' . $user->verificationString();
                    }
                } else {
                    $domain = new Domain($_GET['domain']);
                    $domain->isAssigned($user, true);
                    $res = $domain->toArray();
                }
                Core::sendJson($res);
                return;
            }

            $res = (new DomainList($user))->getList();
            $list = array_map(function ($domain) {
                return $domain->toArray();
            }, $res['domains']);

            Core::sendJson([
                'domains' => $list,
                'more'    => $res['more']
            ]);
            return;
        } elseif (Core::requestMethod() == 'POST') {
            Core::instance()->auth()->isAllowed(User::LEVEL_MANAGER);

            $data = Core::getJsonData();
            if ($data) {
                $domain = new Domain([
                    'fqdn'        => $data['fqdn'] ?? null,
                    'active'      => $data['active'] ?? null,
                    'description' => $data['description'] ?? null
                ]);
                $user   = Core::instance()->user();
                $action = $data['action'] ?? '';
                switch ($action) {
                    case 'add':
                        if ($domain->isAssigned($user)) {
                            throw new SoftException('The domain already exists');
                        }
                        if ($user->id() !== 0) {
                            $method = $core->config('users/domain_verification', 'none');
                            if ($method !== 'none') {
                                $domain->verifyOwnership($user->verificationString(), $method);
                            }
                        }
                        if (!$domain->exists()) {
                            $domain->save();
                        }
                        $domain->assignUser($user);
                        break;
                    case 'update':
                        $domain->isAssigned($user, true);
                        $domain->save();
                        break;
                    case 'delete':
                        if ($user->id() === 0) {
                            $domain->delete();
                        } else {
                            $domain->unassignUser($user);
                        }
                        unset($domain);
                        break;
                    default:
                        throw new SoftException('Unknown action. Valid values are "add", "update", "delete".');
                }

                $res = [
                    'error_code' => 0,
                    'message'    => 'Successfully'
                ];
                if (isset($domain)) {
                    $res['domain'] = $domain->toArray();
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
