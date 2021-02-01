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

namespace Liuch\DmarcSrg\Report;

use PDO;
use Exception;
use Liuch\DmarcSrg\Common;
use Liuch\DmarcSrg\Domains\Domain;
use Liuch\DmarcSrg\Database\Database;

class ReportList
{
    public const ORDER_NONE       = 0;
    public const ORDER_BEGIN_TIME = 1;
    public const ORDER_ASCENT     = 2;
    public const ORDER_DESCENT    = 3;

    private $limit     = null;
    private $position  = null;
    private $filter    = null;
    private $order     = null;

    public function getList(int $pos)
    {
        $this->position = $pos;
        $def_limit = false;
        if (is_null($this->limit)) {
            $this->limit = 25;
            $def_limit = true;
        }
        $order_str = $this->sqlOrder();
        $cond_str = $this->sqlCondition(' AND ');
        $limit_str = $this->sqlLimit();
        $db = Database::connection();
        try {
            $st = $db->prepare("SELECT `org`, `begin_time`, `end_time`, `fqdn`, external_id, `seen`, SUM(`rcount`) AS `rcount`, MIN(`dkim_align`) AS `dkim_align`, MIN(`spf_align`) AS `spf_align`, MIN(`disposition`) AS `disposition` FROM `rptrecords` RIGHT JOIN (SELECT `reports`.`id`, `org`, `begin_time`, `end_time`, `external_id`, `fqdn`, `seen` FROM `reports` INNER JOIN `domains` ON `domains`.`id` = `reports`.`domain_id`{$cond_str}{$order_str}{$limit_str}) AS `reports` ON `reports`.id = `rptrecords`.`report_id` GROUP BY `reports`.`id`{$order_str}");
            $this->sqlBindValues($st, 1);
            $st->execute();
            $r_cnt = 0;
            $list = [];
            $more = false;
            while ($res = $st->fetch(PDO::FETCH_NUM)) {
                if (++$r_cnt <= $this->limit) {
                    $list[] = [
                        'org_name'    => $res[0],
                        'date'        => [
                            'begin' => $res[1] . 'Z',
                            'end'   => $res[2] . 'Z'
                        ],
                        'domain'      => $res[3],
                        'report_id'   => $res[4],
                        'seen'        => (bool) $res[5],
                        'messages'    => $res[6],
                        'dkim_align'  => Common::$align_res[$res[7]],
                        'spf_align'   => Common::$align_res[$res[8]],
                        'disposition' => Common::$disposition[$res[9]]
                    ];
                } else {
                    $more = true;
                }
            }
            $st->closeCursor();
            unset($st);
        } catch (Exception $e) {
            throw new Exception('Failed to get the report list', -1);
        } finally {
            if ($def_limit) {
                $this->limit = null;
            }
        }
        return [
            'reports' => $list,
            'more'    => $more
        ];
    }

    public function setOrder(int $field, int $direction)
    {
        $this->order = null;
        if ($field > self::ORDER_NONE && $field < self::ORDER_ASCENT) {
            if ($direction !== self::ORDER_ASCENT) {
                $direction = self::ORDER_DESCENT;
            }
            $this->order = [
                'field'     => $field,
                'direction' => $direction
            ];
        }
    }

    public function setMaxCount(int $num)
    {
        if ($num > 0) {
            $this->limit = $num;
        } else {
            $this->limit = null;
        }
    }

    public function setFilter(array $filter)
    {
        $this->filter = null;
        $a_str = [];
        $uses = false;
        $bindings = [];
        foreach (ReportList::$filters_available as $fn) {
            if (isset($filter[$fn])) {
                $fv = $filter[$fn];
                if (gettype($fv) == 'string') {
                    if (!empty($fv)) {
                        if ($fn == 'domain') {
                            $a_str[] = '`reports`.`domain_id` = ?';
                            $bindings[] = [ (new Domain($fv))->id(), PDO::PARAM_INT ];
                        } elseif ($fn == 'month') {
                            $ma = explode('-', $fv);
                            if (count($ma) != 2) {
                                throw new Exception('Incorrect date format', -1);
                            }
                            $year = (int)$ma[0];
                            $month = (int)$ma[1];
                            if ($year < 0 || $month < 1 || $month > 12) {
                                throw new Exception('Incorrect month or year value', -1);
                            }
                            $a_str[] = '`begin_time` < FROM_UNIXTIME(?) AND `end_time` >= FROM_UNIXTIME(?)';
                            $dtz = date_default_timezone_get();
                            date_default_timezone_set('GMT');
                            $bindings[] = [ mktime(0, 0, 0, $month + 1, 1, $year) - 11, PDO::PARAM_INT ];
                            $bindings[] = [ mktime(0, 0, 0, $month, 1, $year) + 10, PDO::PARAM_INT ];
                            date_default_timezone_set($dtz);
                        } elseif ($fn == 'organization') {
                            $a_str[] = '`org` = ?';
                            $bindings[] = [ $fv, PDO::PARAM_STR ];
                        }
                        $uses = true;
                    }
                } elseif (gettype($fv) == 'integer') {
                    if ($fv > 0) {
                        if ($fn == 'before_time') {
                            $a_str[] = '`begin_time` < FROM_UNIXTIME(?)';
                            $bindings[] = [ $fv, PDO::PARAM_INT ];
                        }
                        $uses = true;
                    }
                } elseif (gettype($fv) == "object") {
                    $a_str[] = '`reports`.`domain_id` = ?';
                    $bindings[] = [ $fv->id(), PDO::PARAM_INT ];
                    $uses = true;
                }
            }
        }
        if ($uses) {
            $this->filter = [
                'str'      => implode(' AND ', $a_str),
                'bindings' => $bindings
            ];
        }
    }

