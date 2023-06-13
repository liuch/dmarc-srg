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
 * This file contains the ReportMapper class
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Database\Mariadb;

use Liuch\DmarcSrg\Core;
use Liuch\DmarcSrg\Common;
use Liuch\DmarcSrg\DateTime;
use Liuch\DmarcSrg\Settings\SettingsList;
use Liuch\DmarcSrg\Database\ReportMapperInterface;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\LogicException;
use Liuch\DmarcSrg\Exception\DatabaseFatalException;
use Liuch\DmarcSrg\Exception\DatabaseNotFoundException;

/**
 * ReportMapper class implementation for MariaDB
 */
class ReportMapper implements ReportMapperInterface
{
    private $connector = null;

    private static $allowed_domains = null;

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
     * Fetches report data from the database and stores it in the passed array
     *
     * @param array $data Array with report data. To identify the report,
     *                    the array must contain at least two fields:
     *                    `report_id` - External report id from the xml file
     *                    `domain`    - Fully Qualified Domain Name without a trailing dot
     *
     * @return void
     */
    public function fetch(array &$data): void
    {
        $db = $this->connector->dbh();
        try {
            $st = $db->prepare(
                'SELECT `rp`.`id`, `begin_time`, `end_time`, `loaded_time`, `org`, `email`, `extra_contact_info`,'
                . ' `error_string`, `policy_adkim`, `policy_aspf`, `policy_p`, `policy_sp`, `policy_np`,'
                . ' `policy_pct`, `policy_fo`'
                . ' FROM `' . $this->connector->tablePrefix('reports') . '` AS `rp`'
                . ' INNER JOIN `' . $this->connector->tablePrefix('domains')
                    . '` AS `dom` ON `dom`.`id` = `rp`.`domain_id`'
                . ' WHERE `fqdn` = ? AND `external_id` = ?'
            );
            $st->bindValue(1, $data['domain'], \PDO::PARAM_STR);
            $st->bindValue(2, $data['report_id'], \PDO::PARAM_STR);
            $st->execute();
            if (!($res = $st->fetch(\PDO::FETCH_NUM))) {
                throw new DatabaseNotFoundException('The report is not found');
            }
            $id = intval($res[0]);
            $data['date'] = [
                'begin' => new DateTime($res[1]),
                'end'   => new DateTime($res[2])
            ];
            $data['loaded_time']  = new DateTime($res[3]);
            $data['org_name']     = $res[4];
            $data['email']        = $res[5];
            $data['extra_contact_info'] = $res[6];
            $data['error_string'] = json_decode($res[7] ?? '', true);
            $data['policy']       = [
                'adkim' => $res[8],
                'aspf'  => $res[9],
                'p'     => $res[10],
                'sp'    => $res[11],
                'np'    => $res[12],
                'pct'   => $res[13],
                'fo'    => $res[14]
            ];

            $order_str = $this->sqlOrderRecords();
            $st = $db->prepare(
                'SELECT `report_id`, `ip`, `rcount`, `disposition`, `reason`, `dkim_auth` , `spf_auth`, `dkim_align`,'
                . ' `spf_align`, `envelope_to`, `envelope_from`, `header_from`'
                . ' FROM `' . $this->connector->tablePrefix('rptrecords') . '` WHERE `report_id` = ?' . $order_str
            );
            $st->bindValue(1, $id, \PDO::PARAM_INT);
            $st->execute();
            $data['records'] = [];
            while ($res = $st->fetch(\PDO::FETCH_NUM)) {
                $data['records'][] = [
                    'ip'            => inet_ntop($res[1]),
                    'count'         => intval($res[2]),
                    'disposition'   => Common::$disposition[$res[3]],
                    'reason'        => json_decode($res[4] ?? '', true),
                    'dkim_auth'     => json_decode($res[5] ?? '', true),
                    'spf_auth'      => json_decode($res[6] ?? '', true),
                    'dkim_align'    => Common::$align_res[$res[7]],
                    'spf_align'     => Common::$align_res[$res[8]],
                    'envelope_to'   => $res[9],
                    'envelope_from' => $res[10],
                    'header_from'   => $res[11]
                ];
            }
            $st->closeCursor();
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to get the report from DB', -1, $e);
        }
    }

