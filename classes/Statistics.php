<?php

/**
 * dmarc-srg - a php parser, viewer and summary report generator for incoming dmarc reports.
 * copyright (c) 2020 aleksey andreev (liuch)
 *
 * available at:
 * https://github.com/liuch/dmarc-srg
 *
 * this program is free software: you can redistribute it and/or modify it
 * under the terms of the gnu general public license as published by the free
 * software foundation, either version 3 of the license.
 *
 * this program is distributed in the hope that it will be useful, but without
 * any warranty; without even the implied warranty of  merchantability or
 * fitness for a particular purpose. see the gnu general public license for
 * more details.
 *
 * you should have received a copy of the gnu general public license along with
 * this program.  if not, see <http://www.gnu.org/licenses/>.
 *
 * =========================
 *
 * This file contains the class Statistics
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\DateTime;
use Liuch\DmarcSrg\Database\Database;
use Liuch\DmarcSrg\Exception\DatabaseException;

/**
 * This class is designed to get statistics on DMARC reports of a specified period
 */
class Statistics
{
    private $domain = null;
    private $date1  = null;
    private $date2  = null;

    /**
     * The constructor of the class, it only uses in static methods of this class
     *
     * @param Domain|null $domain The domain for which you need to get statistics, null for all the domains.
     *
     * @return void
     */
    private function __construct($domain)
    {
        $this->domain = $domain;
    }

    /**
     * Returns an instance of the class for the period from $date1 to $date2
     *
     * @param Domain|null $domain See the constructor for the details
     * @param DateTime    $date1  The date you need statistics from
     * @param DateTime    $date2  The date you need statistics to (not included)
     *
     * @return Statistics Instance of the class
     */
    public static function fromTo($domain, int $date1, int $date2)
    {
        $r = new Statistics($domain);
        $r->date1 = $date1;
        $r->date2 = $date2;
        return $r;
    }

    /**
     * Returns an instance of the class for the last week
     *
     * @param Domain|null $domain See the constructor for the details
     *
     * @return Statistics Instance of the class
     */
    public static function lastWeek($domain)
    {
        $r = new Statistics($domain);
        $r->date1 = new DateTime('monday last week');
        $r->date2 = (clone $r->date1)->add(new \DateInterval('P7D'));
        return $r;
    }

    /**
     * Returns an instance of the class for the last month
     *
     * @param Domain|null $domain See the construct for the details
     *
     * @return Statistics Instance of the class
     */
    public static function lastMonth($domain)
    {
        $r = new Statistics($domain);
        $r->date1 = new DateTime('midnight first day of last month');
        $r->date2 = new DateTime('midnight first day of this month');
        return $r;
    }

    /**
     * Returns an instance of the class for the last N days
     *
     * @param Domain|null $domain See the construct for the details
     * @param int         $ndays  Number of days
     *
     * @return Statistics Instance of the class
     */
    public static function lastNDays($domain, int $ndays)
    {
        $r = new Statistics($domain);
        $r->date2 = new DateTime('midnight');
        $r->date1 = (clone $r->date2)->sub(new \DateInterval("P{$ndays}D"));
        return $r;
    }

    /**
     * Returns the date from and the date to in an array
     *
     * @return array - The range of the statistics
     */
    public function range(): array
    {
        return [ (clone $this->date1), (clone $this->date2)->sub(new \DateInterval('PT1S')) ];
    }

    /**
     * Returns summary information for e-mail messages as an array
     *
     * @return array An array of summary information with fields
     *               `total`, `dkim_spf_aligned`, `dkim_aligned`, `spf_aligned`
     */
    public function summary(): array
    {
        $st = null;
        try {
            $st = Database::connection()->prepare(
                'SELECT SUM(`rcount`), SUM(IF(`dkim_align` = 2 AND `spf_align` = 2, `rcount`, 0)),'
                . ' SUM(IF(`dkim_align` = 2 AND `spf_align` <> 2, `rcount`, 0)),'
                . ' SUM(IF(`dkim_align` <> 2 AND `spf_align` = 2, `rcount`, 0))'
                . ' FROM `' . Database::tablePrefix('rptrecords') . '` AS `rr`'
                . ' INNER JOIN `' . Database::tablePrefix('reports') . '` AS `rp` ON `rr`.`report_id` = `rp`.`id`'
                . ' WHERE ' . $this->sqlCondition()
            );
            $this->sqlBindValues($st);
            $st->execute();
            $row = $st->fetch(\PDO::FETCH_NUM);
            $ems = [
                'total' => intval($row[0]),
                'dkim_spf_aligned' => intval($row[1]),
                'dkim_aligned' => intval($row[2]),
                'spf_aligned' => intval($row[3])
            ];
            $st->closeCursor();

            $st = Database::connection()->prepare(
                'SELECT COUNT(*) FROM (SELECT `org` FROM `' . Database::tablePrefix('reports') . '` WHERE '
                . $this->sqlCondition() . ' GROUP BY `org`) AS `orgs`'
            );
            $this->sqlBindValues($st);
            $st->execute();
            $row = $st->fetch(\PDO::FETCH_NUM);
            $st->closeCursor();
        } catch (\PDOException $e) {
            throw new DatabaseException('Failed to get emails summary information', -1, $e);
        }

        return [
            'emails' => $ems,
            'organizations' => intval($row[0])
        ];
    }

