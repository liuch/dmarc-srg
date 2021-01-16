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

use PDO;
use Exception;
use Liuch\DmarcSrg\Database\Database;

class ReportLog
{
    public const ORDER_ASCENT  = 1;
    public const ORDER_DESCENT = 2;

    private $from_time = null;
    private $till_time = null;
    private $direction = self::ORDER_ASCENT;
    private $rec_count = 0;

    public function __construct($from_time, $till_time)
    {
        $this->from_time = $from_time;
        $this->till_time = $till_time;
    }

    public function setOrder(int $dir)
    {
        $this->direction = $dir;
    }

    public function setMaxCount(int $n)
    {
        $this->rec_count = $n;
    }

    public function count()
    {
        $cnt = 0;
        $db = Database::connection();
        try {
            $st = $db->prepare('SELECT COUNT(*) FROM `reportlog`' . $this->sqlCondition() . $this->sqlLimit());
            $this->sqlBindValues($st);
            $st->execute();
            $cnt = $st->fetch(PDO::FETCH_NUM)[0];
            $st->closeCursor();
        } catch (Exception $e) {
            throw new Exception('Failed to get the log data', -1);
        }
        return $cnt;
    }

    public function delete()
    {
        $db = Database::connection();
        try {
            $st = $db->prepare('DELETE FROM `reportlog`' . $this->sqlCondition() . $this->sqlOrder() . $this->sqlLimit());
            $this->sqlBindValues($st);
            $st->execute();
            $st->closeCursor();
        } catch (Exception $e) {
            throw new Exception('Failed to remove the log data', -1);
        }
    }

    private function sqlCondition()
    {
        $res = '';
        if (!is_null($this->from_time) || !is_null($this->till_time)) {
            $res = ' WHERE';
            if (!is_null($this->from_time)) {
                $res .= ' `event_time` >= FROM_UNIXTIME(?)';
                if (!is_null($this->till_time)) {
                    $res .= ' AND';
                }
            }
            if (!is_null($this->till_time)) {
                $res .= ' `event_time` < FROM_UNIXTIME(?)';
            }
        }
        return $res;
    }

    private function sqlOrder()
    {
        return ' ORDER BY `event_time` ' . ($this->direction === self::ORDER_ASCENT ? 'ASC' : 'DESC');
    }

    private function sqlLimit()
    {
        $res = '';
        if ($this->rec_count > 0) {
            $res = ' LIMIT ?';
        }
        return $res;
    }

    private function sqlBindValues($st)
    {
        $pos = 1;
        if (!is_null($this->from_time)) {
            $st->bindValue($pos++, $this->from_time, PDO::PARAM_INT);
        }
        if (!is_null($this->till_time)) {
            $st->bindValue($pos++, $this->till_time, PDO::PARAM_INT);
        }
        if ($this->rec_count > 0) {
            $st->bindValue($pos, $this->rec_count, PDO::PARAM_INT);
        }
    }
}

