<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2022 Aleksey Andreev (liuch)
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
 * This file contains the StatisticsMapper class
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Database\Mariadb;

use Liuch\DmarcSrg\Common;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\DatabaseFatalException;
use Liuch\DmarcSrg\Database\StatisticsMapperInterface;

/**
 * StatisticsMapper class implementation for MariaDB
 */
class StatisticsMapper implements StatisticsMapperInterface
{
    /** @var \Liuch\DmarcSrg\Database\DatabaseConnector */
    private $connector = null;

    /**
     * The constructor
     *
     * @param \Liuch\DmarcSrg\Database\DatabaseConnector $connector DatabaseConnector instance of the current database
     */
    public function __construct(object $connector)
    {
        $this->connector = $connector;
    }

    /**
     * Returns summary information for the specified domain and date range
     *
     * @param \Liuch\DmarcSrg\Domains\Domain|null $domain Domain for which the information is needed.
     *                                                    Null is for all domains.
     * @param array                               $range  Array with two dates
     * @param array                               $filter Array with filtering parameters
     *
     * @return array Array with Summary information:
     *                          'emails' => [
     *                              'total'            => total email processed (int)
     *                              'dkim_spf_aligned' => Both DKIM and SPF aligned (int)
     *                              'dkim_aligned'     => Only DKIM aligned (int)
     *                              'spf_aligned'      => Only SPF aligned (int)
     *                              'quarantined'      => Quarantined (int)
     *                              'rejected'         => Rejected (int)
     *                          ];
     */
    public function summary($domain, array &$range, array &$filter): array
    {
        $is_domain = $domain ? true : false;
        $db = $this->connector->dbh();
        try {
            $f_data = $this->prepareFilterData($domain, $range, $filter);
            $st = $db->prepare(
                'SELECT SUM(`rcount`), SUM(IF(`dkim_align` = 2 AND `spf_align` = 2, `rcount`, 0)),'
                . ' SUM(IF(`dkim_align` = 2 AND `spf_align` <> 2, `rcount`, 0)),'
                . ' SUM(IF(`dkim_align` <> 2 AND `spf_align` = 2, `rcount`, 0)),'
                . ' SUM(IF(`disposition` = 0, `rcount`, 0)),'
                . ' SUM(IF(`disposition` = 1, `rcount`, 0))'
                . ' FROM `' . $this->connector->tablePrefix('rptrecords') . '` AS `rr`'
                . ' INNER JOIN `' . $this->connector->tablePrefix('reports')
                . '` AS `rp` ON `rr`.`report_id` = `rp`.`id`'
                . $this->sqlCondition($f_data, ' WHERE ', 0) . $this->sqlCondition($f_data, ' AND ', 1)
            );
            $this->sqlBindValues($st, $f_data, [ 0, 1 ]);
            $st->execute();
            $row = $st->fetch(\PDO::FETCH_NUM);
            $ems = [
                'total' => intval($row[0]),
                'dkim_spf_aligned' => intval($row[1]),
                'dkim_aligned'     => intval($row[2]),
                'spf_aligned'      => intval($row[3]),
                'rejected'         => intval($row[4]),
                'quarantined'      => intval($row[5])
            ];
            $st->closeCursor();

            if (!isset($filter['dkim']) && !isset($filter['spf']) && !isset($filter['disposition'])) {
                $st = $db->prepare(
                    'SELECT COUNT(*) FROM (SELECT `org` FROM `' . $this->connector->tablePrefix('reports') . '`'
                    . $this->sqlCondition($f_data, ' WHERE ', 0) . ' GROUP BY `org`) AS `orgs`'
                );
            } else {
                $st = $db->prepare(
                    'SELECT COUNT(*) FROM (SELECT `org` FROM ('
                    . 'SELECT `org`, MIN(`dkim_align`) as `dkim_align`, MIN(`spf_align`) AS `spf_align`,'
                    . ' MIN(`disposition`) AS `disposition`'
                    . ' FROM `' . $this->connector->tablePrefix('reports') . '` AS `rp`'
                    . ' INNER JOIN `' . $this->connector->tablePrefix('rptrecords') . '` AS `rr`'
                    . ' ON `rp`.`id` = `rr`.`report_id`' . $this->sqlCondition($f_data, ' WHERE ', 0)
                    . ' GROUP BY `rr`.`report_id`'
                    . ') AS `sr`' . $this->sqlCondition($f_data, ' WHERE ', 1) . ' GROUP BY `org`) AS `orgs`'
                );
            }

            $this->sqlBindValues($st, $f_data, [ 0, 1 ]);
            $st->execute();
            $row = $st->fetch(\PDO::FETCH_NUM);
            $st->closeCursor();
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to get summary information', -1, $e);
        }

        return [
            'emails' => $ems,
            'organizations' => intval($row[0])
        ];
    }

