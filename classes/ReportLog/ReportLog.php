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

namespace Liuch\DmarcSrg\ReportLog;

use Liuch\DmarcSrg\Core;

class ReportLog
{
    public const ORDER_ASCENT  = 1;
    public const ORDER_DESCENT = 2;

    private $db        = null;
    private $filter    = [
            'from_time' => null,
            'till_time' => null
    ];
    private $order     = [
            'direction' => 'ascent'
    ];
    private $rec_limit = 0;
    private $position  = 0;

    public function __construct($from_time, $till_time, $db = null)
    {
        $this->filter['from_time'] = $from_time;
        $this->filter['till_time'] = $till_time;
        $this->db = $db ?? Core::instance()->database();
    }

    public function setOrder(int $dir)
    {
        $this->order['direction'] = ($dir === self::ORDER_DESCENT ? 'descent' : 'ascent');
    }

    public function setMaxCount(int $n)
    {
        $this->rec_limit = $n;
    }

    public function count()
    {
        $limit = [ 'offset' => 0, 'count' => $this->rec_limit ];
        return $this->db->getMapper('report-log')->count($this->filter, $limit);
    }

    public function getList(int $pos)
    {
        $this->position = $pos;
        $max_rec = $this->rec_limit > 0 ? $this->rec_limit : 25;

        $limit  = [ 'offset' => $pos, 'count' => $max_rec + 1 ];

        $list = $this->db->getMapper('report-log')->list($this->filter, $this->order, $limit);
        if (count($list) > $max_rec) {
            $more = true;
            unset($list[$max_rec]);
        } else {
            $more = false;
        }
        foreach ($list as &$it) {
            $it['source'] = ReportLogItem::sourceToString($it['source']);
        }
        unset($it);

        return [
            'items' => $list,
            'more'  => $more
        ];
    }

    public function delete()
    {
        $limit = [ 'offset' => 0, 'count' => $this->rec_limit ];
        $this->db->getMapper('report-log')->delete($this->filter, $this->order, $limit);
    }
}
