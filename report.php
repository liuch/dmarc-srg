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
use Liuch\DmarcSrg\Report\Report;

require 'init.php';

if (!empty($_GET['report_id']) && !empty($_GET['domain'])) {
    if (Core::isJson()) {
        try {
            Core::auth()->isAllowed();
            if (Core::method() == 'GET') {
                $rep = new Report(
                    [
                        'domain'    => $_GET['domain'],
                        'report_id' => $_GET['report_id']
                    ]
                );
                $rep->fetch();
                Core::sendJson([ 'report' => $rep->get() ]);
                return;
            } elseif (Core::method() == 'POST') {
                if ($_GET['action'] === 'set') {
                    $jdata = Core::getJsonData();
                    if ($jdata && isset($jdata['name']) && isset($jdata['value'])) {
                        $name = $jdata['name'];
                        $value = $jdata['value'];
                        $rep = new Report(
                            [
                                'domain'    => $_GET['domain'],
                                'report_id' => $_GET['report_id']
                            ]
                        );
                        Core::sendJson($rep->set($name, $value));
                        return;
                    }
                }
            }
        } catch (Exception $e) {
            Core::sendJson(
                [
                    'error_code' => $e->getCode(),
                    'message'    => $e->getMessage()
                ]
            );
            return;
        }
    } elseif (Core::method() == 'GET') {
        Core::sendHtml();
        return;
    }
}

Core::sendBad();