    /**
     * Returns a list of ip-addresses from which the e-mail messages were received, with some statistics for each one
     *
     * @return array A list of ip-addresses with fields
     *               `ip`, `emails`, `dkim_aligned`, `spf_aligned`
     */
    public function ips(): array
    {
        try {
            $st = Database::connection()->prepare(
                'SELECT `ip`, SUM(`rcount`) AS `rcount`, SUM(IF(`dkim_align` = 2, `rcount`, 0)) AS `dkim_aligned`,'
                . ' SUM(IF(`spf_align` = 2, `rcount`, 0)) AS `spf_aligned`'
                . ' FROM `' . Database::tablePrefix('rptrecords') . '` AS `rr`'
                . ' INNER JOIN `' . Database::tablePrefix('reports') . '` AS `rp` ON `rr`.`report_id` = `rp`.`id`'
                . ' WHERE ' . $this->sqlCondition() . ' GROUP BY `ip` ORDER BY `rcount` DESC'
            );
            $this->sqlBindValues($st);
            $st->execute();
            $res = [];
            while ($row = $st->fetch(\PDO::FETCH_NUM)) {
                $res[] = [
                    'ip' => inet_ntop($row[0]),
                    'emails' => intval($row[1]),
                    'dkim_aligned' => intval($row[2]),
                    'spf_aligned' => intval($row[3])
                ];
            }
            $st->closeCursor();
        } catch (\PDOException $e) {
            throw new DatabaseException('Failed to get IPs summary information', -1, $e);
        }
        return $res;
    }

    /**
     * Returns a list of organizations that sent the reports with some statistics for each one
     *
     * @return array A list of organizations with fields
     *               `name`, `reports`, `emails`
     */
    public function organizations(): array
    {
        try {
            $st = Database::connection()->prepare(
                'SELECT `org`, COUNT(*), SUM(`rr`.`rcount`) AS `rcount`'
                . ' FROM `' . Database::tablePrefix('reports') . '` AS `rp`'
                . ' INNER JOIN (SELECT `report_id`, SUM(`rcount`) AS `rcount` FROM `'
                . Database::tablePrefix('rptrecords')
                . '` GROUP BY `report_id`) AS `rr` ON `rp`.`id` = `rr`.`report_id` WHERE '
                . $this->sqlCondition()
                . ' GROUP BY `org` ORDER BY `rcount` DESC'
            );
            $this->sqlBindValues($st);
            $st->execute();
            $res = [];
            while ($row = $st->fetch(\PDO::FETCH_NUM)) {
                $res[] = [
                    'name' => $row[0],
                    'reports' => intval($row[1]),
                    'emails' => intval($row[2])
                ];
            }
            $st->closeCursor();
        } catch (\PDOException $e) {
            throw new DatabaseException('Failed to get summary information about organizations', -1, $e);
        }
        return $res;
    }

    /**
     * Returns a condition string for a WHERE statement
     *
     * @return string Condition string
     */
    private function sqlCondition(): string
    {
        $res = '';
        if ($this->domain) {
            $res = 'domain_id = ? AND ';
        }
        $res .= '`begin_time` < ? AND `end_time` >= ?';
        return $res;
    }

    /**
     * Binds values for SQL queries
     *
     * @param PDOStatement $st PDO Statement to bind to
     *
     * @return void
     */
    private function sqlBindValues(object $st): void
    {
        $pnum = 0;
        if ($this->domain) {
            $st->bindValue(++$pnum, $this->domain->id(), \PDO::PARAM_INT);
        }
        $ds1 = (clone $this->date1)->add(new \DateInterval('PT10S'))->format('Y-m-d H:i:s');
        $ds2 = (clone $this->date2)->sub(new \DateInterval('PT10S'))->format('Y-m-d H:i:s');
        $st->bindValue(++$pnum, $ds2, \PDO::PARAM_STR);
        $st->bindValue(++$pnum, $ds1, \PDO::PARAM_STR);
    }
}
