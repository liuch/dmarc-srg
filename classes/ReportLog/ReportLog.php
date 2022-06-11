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

use Liuch\DmarcSrg\DateTime;
use Liuch\DmarcSrg\Database\Database;

class ReportLog
{
    public const ORDER_ASCENT  = 1;
    public const ORDER_DESCENT = 2;

    private $from_time = null;
    private $till_time = null;
    private $direction = self::ORDER_ASCENT;
    private $rec_limit = 0;
    private $position  = null;

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
        $this->rec_limit = $n;
    }

    public function count()
    {
        $cnt = 0;
        $db = Database::connection();
        try {
            $st = $db->prepare(
                'SELECT COUNT(*) FROM `' . Database::tablePrefix('reportlog') . '`'
                . $this->sqlCondition() . $this->sqlLimit(false)
            );
            $this->sqlBindValues($st, 0);
            $st->execute();
            $cnt = $st->fetch(\PDO::FETCH_NUM)[0];
            $st->closeCursor();
        } catch (\Exception $e) {
            throw new \Exception('Failed to get the log data', -1);
        }
        return $cnt;
    }

    public function getList(int $pos)
    {
        $this->position = $pos;
        $def_limit = false;
        if ($this->rec_limit === 0) {
            $this->rec_limit = 25;
            $def_limit = true;
        }

        $db = Database::connection();
        try {
            $st = $db->prepare(
                'SELECT `id`, `domain`, `event_time`, `source`, `success`, `message` FROM `'
                . Database::tablePrefix('reportlog') . '`'
                . $this->sqlCondition()
                . $this->sqlOrder()
                . $this->sqlLimit(true)
            );
            $this->sqlBindValues($st, 1);
            $st->execute();
            $r_cnt = 0;
            $list = [];
            $more = false;
            while ($res = $st->fetch(\PDO::FETCH_NUM)) {
                if (++$r_cnt <= $this->rec_limit) {
                    $list[] = [
                        'id'         => intval($res[0]),
                        'domain'     => $res[1],
                        'event_time' => new DateTime($res[2]),
                        'source'     => ReportLogItem::sourceToString(intval($res[3])),
                        'success'    => boolval($res[4]),
                        'message'    => $res[5]
                    ];
                } else {
                    $more = true;
                }
            }
            $st->closeCursor();
        } catch (\Exception $e) {
            throw new \Exception('Failed to get the logs', -1);
        } finally {
            if ($def_limit) {
                $this->rec_limit = 0;
            }
        }
        return [
            'items' => $list,
            'more'  => $more
        ];
    }

    public function delete()
    {
        $db = Database::connection();
        try {
            $st = $db->prepare(
                'DELETE FROM `' . Database::tablePrefix('reportlog') . '`'
                . $this->sqlCondition()
                . $this->sqlOrder()
                . $this->sqlLimit(false)
            );
            $this->sqlBindValues($st, 0);
            $st->execute();
            $st->closeCursor();
        } catch (\Exception $e) {
            throw new \Exception('Failed to remove the log data', -1);
        }
    }

    private function sqlCondition()
    {
        $res = '';
        if (!is_null($this->from_time) || !is_null($this->till_time)) {
            $res = ' WHERE';
            if (!is_null($this->from_time)) {
                $res .= ' `event_time` >= ?';
                if (!is_null($this->till_time)) {
                    $res .= ' AND';
                }
            }
            if (!is_null($this->till_time)) {
                $res .= ' `event_time` < ?';
            }
        }
        return $res;
    }

    private function sqlOrder()
    {
        return ' ORDER BY `event_time` ' . ($this->direction === self::ORDER_ASCENT ? 'ASC' : 'DESC');
    }

    private function sqlLimit(bool $with_position)
    {
        $res = '';
        if ($this->rec_limit > 0) {
            $res = ' LIMIT ?';
            if ($with_position) {
                $res .= ', ?';
            }
        }
        return $res;
    }

    private function sqlBindValues($st, int $inc_limit)
    {
        $pos = 0;
        if (!is_null($this->from_time)) {
            $st->bindValue(++$pos, $this->from_time->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
        }
        if (!is_null($this->till_time)) {
            $st->bindValue(++$pos, $this->till_time->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
        }
        if ($this->rec_limit > 0 && $inc_limit >= 0) {
            if (!is_null($this->position)) {
                $st->bindValue(++$pos, $this->position, \PDO::PARAM_INT);
            }
            $st->bindValue(++$pos, $this->rec_limit + $inc_limit, \PDO::PARAM_INT);
        }
    }
}