    /**
     * Returns a list of ip-addresses from which the e-mail messages were received, with some statistics for each one
     *
     * @param \Liuch\DmarcSrg\Domains\Domain|null $domain Domain for which the information is needed.
     *                                                    Null is for all domains.
     * @param array                               $range  Array with two dates
     * @param array                               $filter Array with filtering parameters
     *
     * @return array A list of ip-addresses with fields `ip`, `emails`, `dkim_aligned`, `spf_aligned`
     */
    public function ips($domain, array &$range, array &$filter): array
    {
        try {
            $f_data = $this->prepareFilterData($domain, $range, $filter);
            $st = $this->connector->dbh()->prepare(
                'SELECT `ip`, SUM(`rcount`) AS `rcount`, SUM(IF(`dkim_align` = 2, `rcount`, 0)) AS `dkim_aligned`,'
                . ' SUM(IF(`spf_align` = 2, `rcount`, 0)) AS `spf_aligned`,'
                . ' SUM(IF(`disposition` = 0, `rcount`, 0)),'
                . ' SUM(IF(`disposition` = 1, `rcount`, 0))'
                . ' FROM `' . $this->connector->tablePrefix('rptrecords') . '` AS `rr`'
                . ' INNER JOIN `' . $this->connector->tablePrefix('reports')
                . '` AS `rp` ON `rr`.`report_id` = `rp`.`id`'
                . $this->sqlCondition($f_data, ' WHERE ', 0) . $this->sqlCondition($f_data, ' AND ', 1)
                . ' GROUP BY `ip` ORDER BY `rcount` DESC'
            );
            $this->sqlBindValues($st, $f_data, [ 0, 1 ]);
            $st->execute();
            $res = [];
            while ($row = $st->fetch(\PDO::FETCH_NUM)) {
                $res[] = [
                    'ip'           => inet_ntop($row[0]),
                    'emails'       => intval($row[1]),
                    'dkim_aligned' => intval($row[2]),
                    'spf_aligned'  => intval($row[3]),
                    'rejected'     => intval($row[4]),
                    'quarantined'  => intval($row[5])
                ];
            }
            $st->closeCursor();
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to get IPs summary information', -1, $e);
        }
        return $res;
    }

    /**
     * Returns a list of organizations that sent the reports with some statistics for each one
     *
     * @param \Liuch\DmarcSrg\Domains\Domain|null $domain Domain for which the information is needed.
     *                                                    Null is for all domains.
     * @param array                               $range  Array with two dates
     * @param array                               $filter Array with filtering parameters
     *
     * @return array List of organizations with fields `name`, `reports`, `emails`
     */
    public function organizations($domain, array &$range, array &$filter): array
    {
        try {
            $f_data = $this->prepareFilterData($domain, $range, $filter);
            $st = $this->connector->dbh()->prepare(
                'SELECT `org`, COUNT(*), SUM(`rr`.`rcount`) AS `rcount`'
                . ' FROM `' . $this->connector->tablePrefix('reports') . '` AS `rp`'
                . ' INNER JOIN (SELECT `report_id`, SUM(`rcount`) AS `rcount` FROM `'
                . $this->connector->tablePrefix('rptrecords') . '`'
                . $this->sqlCondition($f_data, ' WHERE ', 1)
                . ' GROUP BY `report_id`) AS `rr` ON `rp`.`id` = `rr`.`report_id`'
                . $this->sqlCondition($f_data, ' WHERE ', 0)
                . ' GROUP BY `org` ORDER BY `rcount` DESC'
            );
            $this->sqlBindValues($st, $f_data, [ 1, 0 ]);
            $st->execute();
            $res = [];
            while ($row = $st->fetch(\PDO::FETCH_NUM)) {
                $res[] = [
                    'name'    => $row[0],
                    'reports' => intval($row[1]),
                    'emails'  => intval($row[2])
                ];
            }
            $st->closeCursor();
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to get summary information of reporting organizations', -1, $e);
        }
        return $res;
    }

    /**
     * Valid filter item names
     */
    private static $filters_available = [
        'organization', 'dkim', 'spf', 'disposition', 'status'
    ];

