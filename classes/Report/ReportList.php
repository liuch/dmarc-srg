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
 * This file contains ReportList class
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Report;

use Liuch\DmarcSrg\Common;
use Liuch\DmarcSrg\DateTime;
use Liuch\DmarcSrg\Domains\Domain;
use Liuch\DmarcSrg\Domains\DomainList;
use Liuch\DmarcSrg\Database\Database;

/**
 * It's the main class for working with the incoming reports, such as:
 *  - getting a list of reports
 *  - deleting several reports at once
 *  - getting the number of reports in the database
 */
class ReportList
{
    public const ORDER_NONE       = 0;
    public const ORDER_BEGIN_TIME = 1;
    public const ORDER_ASCENT     = 2;
    public const ORDER_DESCENT    = 3;

    private $limit     = null;
    private $position  = null;
    private $filters   = [];
    private $order     = null;

    /**
     * Returns a list of reports with specified parameters from position $pos
     *
     * This method returns a list of reports that depends on the filter and order.
     * The filter, order, and limit for the list can be set using the setFilter, setOrder and setMaxCount methods.
     *
     * @param int $pos The starting position from which the list will be passed
     *
     * @return array An array with keys `reports` and `more`.
     *               `reports` is an array of incoming reports which contains maximum 25 records by default.
     *                         Another value of the number of records can be specified by calling
     *                         the method setMaxCount.
     *               `more`    is true if there are more records in the database, false otherwise.
     */
    public function getList(int $pos): array
    {
        $this->position = $pos;
        $def_limit = false;
        if (is_null($this->limit)) {
            $this->limit = 25;
            $def_limit = true;
        }
        $order_str = $this->sqlOrder();
        $cond_str0 = $this->sqlCondition(' AND ', 0);
        $cond_str1 = $this->sqlCondition(' HAVING ', 1);
        $limit_str = $this->sqlLimit();
        $db = Database::connection();
        try {
            $st = $db->prepare(
                'SELECT `org`, `begin_time`, `end_time`, `fqdn`, external_id, `seen`, SUM(`rcount`) AS `rcount`,'
                . ' MIN(`dkim_align`) AS `dkim_align`, MIN(`spf_align`) AS `spf_align`,'
                . ' MIN(`disposition`) AS `disposition` FROM `' . Database::tablePrefix('rptrecords')
                . '` AS `rr` RIGHT JOIN (SELECT `rp`.`id`, `org`, `begin_time`, `end_time`, `external_id`,'
                . ' `fqdn`, `seen` FROM `' . Database::tablePrefix('reports')
                . '` AS `rp` INNER JOIN `' . Database::tablePrefix('domains')
                . '` AS `d` ON `d`.`id` = `rp`.`domain_id`' . $cond_str0 . $order_str
                . ') AS `rp` ON `rp`.`id` = `rr`.`report_id` GROUP BY `rp`.`id`'
                . $cond_str1 . $order_str . $limit_str
            );
            $this->sqlBindValues($st, 1);
            $st->execute();
            $r_cnt = 0;
            $list = [];
            $more = false;
            while ($res = $st->fetch(\PDO::FETCH_NUM)) {
                if (++$r_cnt <= $this->limit) {
                    $list[] = [
                        'org_name'    => $res[0],
                        'date'        => [
                            'begin' => new DateTime($res[1]),
                            'end'   => new DateTime($res[2])
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
        } catch (\Exception $e) {
            throw new \Exception('Failed to get the report list', -1);
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

    /**
     * Sets the sort order for the list and for deleting several reports at once
     *
     * @param int $field     The field to sort by. Currently only ORDER_BEGIN_TIME is available.
     * @param int $direction The sorting direction. ORDER_ASCENT or ORDER_DESCENT must be used here.
     *
     * @return void
     */
    public function setOrder(int $field, int $direction): void
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

    /**
     * Sets maximum numbers of records in the list and for deleting reports
     *
     * @param int $num Maximum number of records in the list
     *
     * @return void
     */
    public function setMaxCount(int $num): void
    {
        if ($num > 0) {
            $this->limit = $num;
        } else {
            $this->limit = null;
        }
    }

    /**
     * Sets filter values for the list and for deleting reports
     *
     * @param array $filter An key-value array:
     *                      'before_time'  => DateTime, timestamp
     *                      'dkim'         => string, 'fail' or 'pass'
     *                      'domain'       => string or instance of Domain class
     *                      'month'        => string, yyyy-mm format
     *                      'organization' => string
     *                      'spf'          => string, 'fail' or 'pass'
     *                      'status'       => string, 'read' or 'unread'
     *                      Note! 'dkim' and 'spf' do not affect the delete and count methods
     *
     * @return void
     */
    public function setFilter(array $filter): void
    {
        $this->filters = [];
        $filters = [];
        for ($i = 0; $i < 2; ++$i) {
            $filters[] = [
                'a_str'    => [],
                'bindings' => []
            ];
        }
        foreach (ReportList::$filters_available as $fn) {
            if (isset($filter[$fn])) {
                $fv = $filter[$fn];
                switch (gettype($fv)) {
                    case 'string':
                        if (!empty($fv)) {
                            if ($fn == 'domain') {
                                $filters[0]['a_str'][] = '`rp`.`domain_id` = ?';
                                $filters[0]['bindings'][] = [ (new Domain($fv))->id(), \PDO::PARAM_INT ];
                            } elseif ($fn == 'month') {
                                $ma = explode('-', $fv);
                                if (count($ma) != 2) {
                                    throw new \Exception('Incorrect date format', -1);
                                }
                                $year = (int)$ma[0];
                                $month = (int)$ma[1];
                                if ($year < 0 || $month < 1 || $month > 12) {
                                    throw new \Exception('Incorrect month or year value', -1);
                                }
                                $filters[0]['a_str'][] = '`begin_time` < ? AND `end_time` >= ?';
                                $date1 = new DateTime("${year}-${month}-01");
                                $date2 = (clone $date1)->modify('first day of next month');
                                $date1->add(new \DateInterval('PT10S'));
                                $date2->sub(new \DateInterval('PT10S'));
                                $filters[0]['bindings'][] = [ $date2->format('Y-m-d H:i:s'), \PDO::PARAM_STR ];
                                $filters[0]['bindings'][] = [ $date1->format('Y-m-d H:i:s'), \PDO::PARAM_STR ];
                            } elseif ($fn == 'organization') {
                                $filters[0]['a_str'][] = '`org` = ?';
                                $filters[0]['bindings'][] = [ $fv, \PDO::PARAM_STR ];
                            } elseif ($fn == 'dkim') {
                                if ($fv === Common::$align_res[0]) {
                                    $val = 0;
                                } else {
                                    $val = count(Common::$align_res) - 1;
                                    if ($fv !== Common::$align_res[$val]) {
                                        throw new \Exception('Incorrect DKIM filter', -1);
                                    }
                                }
                                $filters[1]['a_str'][] = '`dkim_align` = ?';
                                $filters[1]['bindings'][] = [ $val, \PDO::PARAM_INT ];
                            } elseif ($fn == 'spf') {
                                if ($fv === Common::$align_res[0]) {
                                    $val = 0;
                                } else {
                                    $val = count(Common::$align_res) - 1;
                                    if ($fv !== Common::$align_res[$val]) {
                                        throw new \Exception('Incorrect SPF filter', -1);
                                    }
                                }
                                $filters[1]['a_str'][] = '`spf_align` = ?';
                                $filters[1]['bindings'][] = [ $val, \PDO::PARAM_INT ];
                            } elseif ($fn == 'status') {
                                if ($fv === 'read') {
                                    $val = true;
                                } elseif ($fv === 'unread') {
                                    $val = false;
                                } else {
                                    throw new \Exception('Incorrect status filter');
                                }
                                $filters[0]['a_str'][] = '`seen` = ?';
                                $filters[0]['bindings'][] = [ $val, \PDO::PARAM_BOOL ];
                            }
                        }
                        break;
                    case 'object':
                        if ($fn == 'domain') {
                            $filters[0]['a_str'][] = '`rp`.`domain_id` = ?';
                            $filters[0]['bindings'][] = [ $fv->id(), \PDO::PARAM_INT ];
                            break;
                        } elseif ($fn == 'before_time') {
                            $filters[0]['a_str'][] = '`begin_time` < ?';
                            $filters[0]['bindings'][] = [ $fv->format('Y-m-d H:i:s'), \PDO::PARAM_STR ];
                        }
                }
            }
        }
        for ($i = 0; $i < count($filters); ++$i) {
            $filter = &$filters[$i];
            if (count($filter['a_str']) > 0) {
                $this->filters[$i] = [
                    'str'      => implode(' AND ', $filter['a_str']),
                    'bindings' => $filter['bindings']
                ];
            }
            unset($filter);
        }
    }

    /**
     * Returns the number of reports in the database
     *
     * It returns the number of reports in the database.
     * The limit and some filter items (`dkim`, `spf`) do not affect this.
     *
     * @return int The number of reports in the database
     */
    public function count(): int
    {
        $cnt = 0;
        $db = Database::connection();
        try {
            $st = $db->prepare(
                'SELECT COUNT(*) FROM `' . Database::tablePrefix('reports') . '` AS `rp`'
                . $this->sqlCondition(' WHERE ', 0)
            );
            $this->sqlBindValues($st, -1);
            $st->execute();
            $cnt = $st->fetch(\PDO::FETCH_NUM)[0];
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
        } catch (\Exception $e) {
            throw new \Exception('Failed to get the number of reports', -1);
        }
        return $cnt;
    }

    /**
     * Deletes reports from the database
     *
     * It deletes repors form the database. The filter items `dkim` and `spf` do not affect this.
     *
     * @return void
     */
    public function delete(): void
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $cond_str = $this->sqlCondition(' WHERE ', 0);
            $order_str = $this->sqlOrder();
            $limit_str = $this->sqlLimit();

            $st = $db->prepare(
                'DELETE `rr` FROM `' . Database::tablePrefix('rptrecords')
                . '` AS `rr` INNER JOIN (SELECT `id` FROM `' . Database::tablePrefix('reports') . '`'
                . $cond_str . $order_str . $limit_str . ') AS `rp` ON `rp`.`id` = `rr`.`report_id`'
            );
            $this->sqlBindValues($st, 0);
            $st->execute();
            $st->closeCursor();

            $st = $db->prepare(
                'DELETE FROM `' . Database::tablePrefix('reports') . "`{$cond_str}{$order_str}{$limit_str}"
            );
            $this->sqlBindValues($st, 0);
            $st->execute();
            $st->closeCursor();

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw new \Exception('Failed to delete reports', -1);
        }
    }

    /**
     * Returns a list of values for each filter item except for `before_time`
     *
     * @return array An key-value array, where the key is the filter item name
     *               and the value is an array of possible values for the item
     */
    public static function getFilterList(): array
    {
        $res = [];
        $db = Database::connection();
        try {
            $res['domain'] = (new DomainList())->names();
        } catch (\Exception $e) {
            throw new \Exception('Failed to get a list of domains', -1);
        }
        $rep_tn = Database::tablePrefix('reports');
        try {
            $months = [];
            $st = $db->query(
                'SELECT DISTINCT DATE_FORMAT(`date`, "%Y-%m") AS `month` FROM'
                . ' ((SELECT DISTINCT `begin_time` AS `date` FROM `' . $rep_tn
                . '`) UNION (SELECT DISTINCT `end_time` AS `date` FROM `' . $rep_tn
                . '`)) AS `r` ORDER BY `month` DESC'
            );
            while ($r = $st->fetch(\PDO::FETCH_NUM)) {
                $months[] = $r[0];
            }
            $st->closeCursor();
            $res['month'] = $months;
        } catch (\Exception $e) {
            throw new \Exception('Failed to get a list of months', -1);
        }
        try {
            $orgs = [];
            // TODO оптимизировать индексом!
            $st = $db->query('SELECT DISTINCT `org` FROM `' . $rep_tn . '` ORDER BY `org`');
            while ($r = $st->fetch(\PDO::FETCH_NUM)) {
                $orgs[] = $r[0];
            }
            $st->closeCursor();
            $res['organization'] = $orgs;
        } catch (\Exception $e) {
            throw new \Exception('Failed to get a list of organizations', -1);
        }
        $res['dkim']   = [ 'pass', 'fail' ];
        $res['spf']    = [ 'pass', 'fail' ];
        $res['status'] = [ 'read', 'unread' ];
        return $res;
    }

    /**
     * The valid filter item names
     */
    private static $filters_available = [
        'domain', 'month', 'before_time', 'organization', 'dkim', 'spf', 'status'
    ];

    /**
     * Returns the SQL condition for a filter by filter id
     *
     * @param string $prefix Prefix, which will be added to the beginning of the condition string,
     *                       but only in the case when the condition string is not empty.
     * @param int    $f_id   Index of the filter
     *
     * @return string the condition string
     */
    private function sqlCondition(string $prefix, int $f_idx): string
    {
        return isset($this->filters[$f_idx]) ? ($prefix . $this->filters[$f_idx]['str']) : '';
    }

    /**
     * Returns `ORDER BY ...` part of the SQL query
     *
     * @return string
     */
    private function sqlOrder(): string
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

    /**
     * Returns `LIMIT ...` part of the SQL query
     *
     * @return string
     */
    private function sqlLimit(): string
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

    /**
     * Binds the values of the filter and the limit to SQL query
     *
     * @param PDOStatement $st        Prepared SQL statement to bind to
     * @param int          $inc_limit Value by which the number of result rows in the limit experssion
     *                                will be increased. If $inc_limit less than 0,
     *                                limit will be ignored and will not be bond.
     *
     * @return void
     */
    private function sqlBindValues($st, int $inc_limit): void
    {
        $pos = 0;
        if (isset($this->filters[0])) {
            $this->sqlBindFilterValues($st, 0, $pos);
        }
        if (isset($this->filters[1])) {
            $this->sqlBindFilterValues($st, 1, $pos);
        }
        if (!is_null($this->limit) && $inc_limit >= 0) {
            if (!is_null($this->position)) {
                $st->bindValue(++$pos, $this->position, \PDO::PARAM_INT);
            }
            $st->bindValue(++$pos, $this->limit + $inc_limit, \PDO::PARAM_INT);
        }
    }

    /**
     * Binds the values of the specified filter item to SQL query
     *
     * @param PDOStatement $st         Prepared SQL statement to bind to
     * @param int          $filter_idx Index of the filter to bind to
     * @param int          $bind_pos   Start bind position (pointer). It will be increaded with each binding.
     *
     * @return void
     */
    private function sqlBindFilterValues($st, int $filter_idx, int &$bind_pos): void
    {
        foreach ($this->filters[$filter_idx]['bindings'] as &$bv) {
            $st->bindValue(++$bind_pos, $bv[0], $bv[1]);
        }
    }
}
