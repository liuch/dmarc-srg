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
 * This file contains the ReportLogMapper class
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Database\Mariadb;

use Liuch\DmarcSrg\DateTime;
use Liuch\DmarcSrg\Exception\DatabaseFatalException;
use Liuch\DmarcSrg\Exception\DatabaseNotFoundException;

/**
 * ReportLogMapper class implementation for MariaDB
 */
class ReportLogMapper
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
     * Fetches data of report log item from the database by id
     *
     * @param Report log data
     *
     * @return void
     */
    public function fetch(array &$data): void
    {
        try {
            $st = $this->connector->dbh()->prepare(
                'SELECT `domain`, `external_id`, `event_time`, `filename`, `source`, `success`, `message` FROM `'
                . $this->connector->tablePrefix('reportlog') . '` WHERE `id` = ?'
            );
            $st->bindValue(1, $data['id'], \PDO::PARAM_INT);
            $st->execute();
            if (!($row = $st->fetch(\PDO::FETCH_NUM))) {
                throw new DatabaseNotFoundException();
            }
            $data['domain']      = $row[0];
            $data['external_id'] = $row[1];
            $data['event_time']  = new DateTime($row[2]);
            $data['filename']    = $row[3];
            $data['source']      = intval($row[4]);
            $data['success']     = boolval($row[5]);
            $data['message']     = $row[6];
            $st->closeCursor();
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to get the log item', -1, $e);
        }
    }

    /**
     * Saves data of report log item to the database
     *
     * @return void
     */
    public function save(array &$data): void
    {
        $db = $this->connector->dbh();
        try {
            $id = $data['id'];
            if (is_null($id)) {
                $st = $db->prepare(
                    'INSERT INTO `' . $this->connector->tablePrefix('reportlog')
                    . '` (`domain`, `external_id`, `event_time`, `filename`, `source`, `success`, `message`)'
                    . ' VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
            } else {
                $st = $db->prepare(
                    'UPDATE `' . $this->connector->tablePrefix('reportlog')
                    . '` SET `domain` = ?, `external_id` = ?, `event_time` = ?, `filename` = ?,'
                    . ' `source` = ?, `success` = ?, `message` = ? WHERE `id` = ?'
                );
                $st->bindValue(8, $id, \PDO::PARAM_INT);
            }
            $ts = $data['event_time'] ?? (new DateTime());
            $st->bindValue(1, $data['domain'], \PDO::PARAM_STR);
            $st->bindValue(2, $data['external_id'], \PDO::PARAM_STR);
            $st->bindValue(3, $ts->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
            $st->bindValue(4, $data['filename'], \PDO::PARAM_STR);
            $st->bindValue(5, $data['source'], \PDO::PARAM_INT);
            $st->bindValue(6, $data['success'], \PDO::PARAM_BOOL);
            $st->bindValue(7, $data['message'], \PDO::PARAM_STR);
            $st->execute();
            if (is_null($id)) {
                $data['id'] = intval($db->lastInsertId());
            }
            $st->closeCursor();
            $data['event_time'] = $ts;
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to save a report log item');
        }
    }

    /**
     * Returns a list of report log items with given criteria
     *
     * @param array $filter Key-value array:
     *                      'from_time' => DateTime
     *                      'till_time' => DateTime
     * @param array $order  Key-value array with order options:
     *                      'direction' => string, 'ascent' or 'descent'
     * @param array $limit  Key-value array:
     *                      'offset' => int
     *                      'count'  => int
     *
     * @return array
     */
    public function list(array &$filter, array &$order, array &$limit): array
    {
        $list = [];
        try {
            $st = $this->connector->dbh()->prepare(
                'SELECT `id`, `domain`, `event_time`, `source`, `success`, `message` FROM `'
                . $this->connector->tablePrefix('reportlog') . '`'
                . $this->sqlCondition($filter)
                . $this->sqlOrder($order)
                . $this->sqlLimit($limit)
            );
            $this->sqlBindValues($st, $filter, $limit);
            $st->execute();
            while ($row = $st->fetch(\PDO::FETCH_NUM)) {
                $list[] = [
                    'id'         => intval($row[0]),
                    'domain'     => $row[1],
                    'event_time' => new DateTime($row[2]),
                    'source'     => intval($row[3]),
                    'success'    => boolval($row[4]),
                    'message'    => $row[5]
                ];
            }
            $st->closeCursor();
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to get the logs', -1, $e);
        }
        return $list;
    }

    /**
     * Returns the number of report log items matching the specified filter and limits
     *
     * @param array $filter Key-value array with filtering parameters
     * @param array $limit  Key-value array with limits
     *
     * @return int
     */
    public function count(array &$filter, array &$limit): int
    {
        $cnt = 0;
        try {
            $st = $this->connector->dbh()->prepare(
                'SELECT COUNT(*) FROM `' . $this->connector->tablePrefix('reportlog') . '`'
                . $this->sqlCondition($filter)
                . $this->sqlLimit($limit)
            );
            $this->sqlBindValues($st, $filter, $limit);
            $st->execute();
            $cnt = intval($st->fetch(\PDO::FETCH_NUM)[0]);
            $st->closeCursor();
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to get the log data', -1, $e);
        }
        return $cnt;
    }

    /**
     * Deletes report log items from the database
     *
     * @param array $filter Key-value array with filtering parameters
     * @param array $order  Key-value array with order options:
     *                      'direction' => string, 'ascent' or 'descent'
     * @param array $limit  Key-value array with limits
     *
     * @return void
     */
    public function delete(array &$filter, array &$order, array &$limit): void
    {
        try {
            $st = $this->connector->dbh()->prepare(
                'DELETE FROM `' . $this->connector->tablePrefix('reportlog') . '`'
                . $this->sqlCondition($filter)
                . $this->sqlOrder($order)
                . $this->sqlLimit($limit)
            );
            $this->sqlBindValues($st, $filter, $limit);
            $st->execute();
            $st->closeCursor();
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to remove the log data', -1, $e);
        }
    }

    /**
     * Returns a string with an SQL condition 'WHERE ...'
     *
     * @param array $filter Key-value with filtering paremeters
     *
     * @return string
     */
    private function sqlCondition(array &$filter): string
    {
        $res = '';
        if (!is_null($filter['from_time']) || !is_null($filter['till_time'])) {
            $res = ' WHERE';
            $till_time = $filter['till_time'];
            if (!is_null($filter['from_time'])) {
                $res .= ' `event_time` >= ?';
                if (!is_null($till_time)) {
                    $res .= ' AND';
                }
            }
            if (!is_null($till_time)) {
                $res .= ' `event_time` < ?';
            }
        }
        return $res;
    }

    /**
     * Returns 'ORDER BY ...' part of the SQL query
     *
     * @param array $order Key-value array with ordering options
     *
     * @return string
     */
    private function sqlOrder(array &$order): string
    {
        return ' ORDER BY `event_time` ' . ($order['direction'] === 'descent' ? 'DESC' : 'ASC');
    }

    /**
     * Returns 'LIMIT ...' part of the SQL string
     *
     * @param array $limit Key-value array with keys 'offset' and 'count'
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
     * @param PDOStatement $st     Prepared SOL statement to bind to
     * @param array        $filter Key-value array with filter data
     * @param array        $limit  Key-value array with limit data
     *
     * @return void
     */
    private function sqlBindValues($st, array &$filter, array &$limit): void
    {
        $pos = 0;
        if (!is_null($filter['from_time'])) {
            $st->bindValue(++$pos, $filter['from_time']->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
        }
        if (!is_null($filter['till_time'])) {
            $st->bindValue(++$pos, $filter['till_time']->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
        }
        if ($limit['count'] > 0) {
            if ($limit['offset'] > 0) {
                $st->bindValue(++$pos, $limit['offset'], \PDO::PARAM_INT);
            }
            $st->bindValue(++$pos, $limit['count'], \PDO::PARAM_INT);
        }
    }
}
