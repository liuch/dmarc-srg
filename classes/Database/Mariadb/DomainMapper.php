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
 * This file contains the DomainMapper class
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Database\Mariadb;

use Liuch\DmarcSrg\DateTime;
use Liuch\DmarcSrg\Database\DomainMapperInterface;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\DatabaseFatalException;
use Liuch\DmarcSrg\Exception\DatabaseNotFoundException;
use Liuch\DmarcSrg\Exception\DatabaseException;

/**
 * DomainMapper class implementation for MariaDB
 */
class DomainMapper implements DomainMapperInterface
{
    /** @var \Liuch\DmarcSrg\Database\DatabaseConnector */
    private $connector = null;

    /**
     * The constructor
     *
     * @param \Liuch\DmarcSrg\Database\DatabaseConnector $connector
     */
    public function __construct(object $connector)
    {
        $this->connector = $connector;
    }

    /**
     * Return true if the domain exists or false otherwise.
     *
     * @param array $data Array with domain data to search
     *
     * @return bool
     */
    public function exists(array &$data): bool
    {
        try {
            $st = $this->connector->dbh()->prepare(
                'SELECT `id` FROM `' . $this->connector->tablePrefix('domains') .
                '` WHERE ' . $this->sqlCondition($data)
            );
            $this->sqlBindValue($st, 1, $data);
            $st->execute();
            $res = $st->fetch(\PDO::FETCH_NUM);
            $st->closeCursor();
            if (!$res) {
                return false;
            }
            $data['id'] = intval($res[0]);
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to get domain ID', -1, $e);
        }
        return true;
    }

    /**
     * Fetch the domain data from the database by its id or name
     *
     * @param array $data Domain data to update
     *
     * @return void
     */
    public function fetch(array &$data): void
    {
        try {
            $st = $this->connector->dbh()->prepare(
                'SELECT `id`, `fqdn`, `active`, `description`, `created_time`, `updated_time` FROM `'
                . $this->connector->tablePrefix('domains') . '` WHERE ' . $this->sqlCondition($data)
            );
            $this->sqlBindValue($st, 1, $data);
            $st->execute();
            $res = $st->fetch(\PDO::FETCH_NUM);
            $st->closeCursor();
            if (!$res) {
                throw new DatabaseNotFoundException('Domain not found');
            }
            $data['id']           = intval($res[0]);
            $data['fqdn']         = $res[1];
            $data['active']       = boolval($res[2]);
            $data['description']  = $res[3];
            $data['created_time'] = new DateTime($res[4]);
            $data['updated_time'] = new DateTime($res[5]);
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to fetch the domain data', -1, $e);
        }
    }