    /**
     * Returns prepared filter data for SQL queries
     *
     * @param \Liuch\DmarcSrg\Domains\Domain|null $domain Domain for the condition
     * @param array                               $range  Date range
     * @param array                               $filter Key-value array with filtering parameters
     *
     * @return array
     */
    private function prepareFilterData($domain, array &$range, array &$filter): array
    {
        $sql_cond1 = [];
        $sql_cond2 = [];
        $bindings1 = [];
        $bindings2 = [];
        if ($domain) {
            $sql_cond1[] = '`domain_id` = ?';
            $bindings1[] = [ $domain->id(), \PDO::PARAM_INT ];
        }
        $sql_cond1[] = '`begin_time` < ? AND `end_time` >= ?';
        $bindings1[] = [
            (clone $range['date2'])->sub(new \DateInterval('PT10S'))->format('Y-m-d H:i:s'), \PDO::PARAM_STR
        ];
        $bindings1[] = [
            (clone $range['date1'])->add(new \DateInterval('PT10S'))->format('Y-m-d H:i:s'), \PDO::PARAM_STR
        ];
        foreach (self::$filters_available as $fname) {
            if (empty($filter[$fname])) {
                continue;
            }
            $fvalue = $filter[$fname];
            switch ($fname) {
                case 'organization':
                    $sql_cond1[] = '`org` = ?';
                    $bindings1[] = [ $fvalue, \PDO::PARAM_STR ];
                    break;
                case 'dkim':
                    if ($fvalue === Common::$align_res[0]) {
                        $val = 0;
                    } else {
                        $val = count(Common::$align_res) - 1;
                        if ($fvalue !== Common::$align_res[$val]) {
                            throw new SoftException('Filter: Incorrect DKIM value');
                        }
                    }
                    $sql_cond2[] = '`dkim_align` = ?';
                    $bindings2[] = [ $val, \PDO::PARAM_INT ];
                    break;
                case 'spf':
                    if ($fvalue === Common::$align_res[0]) {
                        $val = 0;
                    } else {
                        $val = count(Common::$align_res) - 1;
                        if ($fvalue !== Common::$align_res[$val]) {
                            throw new SoftException('Filter: Incorrect SPF value');
                        }
                    }
                    $sql_cond2[] = '`spf_align` = ?';
                    $bindings2[] = [ $val, \PDO::PARAM_INT ];
                    break;
                case 'disposition':
                    $val = array_search($fvalue, Common::$disposition);
                    if ($val === false) {
                        throw new SoftException('Filter: Incorrect value of disposition');
                    }
                    $sql_cond2[] = '`disposition` = ?';
                    $bindings2[] = [ $val, \PDO::PARAM_INT ];
                    break;
                case 'status':
                    if ($fvalue === 'read') {
                        $val = true;
                    } elseif ($fvalue === 'unread') {
                        $val = false;
                    } else {
                        throw new SoftException('Filter: Incorrect status value');
                    }
                    $sql_cond1[] = '`seen` = ?';
                    $bindings1[] = [ $val, \PDO::PARAM_BOOL ];
                    break;
            }
        }
        return [
            'sql_cond' => [ $sql_cond1, $sql_cond2 ],
            'bindings' => [ $bindings1, $bindings2 ]
        ];
    }

    /**
     * Returns a condition string for WHERE statement
     *
     * @param array  $f_data  Array with prepared filter data
     * @param string $prefix  Prefix, which will be added to the beginning of the condition string,
     *                        but only in the case when the condition string is not empty.
     * @param int    $num     Part number
     *
     * @return string Condition string
     */
    private function sqlCondition(array &$f_data, string $prefix, int $num): string
    {
        if (count($f_data['sql_cond'][$num]) > 0) {
            return $prefix . implode(' AND ', $f_data['sql_cond'][$num]);
        }
        return '';
    }

    /**
     * Binds values for SQL queries
     *
     * @param \PDOStatement $st     PDO Statement to bind to
     * @param array         $f_data Array with prepared filter data
     * @param array         $order  Parameter binding order
     *
     * @return void
     */
    private function sqlBindValues(object $st, array &$f_data, array $order): void
    {
        $pnum = 0;
        foreach ($order as $idx) {
            foreach ($f_data['bindings'][$idx] as &$it) {
                $st->bindValue(++$pnum, $it[0], $it[1]);
            }
            unset($it);
        }
    }
}
