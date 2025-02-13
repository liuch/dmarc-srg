<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2025 Aleksey Andreev (liuch)
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
 * This file contains the HostMapper
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Database\Mariadb;

use Liuch\DmarcSrg\DateTime;
use Liuch\DmarcSrg\Database\HostMapperInterface;
use Liuch\DmarcSrg\Exception\DatabaseFatalException;

/**
 * HostMapper class implementation for MariaDB
 */
class HostMapper implements HostMapperInterface
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
     * Return an array with statistics
     *
     * @param array $data    Array with filter parameters
     * @param int   $user_id User ID
     *
     * @return array
     */
    public function statistics(array &$data, int $user_id): array
    {
        $res = [];
        if (!$user_id) {
            $user_domains1 = '';
            $user_domains2 = '';
        } else {
            $user_domains1 = ' INNER JOIN `' . $this->connector->tablePrefix('userdomains')
                . '`AS `ud` ON `rp`.`domain_id` = `ud`.`domain_id`';
            $user_domains2 = ' AND `user_id` = ' . $user_id;
        }
        $db = $this->connector->dbh();
        $db->beginTransaction();
        try {
            $st = $db->prepare(
                'SELECT COUNT(*), SUM(`rc`) FROM (SELECT COUNT(`report_id`), SUM(`rcount`) AS `rc` FROM `'
                . $this->connector->tablePrefix('rptrecords') . '` AS `rr` INNER JOIN `'
                . $this->connector->tablePrefix('reports') . '` AS `rp` ON `rr`.`report_id` = `rp`.`id`'
                . $user_domains1 . ' WHERE `rr`.`ip` = ?' . $user_domains2 . ' GROUP BY `report_id`) as t'
            );
            $st->bindValue(1, inet_pton($data['ip']), \PDO::PARAM_STR);
            $st->execute();
            $row = $st->fetch(\PDO::FETCH_NUM);
            $res['reports'] = intval($row[0]);
            $res['messages'] = intval($row[1]);
            $st->closeCursor();
            $st = $db->prepare(
                'SELECT `rp`.`id`, `begin_time` FROM `' . $this->connector->tablePrefix('rptrecords') . '` AS `rr`'
                . ' INNER JOIN `reports` AS `rp` ON `rr`.`report_id` = `rp`.`id`' . $user_domains1
                . ' WHERE `rr`.`ip` = ?' . $user_domains2 . ' GROUP BY `report_id` ORDER BY `begin_time` DESC LIMIT 2'
            );
            $st->bindValue(1, inet_pton($data['ip']), \PDO::PARAM_STR);
            $st->execute();
            $last_report = [];
            while ($row = $st->fetch(\PDO::FETCH_NUM)) {
                $last_report[] = new DateTime($row[1]);
            }
            $res['last_report'] = $last_report;
            $st->closeCursor();
            $db->commit();
        } catch (\PDOException $e) {
            $db->rollBack();
            throw new DatabaseFatalException('Failed to get host data', -1, $e);
        }
        return $res;
    }
}