    public function count()
    {
        $cnt = 0;
        $db = Database::connection();
        try {
            $st = $db->prepare('SELECT COUNT(*) FROM `reports`' . $this->sqlCondition(' WHERE '));
            $this->sqlBindValues($st, -1);
            $st->execute();
            $cnt = $st->fetch(PDO::FETCH_NUM)[0];
            $st->closeCursor();
            if (!is_null($this->position)) {
                $cnt -= ($this->position - 1);
                if ($cnt < 0) {
                    $cnt = 0;
                }
            }

            if (!is_null($this->limit) && $this->limit < $cnt) {
                $cnt = $this->limit;
            }
        } catch (Exception $e) {
            throw new Exception('Failed to get the number of reports', -1);
        }
        return $cnt;
    }

    public function delete()
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $cond_str = $this->sqlCondition(' WHERE ');
            $order_str = $this->sqlOrder();
            $limit_str = $this->sqlLimit();

            $st = $db->prepare("DELETE `rr` FROM `rptrecords` AS `rr` INNER JOIN (SELECT `id` FROM `reports`{$cond_str}{$order_str}{$limit_str}) AS `rp` ON `rp`.`id` = `rr`.`report_id`");
            $this->sqlBindValues($st, 0);
            $st->execute();
            $st->closeCursor();

            $st = $db->prepare("DELETE FROM `reports`{$cond_str}{$order_str}{$limit_str}");
            $this->sqlBindValues($st, 0);
            $st->execute();
            $st->closeCursor();

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw new Exception('Failed to delete reports', -1);
        }
    }

    public static function getFilterList()
    {
        $res = [];
        $db = Database::connection();
        try {
            $domains = [];
            $st = $db->query('SELECT `fqdn` FROM `domains` ORDER BY `fqdn`');
            while ($r = $st->fetch(PDO::FETCH_NUM)) {
                $domains[] = $r[0];
            }
            $st->closeCursor();
            $res['domain'] = $domains;

            $months = [];
            $st = $db->query('SELECT DISTINCT DATE_FORMAT(`date`, "%Y-%m") FROM ((SELECT DISTINCT `begin_time` AS `date` FROM `reports`) UNION (SELECT DISTINCT `end_time` AS `date` FROM `reports`)) AS `r` ORDER BY `date` DESC');
            while ($r = $st->fetch(PDO::FETCH_NUM)) {
                $months[] = $r[0];
            }
            $st->closeCursor();
            $res['month'] = $months;

            $orgs = [];
            // TODO оптимизировать индексом!
            $st = $db->query('SELECT DISTINCT `org` FROM `reports` ORDER BY `org`');
            while ($r = $st->fetch(PDO::FETCH_NUM)) {
                $orgs[] = $r[0];
            }
            $st->closeCursor();
            $res['organization'] = $orgs;
        } catch (Exception $e) {
            throw new Exception('Failed to get a list of domains', -1);
        }
        return $res;
    }

    private static $filters_available = [
        'domain', 'month', 'before_time', 'organization'
    ];

    private function sqlCondition($prefix)
    {
        return $this->filter ? ($prefix . $this->filter['str']) : '';
    }

    private function sqlOrder()
    {
        if (!$this->order) {
            return '';
        }

        $dir = $this->order['direction'] === self::ORDER_ASCENT ? 'ASC' : 'DESC';
        $fname = null;
        switch ($this->order['field']) {
            case self::ORDER_BEGIN_TIME:
                $fname = 'begin_time';
                break;
        }

        return " ORDER BY `{$fname}` {$dir}";
    }

    private function sqlLimit()
    {
        $res = '';
        if (!is_null($this->limit)) {
            $res = ' LIMIT ?';
            if (!is_null($this->position)) {
                $res .= ', ?';
            }
        }
        return $res;
    }

    private function sqlBindValues($st, $inc_limit)
    {
        $pos = 0;
        if ($this->filter) {
            foreach ($this->filter['bindings'] as &$bv) {
                $st->bindValue(++$pos, $bv[0], $bv[1]);
            }
            unset($bv);
        }
        if (!is_null($this->limit) && $inc_limit >= 0) {
            if (!is_null($this->position)) {
                $st->bindValue(++$pos, $this->position, PDO::PARAM_INT);
            }
            $st->bindValue(++$pos, $this->limit + $inc_limit, PDO::PARAM_INT);
        }
    }
}

