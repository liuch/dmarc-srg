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
use Liuch\DmarcSrg\Report\ReportList;

require 'debug.php';
require 'init.php';

if (Core::isJson()) {
    if (Core::method() == "GET" && isset($_GET['list'])) {
        try {
            Core::auth()->isAllowed();
            $lst = explode(',', $_GET['list']);
            $res = [];
            if (array_search('reports', $lst) !== false) {
                $pos = isset($_GET['position']) ? intval($_GET['position']) : 0;
                $dir = isset($_GET['direction']) ? $_GET['direction'] : 'ascent';
                $order = isset($_GET['order']) ? $_GET['order'] : 'begin_time';
                $filter = null;
                if (isset($_GET['filter'])) {
                    $filter = [];
                    $pa = gettype($_GET['filter']) == 'array' ?
                        $_GET['filter'] : [ $_GET['filter'] ];
                    foreach ($pa as $it) {
                        $ia = explode(':', $it, 2);
                        if (count($ia) == 2) {
                            $filter[$ia[0]] = $ia[1];
                        }
                    }
                }
                $list = new ReportList();
                if ($filter) {
                    $list->setFilter($filter);
                }
                $n_dir = $dir === 'ascent' ?
                    ReportList::ORDER_ASCENT : ReportList::ORDER_DESCENT;
                $n_order = $order === 'begin_time' ?
                    ReportList::ORDER_BEGIN_TIME : ReportList::ORDER_NONE;
                $list->setOrder($n_order, $n_dir);
                $res = $list->getList($pos);
            }
            if (array_search('filters', $lst) !== false) {
                $res['filters'] = ReportList::getFilterList();
            }
            Core::sendJson($res);
            return;
        } catch (Exception $e) {
            Core::sendJson(
                [
                    'error_code' => $e->getCode(),
                    'message'    => $e->getMessage()
                ]
            );
            return;
        }
    }
    Core::sendBad();
    return;
}

Core::sendHtml();

