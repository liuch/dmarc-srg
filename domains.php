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
 * This script returns the domains information
 *
 * HTTP GET query:
 *   when the header 'Accept' is 'application/json':
 *     It returns a list of the domains or data for the domain specified in the parameter domain.
 *   otherwise:
 *     it returns the content of the index.html file
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

use Liuch\DmarcSrg\ErrorHandler;
use Liuch\DmarcSrg\Domains\Domain;
use Liuch\DmarcSrg\Domains\DomainList;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\RuntimeException;

require 'init.php';

if (Core::isJson()) {
    try {
        Core::instance()->auth()->isAllowed();

        if (Core::method() == 'GET') {
            if (isset($_GET['domain'])) {
                Core::sendJson((new Domain($_GET['domain']))->toArray());
                return;
            }

            $res = (new DomainList())->getList();
            $list = array_map(function ($domain) {
                return $domain->toArray();
            }, $res['domains']);

            Core::sendJson([
                'domains' => $list,
                'more'    => $res['more']
            ]);
            return;
        } elseif (Core::method() == 'POST') {
            $data = Core::getJsonData();
            if ($data) {
                $domain = new Domain([
                    'fqdn'        => $data['fqdn'] ?? null,
                    'active'      => $data['active'] ?? null,
                    'description' => $data['description'] ?? null
                ]);
                $action = $data['action'] ?? '';
                switch ($action) {
                    case 'add':
                        if ($domain->exists()) {
                            throw new SoftException('The domain already exists');
                        }
                        $domain->save();
                        break;
                    case 'update':
                        if (!$domain->exists()) {
                            throw new SoftException('The domain does not exist');
                        }
                        $domain->save();
                        break;
                    case 'delete':
                        $domain->delete();
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
} elseif (Core::method() == 'GET') {
    Core::sendHtml();
    return;
}

Core::sendBad();
