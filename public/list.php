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
use Liuch\DmarcSrg\Report\ReportList;
use Liuch\DmarcSrg\Exception\RuntimeException;

require realpath(__DIR__ . '/..') . '/init.php';

if (Core::requestMethod() == 'GET') {
    if (Core::isJson() && isset($_GET['list'])) {
        try {
            $core = Core::instance();
            $core->auth()->isAllowed(User::LEVEL_USER);

            $lst = explode(',', $_GET['list']);
            $res = [];
            $first_page = false;
            if (array_search('reports', $lst) !== false) {
                $pos = isset($_GET['position']) ? intval($_GET['position']) : 0;
                $dir = isset($_GET['direction']) ? $_GET['direction'] : 'ascent';
                $order = isset($_GET['order']) ? $_GET['order'] : 'begin_time';
                $filter = Common::getFilter();
                $list = new ReportList($core->user());
                if ($filter) {
                    $list->setFilter($filter);
                }
                $n_dir = $dir === 'ascent' ?
                    ReportList::ORDER_ASCENT : ReportList::ORDER_DESCENT;
                $n_order = $order === 'begin_time' ?
                    ReportList::ORDER_BEGIN_TIME : ReportList::ORDER_NONE;
                $list->setOrder($n_order, $n_dir);
                $res = $list->getList($pos);
                $first_page = ($pos === 0);
            }
            if (array_search('count', $lst) !== false) {
                if ($first_page && !$res['more'] && intval($_GET['position'] ?? 0) === 0) {
                    $tc = 0;
                    $uc = 0;
                    foreach ($res['reports'] as $r) {
                        if (!$r['seen']) {
                            ++$uc;
                        }
                        ++$tc;
                    }
                } else {
                    $list = new ReportList($core->user());
                    $filter = Common::getFilter();
                    if ($filter) {
                        $list->setFilter($filter);
                    }
                    $tc = $list->count();
                    if (!$filter) {
                        $filter = [];
                    }
                    $status = $filter['status'] ?? '';
                    if ($status === 'unread') {
                        $uc = $tc;
                    } elseif ($status === 'read') {
                        $uc = 0;
                    } else {
                        $filter['status'] = 'unread';
                        $uc = $list->setFilter($filter)->count();
                    }
                }
                $res['count'] = [ 'total' => $tc, 'unread' => $uc ];
            }
            if (array_search('filters', $lst) !== false) {
                $res['filters'] = (new ReportList($core->user()))->getFilterList();
            }
            Core::sendJson($res);
        } catch (RuntimeException $e) {
            Core::sendJson(ErrorHandler::exceptionResult($e));
        }
        return;
    }
    Core::instance()->sendHtml();
    return;
}

Core::sendBad();
