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
use Liuch\DmarcSrg\Domains\Domain;
use Liuch\DmarcSrg\Report\Report;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\RuntimeException;

require realpath(__DIR__ . '/..') . '/init.php';

if (!empty($_GET['org']) && !empty($_GET['time']) && !empty($_GET['domain']) && !empty($_GET['report_id'])) {
    if (Core::isJson()) {
        try {
            $core = Core::instance();
            $core->auth()->isAllowed(User::LEVEL_USER);

            $domain = new Domain($_GET['domain']);
            $dom_ex = $domain->isAssigned($core->user()) ? null : new SoftException('Report not found');

            $ts = null;
            try {
                $ts = new DateTime($_GET['time']);
            } catch (\Exception $e) {
                throw new SoftException('Incorrect timestamp');
            }

            if (Core::requestMethod() == 'GET') {
                if ($dom_ex) {
                    throw $dom_ex;
                }
                $rep = new Report(
                    [
                        'domain'     => $domain,
                        'org_name'   => $_GET['org'],
                        'date'       => [
                            'begin'  => $ts
                        ],
                        'report_id'  => $_GET['report_id']
                    ]
                );
                $rep->fetch();
                Core::sendJson([ 'report' => $rep->toArray() ]);
                return;
            } elseif (Core::requestMethod() == 'POST') {
                if ($_GET['action'] === 'set') {
                    $jdata = Core::getJsonData();
                    if ($jdata && isset($jdata['name']) && isset($jdata['value'])) {
                        if ($dom_ex) {
                            throw $dom_ex;
                        }
                        $rep = new Report(
                            [
                                'domain'     => $domain,
                                'org_name'   => $_GET['org'],
                                'date'       => [
                                    'begin'  => $ts
                                ],
                                'report_id'  => $_GET['report_id']
                            ]
                        );
                        Core::sendJson($rep->set($jdata['name'], $jdata['value']));
                        return;
                    }
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
}

Core::sendBad();
