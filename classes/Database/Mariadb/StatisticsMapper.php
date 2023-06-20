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

use Liuch\DmarcSrg\Database\StatisticsMapperInterface;
use Liuch\DmarcSrg\Exception\DatabaseFatalException;

/**
 * StatisticsMapper class implementation for MariaDB
 */
class StatisticsMapper implements StatisticsMapperInterface
{
    private $connector = null;

    /**
     * The constructor
     *
     * @param Connector $connector DatabaseConnector
     */
    public function __construct(object $connector)
    {
        $this->connector = $connector;
    }

    /**
     * Returns summary information for the specified domain and date range
     *
     * @param \Liuch\DmarcSrg\Domains\Domain|null $domain Domain for which the information is needed. Null is for all domains.
     * @param array                               $range  Array with two dates
     *
     * @return array Array with Summary information:
     *                          'emails' => [
     *                              'total'            => total email processed (int)
     *                              'dkim_spf_aligned' => Both DKIM and SPF aligned (int)
     *                              'dkim_aligned'     => Only DKIM aligned (int)
     *                              'spf_aligned'      => Only SPF aligned (int)
     *                          ];
     */
    public function summary($domain, array &$range): array
    {
        $is_domain = $domain ? true : false;
        $db = $this->connector->dbh();
        try {
            $st = $db->prepare(
                'SELECT SUM(`rcount`), SUM(IF(`dkim_align` = 2 AND `spf_align` = 2, `rcount`, 0)),'
                . ' SUM(IF(`dkim_align` = 2 AND `spf_align` <> 2, `rcount`, 0)),'
                . ' SUM(IF(`dkim_align` <> 2 AND `spf_align` = 2, `rcount`, 0))'
                . ' FROM `' . $this->connector->tablePrefix('rptrecords') . '` AS `rr`'
                . ' INNER JOIN `' . $this->connector->tablePrefix('reports')
                . '` AS `rp` ON `rr`.`report_id` = `rp`.`id`'
                . $this->sqlCondition($is_domain)
            );
            $this->sqlBindValues($st, $domain, $range);
            $st->execute();
            $row = $st->fetch(\PDO::FETCH_NUM);
            $ems = [
                'total' => intval($row[0]),
                'dkim_spf_aligned' => intval($row[1]),
                'dkim_aligned' => intval($row[2]),
                'spf_aligned' => intval($row[3])
            ];
            $st->closeCursor();

            $st = $db->prepare(
                'SELECT COUNT(*) FROM (SELECT `org` FROM `' . $this->connector->tablePrefix('reports') . '`'
                . $this->sqlCondition($is_domain) . ' GROUP BY `org`) AS `orgs`'
            );
            $this->sqlBindValues($st, $domain, $range);
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
     * @param \Liuch\DmarcSrg\Domains\Domain|null $domain Domain for which the information is needed. Null is for all domains.
     * @param array                               $range  Array with two dates
     *
     * @return array A list of ip-addresses with fields `ip`, `emails`, `dkim_aligned`, `spf_aligned`
     */
    public function ips($domain, array &$range): array
    {
        try {
            $st = $this->connector->dbh()->prepare(
                'SELECT `ip`, SUM(`rcount`) AS `rcount`, SUM(IF(`dkim_align` = 2, `rcount`, 0)) AS `dkim_aligned`,'
                . ' SUM(IF(`spf_align` = 2, `rcount`, 0)) AS `spf_aligned`'
                . ' FROM `' . $this->connector->tablePrefix('rptrecords') . '` AS `rr`'
                . ' INNER JOIN `' . $this->connector->tablePrefix('reports')
                . '` AS `rp` ON `rr`.`report_id` = `rp`.`id`'
                . $this->sqlCondition($domain ? true : false) . ' GROUP BY `ip` ORDER BY `rcount` DESC'
            );
            $this->sqlBindValues($st, $domain, $range);
            $st->execute();
            $res = [];
            while ($row = $st->fetch(\PDO::FETCH_NUM)) {
                $res[] = [
                    'ip'           => inet_ntop($row[0]),
                    'emails'       => intval($row[1]),
                    'dkim_aligned' => intval($row[2]),
                    'spf_aligned'  => intval($row[3])
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
     * @param \Liuch\DmarcSrg\Domains\Domain|null $domain Domain for which the information is needed. Null is for all domains.
     * @param array                               $range  Array with two dates
     *
     * @return array List of organizations with fields `name`, `reports`, `emails`
     */
    public function organizations($domain, array &$range): array
    {
        try {
            $st = $this->connector->dbh()->prepare(
                'SELECT `org`, COUNT(*), SUM(`rr`.`rcount`) AS `rcount`'
                . ' FROM `' . $this->connector->tablePrefix('reports') . '` AS `rp`'
                . ' INNER JOIN (SELECT `report_id`, SUM(`rcount`) AS `rcount` FROM `'
                . $this->connector->tablePrefix('rptrecords')
                . '` GROUP BY `report_id`) AS `rr` ON `rp`.`id` = `rr`.`report_id`'
                . $this->sqlCondition($domain ? true : false)
                . ' GROUP BY `org` ORDER BY `rcount` DESC'
            );
            $this->sqlBindValues($st, $domain, $range);
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
     * Returns a condition string for WHERE statement
     *
     * @param bool $with_domain Is it needed to add a condition for a domain
     *
     * @return string Condition string
     */
    private function sqlCondition($with_domain): string
    {
        $res = ' WHERE ';
        if ($with_domain) {
            $res .= 'domain_id = ? AND ';
        }
        $res .= '`begin_time` < ? AND `end_time` >= ?';
        return $res;
    }

    /**
     * Binds values for SQL queries
     *
     * @param \PDOStatement                       $st     PDO Statement to bind to
     * @param \Liuch\DmarcSrg\Domains\Domain|null $domain Domain for the condition
     * @param array                               $range  Date range for the condition
     *
     * @return void
     */
    private function sqlBindValues(object $st, $domain, array &$range): void
    {
        $pnum = 0;
        if ($domain) {
            $st->bindValue(++$pnum, $domain->id(), \PDO::PARAM_INT);
        }
        $ds1 = (clone $range['date1'])->add(new \DateInterval('PT10S'))->format('Y-m-d H:i:s');
        $ds2 = (clone $range['date2'])->sub(new \DateInterval('PT10S'))->format('Y-m-d H:i:s');
        $st->bindValue(++$pnum, $ds2, \PDO::PARAM_STR);
        $st->bindValue(++$pnum, $ds1, \PDO::PARAM_STR);
    }
}