    /**
     * Inserts report data into the database.
     *
     * @param array $data Report data
     *
     * @return void
     */
    public function save(array &$data): void
    {
        $db = $this->connector->dbh();
        $db->beginTransaction();
        try {
            $domain_data = [ 'fqdn' => strtolower($data['domain']) ];
            $domain_mapper = $this->connector->getMapper('domain');
            try {
                $domain_mapper->fetch($domain_data);
                if (!$domain_data['active']) {
                    throw new SoftException('Failed to add an incoming report: the domain is inactive');
                }
            } catch (DatabaseNotFoundException $e) {
                // The domain is not found. Let's try to add it automatically.
                $this->insertDomain($domain_data, $domain_mapper);
            }

            $ct = new DateTime();
            $st = $db->prepare(
                'INSERT INTO `' . $this->connector->tablePrefix('reports')
                . '` (`domain_id`, `begin_time`, `end_time`, `loaded_time`, `org`, `external_id`, `email`,'
                . ' `extra_contact_info`, `error_string`, `policy_adkim`, `policy_aspf`, `policy_p`,'
                . ' `policy_sp`, `policy_np`, `policy_pct`, `policy_fo`, `seen`)'
                . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)'
            );
            $st->bindValue(1, $domain_data['id'], \PDO::PARAM_INT);
            $st->bindValue(2, $data['begin_time']->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
            $st->bindValue(3, $data['end_time']->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
            $st->bindValue(4, $ct->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
            $st->bindValue(5, $data['org'], \PDO::PARAM_STR);
            $st->bindValue(6, $data['external_id'], \PDO::PARAM_STR);
            $st->bindValue(7, $data['email'], \PDO::PARAM_STR);
            $st->bindValue(8, $data['extra_contact_info'], \PDO::PARAM_STR);
            self::sqlBindJson($st, 9, $data['error_string']);
            $st->bindValue(10, $data['policy_adkim'], \PDO::PARAM_STR);
            $st->bindValue(11, $data['policy_aspf'], \PDO::PARAM_STR);
            $st->bindValue(12, $data['policy_p'], \PDO::PARAM_STR);
            $st->bindValue(13, $data['policy_sp'], \PDO::PARAM_STR);
            $st->bindValue(14, $data['policy_np'], \PDO::PARAM_STR);
            $st->bindValue(15, $data['policy_pct'], \PDO::PARAM_STR);
            $st->bindValue(16, $data['policy_fo'], \PDO::PARAM_STR);
            $st->execute();
            $new_id = intval($db->lastInsertId());
            $st->closeCursor();

            $st = $db->prepare(
                'INSERT INTO `' . $this->connector->tablePrefix('rptrecords')
                . '` (`report_id`, `ip`, `rcount`, `disposition`, `reason`, `dkim_auth`, `spf_auth`, `dkim_align`,'
                . ' `spf_align`, `envelope_to`, `envelope_from`, `header_from`)'
                . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($data['records'] as &$rec_data) {
                $st->bindValue(1, $new_id, \PDO::PARAM_INT);
                $st->bindValue(2, inet_pton($rec_data['ip']), \PDO::PARAM_STR);
                $st->bindValue(3, $rec_data['rcount'], \PDO::PARAM_INT);
                $st->bindValue(4, array_search($rec_data['disposition'], Common::$disposition), \PDO::PARAM_INT);
                self::sqlBindJson($st, 5, $rec_data['reason']);
                self::sqlBindJson($st, 6, $rec_data['dkim_auth']);
                self::sqlBindJson($st, 7, $rec_data['spf_auth']);
                $st->bindValue(8, array_search($rec_data['dkim_align'], Common::$align_res), \PDO::PARAM_INT);
                $st->bindValue(9, array_search($rec_data['spf_align'], Common::$align_res), \PDO::PARAM_INT);
                $st->bindValue(10, $rec_data['envelope_to'], \PDO::PARAM_STR);
                $st->bindValue(11, $rec_data['envelope_from'], \PDO::PARAM_STR);
                $st->bindValue(12, $rec_data['header_from'], \PDO::PARAM_STR);
                $st->execute();
            }
            unset($rec_data);
            $db->commit();
            $data['loaded_time'] = $ct;
        } catch (\PDOException $e) {
            $db->rollBack();
            if ($e->getCode() == '23000') {
                throw new SoftException('This report has already been loaded');
            }
            throw new DatabaseFatalException('Failed to insert the report', -1, $e);
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Sets report record property in database.
     *
     * It has nothing to do with the fields of the report itself.
     *
     * @param array   $data  Report data
     * @param string  $name  Property name. Currently only `seen` is supported.
     * @param variant $value Property value
     *
     * @return void
     */
    public function setProperty(array &$data, string $name, $value): void
    {
        if ($name !== 'seen' && gettype($value) !== 'boolean') {
            throw new LogicException('Incorrect parameters');
        }

        try {
            $st = $this->connector->dbh()->prepare(
                'UPDATE `' . $this->connector->tablePrefix('reports') . '` AS `rp`'
                . ' INNER JOIN `' . $this->connector->tablePrefix('domains') . '` AS `dom`'
                . ' ON `rp`.`domain_id` = `dom`.`id` SET `seen` = ? WHERE `fqdn` = ? AND `external_id` = ?'
            );
            $st->bindValue(1, $value, \PDO::PARAM_BOOL);
            $st->bindValue(2, $data['domain'], \PDO::PARAM_STR);
            $st->bindValue(3, $data['report_id'], \PDO::PARAM_STR);
            $st->execute();
            $st->closeCursor();
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to update the DB record', -1, $e);
        }
    }

    /**
     * Returns a list of reports with specified parameters
     *
     * This method returns a list of reports that depends on the $filter, $order and $limit.
     *
     * @param array $filter Key-value array with filtering parameters
     * @param array $order  Key-value array:
     *                      'field'     => string, 'begin_time'
     *                      'direction' => string, 'ascent' or 'descent'
     * @param array $limit  Key-value array with two keys: `offset` and `count`
     *
     * @return array
     */
    public function list(array &$filter, array &$order, array &$limit): array
    {
        $db = $this->connector->dbh();
        $list = [];
        $f_data = $this->prepareFilterData($filter);
        $order_str = $this->sqlOrderList($order);
        $cond_str0 = $this->sqlConditionList($f_data, ' AND ', 0);
        $cond_str1 = $this->sqlConditionList($f_data, ' HAVING ', 1);
        $limit_str = $this->sqlLimit($limit);
        try {
            $st = $db->prepare(
                'SELECT `org`, `begin_time`, `end_time`, `fqdn`, external_id, `seen`, SUM(`rcount`) AS `rcount`,'
                . ' MIN(`dkim_align`) AS `dkim_align`, MIN(`spf_align`) AS `spf_align`,'
                . ' MIN(`disposition`) AS `disposition` FROM `' . $this->connector->tablePrefix('rptrecords')
                . '` AS `rr` RIGHT JOIN (SELECT `rp`.`id`, `org`, `begin_time`, `end_time`, `external_id`,'
                . ' `fqdn`, `seen` FROM `' . $this->connector->tablePrefix('reports')
                . '` AS `rp` INNER JOIN `' . $this->connector->tablePrefix('domains')
                . '` AS `d` ON `d`.`id` = `rp`.`domain_id`' . $cond_str0 . $order_str
                . ') AS `rp` ON `rp`.`id` = `rr`.`report_id` GROUP BY `rp`.`id`'
                . $cond_str1 . $order_str . $limit_str
            );
            $this->sqlBindValues($st, $f_data, $limit);
            $st->execute();
            while ($row = $st->fetch(\PDO::FETCH_NUM)) {
                $list[] = [
                    'org_name'    => $row[0],
                    'date'        => [
                        'begin' => new DateTime($row[1]),
                        'end'   => new DateTime($row[2])
                    ],
                    'domain'      => $row[3],
                    'report_id'   => $row[4],
                    'seen'        => (bool) $row[5],
                    'messages'    => $row[6],
                    'dkim_align'  => Common::$align_res[$row[7]],
                    'spf_align'   => Common::$align_res[$row[8]],
                    'disposition' => Common::$disposition[$row[9]]
                ];
            }
            $st->closeCursor();
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to get the report list', -1, $e);
        }
        return $list;
    }

    /**
     * Returns the number of reports matching the specified filter and limits
     *
     * @param array $filter Key-value array with filtering parameters
     * @param array $limit  Key-value array with two keys: `offset` and `count`
     *
     * @return int
     */
    public function count(array &$filter, array &$limit): int
    {
        $cnt = 0;
        $f_data = $this->prepareFilterData($filter);
        try {
            $st = $this->connector->dbh()->prepare(
                'SELECT COUNT(*) FROM `' . $this->connector->tablePrefix('reports') . '` AS `rp`'
                . $this->sqlConditionList($f_data, ' WHERE ', 0)
            );
            $l_empty = [ 'offset' => 0, 'count' => 0 ];
            $this->sqlBindValues($st, $f_data, $l_empty);
            $st->execute();
            $cnt = intval($st->fetch(\PDO::FETCH_NUM)[0]);
            $st->closeCursor();

            $offset = $limit['offset'];
            if ($offset > 0) {
                $cnt -= $offset;
                if ($cnt < 0) {
                    $cnt = 0;
                }
            }
            $max = $limit['count'];
            if ($max > 0 && $max < $cnt) {
                $cnt = $max;
            }
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to get the number of reports', -1, $e);
        }
        return $cnt;
    }

    /**
     * Deletes reports from the database
     *
     * It deletes repors form the database. The filter options `dkim` and `spf` do not affect this.
     *
     * @param array $filter Key-value array with filtering parameters
     * @param array $order  Key-value array:
     *                      'field'     => string, 'begin_time'
     *                      'direction' => string, 'ascent' or 'descent'
     * @param array $limit  Key-value array with two keys: `offset` and `count`
     *
     * @return void
     */
    public function delete(array &$filter, array &$order, array &$limit): void
    {
        $f_data = $this->prepareFilterData($filter);
        $cond_str = $this->sqlConditionList($f_data, ' WHERE ', 0);
        $order_str = $this->sqlOrderList($order);
        $limit_str = $this->sqlLimit($limit);
        $db = $this->connector->dbh();
        $db->beginTransaction();
        try {
            $st = $db->prepare(
                'DELETE `rr` FROM `' . $this->connector->tablePrefix('rptrecords')
                . '` AS `rr` INNER JOIN (SELECT `id` FROM `' . $this->connector->tablePrefix('reports') . '`'
                . $cond_str . $order_str . $limit_str . ') AS `rp` ON `rp`.`id` = `rr`.`report_id`'
            );
            $this->sqlBindValues($st, $f_data, $limit);
            $st->execute();
            $st->closeCursor();

            $st = $db->prepare(
                'DELETE FROM `' . $this->connector->tablePrefix('reports') . "`{$cond_str}{$order_str}{$limit_str}"
            );
            $this->sqlBindValues($st, $f_data, $limit);
            $st->execute();
            $st->closeCursor();

            $db->commit();
        } catch (\PDOException $e) {
            $db->rollBack();
            throw new DatabaseFatalException('Failed to delete reports', -1, $e);
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Returns a list of months with years of the form: 'yyyy-mm' for which there is at least one report
     *
     * @return array
     */
    public function months(): array
    {
        $res = [];
        $rep_tn = $this->connector->tablePrefix('reports');
        try {
            $st = $this->connector->dbh()->query(
                'SELECT DISTINCT DATE_FORMAT(`date`, "%Y-%m") AS `month` FROM'
                . ' ((SELECT DISTINCT `begin_time` AS `date` FROM `' . $rep_tn
                . '`) UNION (SELECT DISTINCT `end_time` AS `date` FROM `' . $rep_tn
                . '`)) AS `r` ORDER BY `month` DESC'
            );
            while ($row = $st->fetch(\PDO::FETCH_NUM)) {
                $res[] = $row[0];
            }
            $st->closeCursor();
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to get a list of months', -1, $e);
        }
        return $res;
    }

    /**
     * Returns a list of reporting organizations from which there is at least one report
     *
     * @return array
     */
    public function organizations(): array
    {
        $res = [];
        $rep_tn = $this->connector->tablePrefix('reports');
        try {
            $st = $this->connector->dbh()->query(
                'SELECT DISTINCT `org` FROM `' . $rep_tn . '` ORDER BY `org`'
            );
            while ($row = $st->fetch(\PDO::FETCH_NUM)) {
                $res[] = $row[0];
            }
            $st->closeCursor();
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to get a list of organizations', -1, $e);
        }
        return $res;
    }

    /**
     * Returns `ORDER BY ...` part of the SQL query for report records
     *
     * @return string
     */
    private function sqlOrderRecords(): string
    {
        $o_set = explode(',', SettingsList::getSettingByName('report-view.sort-records-by')->value());
        switch ($o_set[0]) {
            case 'ip':
                $fname = 'ip';
                break;
            case 'message-count':
            default:
                $fname = 'rcount';
                break;
        }
        $dir = $o_set[1] === 'descent' ? 'DESC' : 'ASC';

        return " ORDER BY `{$fname}` {$dir}";
    }

    /**
     * Checks if the domain exists and adds it to the database if necessary
     *
     * It automatically adds the domain if there are no domains in the database
     * or if the domain match the `allowed_domains` reqular expression in the configuration file.
     * Otherwise, throws a SoftException.
     *
     * @param array  $data   Domain data
     * @param object $mapper Domain mapper
     *
     * @return void
     */
    private function insertDomain(array &$data, $mapper): void
    {
        $mapper = $this->connector->getMapper('domain');
        if ($mapper->count(1) !== 0) {
            if (is_null(self::$allowed_domains)) {
                $allowed = Core::instance()->config('fetcher/allowed_domains', '');
                if (!empty($allowed)) {
                    self::$allowed_domains = "<{$allowed}>i";
                }
            }
            try {
                $add = !empty(self::$allowed_domains) && preg_match(self::$allowed_domains, $data['fqdn']) === 1;
            } catch (\ErrorException $e) {
                $add = false;
                Core::instance()->logger()->warning(
                    'The allow_domains parameter in the settings has an incorrect regular expression value.'
                );
            }
            if (!$add) {
                throw new SoftException('Failed to add an incoming report: unknown domain: ' . $data['fqdn']);
            }
        }

        $data['active'] = true;
        $data['description'] = 'The domain was added automatically.';
        $mapper->save($data);
    }

    /**
     * Binds a nullable array to an SQL query as a json string
     *
     * @param PDOStatement $st   DB statement object
     * @param int          $idx  Bind position
     * @param array        $data JSON data or null
     *
     * @return void
     */
    private static function sqlBindJson($st, int $idx, $data): void
    {
        if (is_null($data)) {
            $val  = null;
            $type = \PDO::PARAM_NULL;
        } else {
            $val  = json_encode($data);
            $type = \PDO::PARAM_STR;
        }
        $st->bindValue($idx, $val, $type);
    }

    /**
     * Returns `ORDER BY ...` part of the SQL query
     *
     * @param array $order Key-value array with ordering options
     *
     * @return string
     */
    private function sqlOrderList(array &$order): string
    {

        $dir = $order['direction'] === 'ascent' ? 'ASC' : 'DESC';
        return " ORDER BY `{$order['field']}` {$dir}";
    }

    /**
     * The valid filter item names
     */
    private static $filters_available = [
        'domain', 'month', 'before_time', 'organization', 'dkim', 'spf', 'status'
    ];

    /**
     * Returns prepared filter data for sql queries
     *
     * @param array $filter Key-value array with filter options
     *
     * @return array
     */
    private function prepareFilterData(array &$filter): array
    {
        $filters = [];
        for ($i = 0; $i < 2; ++$i) {
            $filters[] = [
                'a_str'    => [],
                'bindings' => []
            ];
        }
        foreach (self::$filters_available as $fn) {
            if (isset($filter[$fn])) {
                $fv = $filter[$fn];
                switch (gettype($fv)) {
                    case 'string':
                        if (!empty($fv)) {
                            if ($fn == 'domain') {
                                $filters[0]['a_str'][] = '`rp`.`domain_id` = ?';
                                $d_data = [ 'fqdn' => $fv ];
                                $this->connector->getMapper('domain')->fetch($d_data);
                                $filters[0]['bindings'][] = [ $d_data['id'], \PDO::PARAM_INT ];
                            } elseif ($fn == 'month') {
                                $ma = explode('-', $fv);
                                if (count($ma) != 2) {
                                    throw new SoftException('Report list filter: Incorrect date format');
                                }
                                $year = (int)$ma[0];
                                $month = (int)$ma[1];
                                if ($year < 0 || $month < 1 || $month > 12) {
                                    throw new SoftException('Report list filter: Incorrect month or year value');
                                }
                                $filters[0]['a_str'][] = '`begin_time` < ? AND `end_time` >= ?';
                                $date1 = new DateTime("{$year}-{$month}-01");
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
                                        throw new SoftException('Report list filter: Incorrect DKIM value');
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
                                        throw new SoftException('Report list filter: Incorrect SPF value');
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
                                    throw new SoftException('Report list filter: Incorrect status value');
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
                        } elseif ($fn == 'before_time') {
                            $filters[0]['a_str'][] = '`begin_time` < ?';
                            $filters[0]['bindings'][] = [ $fv->format('Y-m-d H:i:s'), \PDO::PARAM_STR ];
                        }
                        break;
                    case 'integer':
                        if ($fn == 'domain') {
                            $filters[0]['a_str'][] = '`rp`.`domain_id` = ?';
                            $filters[0]['bindings'][] = [ $fv, \PDO::PARAM_INT ];
                        }
                        break;
                }
            }
        }
        $f_data = [];
        for ($i = 0; $i < count($filters); ++$i) {
            $filter = &$filters[$i];
            if (count($filter['a_str']) > 0) {
                $f_data[$i] = [
                    'str'      => implode(' AND ', $filter['a_str']),
                    'bindings' => $filter['bindings']
                ];
            }
            unset($filter);
        }
        return $f_data;
    }

    /**
     * Returns the SQL condition for a filter by filter id
     *
     * @param array  $f_data Array with prepared filter data
     * @param string $prefix Prefix, which will be added to the beginning of the condition string,
     *                       but only in the case when the condition string is not empty.
     * @param int    $f_id   Index of the filter
     *
     * @return string the condition string
     */
    private function sqlConditionList(array &$f_data, string $prefix, int $f_idx): string
    {
        return isset($f_data[$f_idx]) ? ($prefix . $f_data[$f_idx]['str']) : '';
    }

    /**
     * Returns `LIMIT ...` part of the SQL query
     *
     * @param array $limit Key-value array with two keys: `offset` and `count`
     *
     * @return string
     */
    private function sqlLimit(array &$limit): string
    {
        $res = '';
        if ($limit['count'] > 0) {
            $res = ' LIMIT ?';
            if ($limit['offset'] > 0) {
                $res .= ', ?';
            }
        }
        return $res;
    }

    /**
     * Binds the values of the filter and the limit to SQL query
     *
     * @param PDOStatement $st     Prepared SQL statement to bind to
     * @param array        $f_data Array with prepared filter data
     * @param array        $limit  Key-value array with two keys: `offset` and `count`
     *
     * @return void
     */
    private function sqlBindValues($st, array &$f_data, array &$limit): void
    {
        $pos = 0;
        if (isset($f_data[0])) {
            $this->sqlBindFilterValues($st, $f_data, 0, $pos);
        }
        if (isset($f_data[1])) {
            $this->sqlBindFilterValues($st, $f_data, 1, $pos);
        }
        if ($limit['count'] > 0) {
            if ($limit['offset'] > 0) {
                $st->bindValue(++$pos, $limit['offset'], \PDO::PARAM_INT);
            }
            $st->bindValue(++$pos, $limit['count'], \PDO::PARAM_INT);
        }
    }

    /**
     * Binds the values of the specified filter item to SQL query
     *
     * @param PDOStatement $st         Prepared SQL statement to bind to
     * @param array        $f_data     Array with prepared filter data
     * @param int          $filter_idx Index of the filter to bind to
     * @param int          $bind_pos   Start bind position (pointer). It will be increaded with each binding.
     *
     * @return void
     */
    private function sqlBindFilterValues($st, array &$f_data, int $filter_idx, int &$bind_pos): void
    {
        foreach ($f_data[$filter_idx]['bindings'] as &$bv) {
            $st->bindValue(++$bind_pos, $bv[0], $bv[1]);
        }
    }
}
