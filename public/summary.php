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
 * This script is to get summary report via the web interface
 *
 * HTTP GET query:
 *   when the header 'Accept' is 'application/json':
 *     if parameter `mode` is `options`, it returs data for the report options dialog;
 *     if parameter `mode` is `report`, it returns report data for the specified domain and period
 *   otherwise:
 *     it returns the content of the template.html file
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
use Liuch\DmarcSrg\Report\SummaryReport;
use Liuch\DmarcSrg\Report\OverallReport;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\RuntimeException;

require realpath(__DIR__ . '/..') . '/init.php';

if (Core::isJson() && isset($_GET['mode'])) {
    try {
        $core = Core::instance();
        $core->auth()->isAllowed(User::LEVEL_USER);
        $user = $core->user();

        $mode = $_GET['mode'];
        if ($mode === 'options') {
            Core::sendJson(
                [
                    'domains' => (new DomainList($user))->names()
                ]
            );
            return;
        } elseif ($mode === 'report') {
            if (empty($_GET['domain'])) {
                throw new SoftException('Parameter "domain" is not specified');
            }
            if (empty($_GET['period'])) {
                throw new SoftException('Parameter "period" is not specified');
            }

            $format = $_GET['format'] ?? '';
            if (!in_array($format, [ 'raw', 'text', 'csv' ])) {
                throw new SoftException('The `format` parameter can only be `raw`, `text` or `csv`');
            }

            $ovr = null;
            $rep = new SummaryReport($_GET['period']);
            if (true) { // TODO this optionally using GET paramenter
                $ovr = new OverallReport();
                $rep->bindSection($ovr);
            }
            $reports = [];
            foreach (explode(',', $_GET['domain']) as $d) {
                $dom = new Domain($d);
                $dom->isAssigned($user, true);
                $rep->setDomain($dom);
                $r = [ 'domain' => $d ];
                switch ($format) {
                    case 'raw':
                        $r['data'] = $rep->toArray();
                        break;
                    case 'text':
                        $r['text'] = $rep->text();
                        break;
                    case 'csv':
                        $r['csv'] = $rep->csv();
                        break;
                }
                $reports[] = $r;
            }
            $res['reports'] = $reports;
            if ($ovr) {
                $r = [];
                switch ($format) {
                    case 'raw':
                        $r['data'] = $ovr->toArray();
                        break;
                    case 'text':
                        $r['text'] = $ovr->text();
                        break;
                    case 'csv':
                        $r['csv'] = $ovr->csv();
                        break;
                }
                $res['overall'] = $r;
            }

            Core::sendJson($res);
            return;
        } else {
            throw new SoftException('The `mode` parameter can only be `options` or `report`');
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