    /**
     * Saves domain data to the database (updates or inserts an record)
     *
     * @param array $data Domain data
     *
     * @return void
     */
    public function save(array &$data): void
    {
        $db = $this->connector->dbh();
        $data['updated_time'] = new DateTime();
        if ($this->exists($data)) {
            try {
                $st = $db->prepare(
                    'UPDATE `' . $this->connector->tablePrefix('domains')
                    . '` SET `active` = ?, `description` = ?, `updated_time` = ? WHERE `id` = ?'
                );
                $st->bindValue(1, $data['active'], \PDO::PARAM_BOOL);
                $st->bindValue(2, $data['description'], \PDO::PARAM_STR);
                $st->bindValue(3, $data['updated_time']->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
                $st->bindValue(4, $data['id'], \PDO::PARAM_INT);
                $st->execute();
                $st->closeCursor();
            } catch (\PDOException $e) {
                throw new DatabaseException('Failed to update the domain data', -1, $e);
            }
        } else {
            try {
                $active = $data['active'] ?? false;
                $data['created_time'] = $data['updated_time'];
                if (is_null($data['description'])) {
                    $sql1 = '';
                    $sql2 = '';
                } else {
                    $sql1 = ', `description`';
                    $sql2 = ', ?';
                }
                $st = $db->prepare(
                    'INSERT INTO `' . $this->connector->tablePrefix('domains')
                    . '` (`fqdn`, `active`' . $sql1 . ', `created_time`, `updated_time`)'
                    . ' VALUES (?, ?' . $sql2 . ', ?, ?)'
                );
                $idx = 0;
                $st->bindValue(++$idx, $data['fqdn'], \PDO::PARAM_STR);
                $st->bindValue(++$idx, $active, \PDO::PARAM_BOOL);
                if (!is_null($data['description'])) {
                    $st->bindValue(++$idx, $data['description'], \PDO::PARAM_STR);
                }
                $st->bindValue(++$idx, $data['created_time']->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
                $st->bindValue(++$idx, $data['updated_time']->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
                $st->execute();
                $st->closeCursor();
                $data['id']     = intval($db->lastInsertId());
                $data['active'] = $active;
            } catch (\PDOException $e) {
                throw new DatabaseFatalException('Failed to insert the domain data', -1, $e);
            }
        }
    }

    /**
     * Deletes the domain from the database
     *
     * Deletes the domain if there are no reports for this domain in the database.
     *
     * @param array $data Domain data
     *
     * @return void
     */
    public function delete(array &$data): void
    {
        $db = $this->connector->dbh();
        $db->beginTransaction();
        try {
            $filter = [ 'domain' => $data['id'] ];
            $limit  = [ 'offset' => 0, 'count' => 0 ];
            $r_count = $this->connector->getMapper('report')->count($filter, $limit);
            if ($r_count > 0) {
                switch ($r_count) {
                    case 1:
                        $s1 = 'is';
                        $s2 = '';
                        break;
                    default:
                        $s1 = 'are';
                        $s2 = 's';
                        break;
                }
                throw new SoftException(
                    "Failed to delete: there {$s1} {$r_count} incoming report{$s2} for this domain"
                );
            }
            $st = $db->prepare('DELETE FROM `' . $this->connector->tablePrefix('domains') . '` WHERE `id` = ?');
            $st->bindValue(1, $data['id'], \PDO::PARAM_INT);
            $st->execute();
            $st->closeCursor();
            $db->commit();
        } catch (\PDOException $e) {
            $db->rollBack();
            throw new DatabaseFatalException('Failed to delete the domain', -1, $e);
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Returns a list of domains data from the database
     *
     * @return array
     */
    public function list(): array
    {
        $list = [];
        try {
            $st = $this->connector->dbh()->query(
                'SELECT `id`, `fqdn`, `active`, `description`, `created_time`, `updated_time` FROM `'
                . $this->connector->tablePrefix('domains') . '`'
            );
            while ($row = $st->fetch(\PDO::FETCH_NUM)) {
                $list[] = [
                    'id'           => intval($row[0]),
                    'fqdn'         => $row[1],
                    'active'       => boolval($row[2]),
                    'description'  => $row[3],
                    'created_time' => new DateTime($row[4]),
                    'updated_time' => new DateTime($row[5])
                ];
            }
            $st->closeCursor();
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to get the domain list', -1, $e);
        }
        return $list;
    }

    /**
     * Returns an ordered array with domain names from the database
     *
     * @return array
     */
    public function names(): array
    {
        $res = [];
        try {
            $st = $this->connector->dbh()->query(
                'SELECT `fqdn` FROM `' . $this->connector->tablePrefix('domains') . '` ORDER BY `fqdn`',
                \PDO::FETCH_NUM
            );
            while ($name = $st->fetchColumn(0)) {
                $res[] = $name;
            }
            $st->closeCursor();
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to get a list of domain names', -1, $e);
        }
        return $res;
    }

    /**
     * Returns the total number of domains in the database
     *
     * @param int $max The maximum number of records to count. 0 means no limitation.
     *
     * @return int The total number of domains
     */
    public function count(int $max = 0): int
    {
        $number = 0;
        try {
            $query_str = 'SELECT COUNT(*) FROM `' . $this->connector->tablePrefix('domains') . '`';
            if ($max > 0) {
                $query_str .= " LIMIT {$max}";
            }
            $st = $this->connector->dbh()->query($query_str, \PDO::FETCH_NUM);
            $number = intval($st->fetchColumn(0));
            $st->closeCursor();
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to get the number of domains', -1, $e);
        }
        return $number;
    }

    /**
     * Returns a condition string for a WHERE statement based on existing domain data
     *
     * @param array $data Domain data
     *
     * @return string Condition string
     */
    private function sqlCondition(array &$data): string
    {
        if (isset($data['id'])) {
            return '`id` = ?';
        }
        return '`fqdn` = ?';
    }

    /**
     * Binds values for SQL queries based on existing domain data
     *
     * @param \PDOStatement $st   PDO Statement to bind to
     * @param int           $pos  Start position for binding
     * @param array         $data Domain data
     *
     * @return void
     */
    private function sqlBindValue($st, int $pos, array &$data): void
    {
        if (isset($data['id'])) {
            $st->bindValue($pos, $data['id'], \PDO::PARAM_INT);
        } else {
            $st->bindValue($pos, $data['fqdn'], \PDO::PARAM_STR);
        }
    }
}
