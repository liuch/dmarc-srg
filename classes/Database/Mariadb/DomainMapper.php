<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2022-2024 Aleksey Andreev (liuch)
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
use Liuch\DmarcSrg\Exception\LogicException;
use Liuch\DmarcSrg\Exception\DatabaseFatalException;
use Liuch\DmarcSrg\Exception\DatabaseNotFoundException;

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
     * @param \Liuch\DmarcSrg\Database\DatabaseConnector $connector DatabaseConnector instance of the current database
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
            $this->sqlBindValues($st, $data, 1);
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
     * Returns true if the domain exists and is assigned to the user
     *
     * @param array $data    Array with domain data to check
     * @param int   $user_id User ID to check
     *
     * @return bool
     */
    public function isAssigned(array &$data, int $user_id): bool
    {
        $res = null;
        try {
            $st = $this->connector->dbh()->prepare(
                'SELECT 1 FROM `' . $this->connector->tablePrefix('userdomains') . '` INNER JOIN `'
                . $this->connector->tablePrefix('domains') . '` ON `domain_id` = `id` WHERE `user_id` = ? AND '
                . $this->sqlCondition($data)
            );
            $st->bindValue(1, $user_id, \PDO::PARAM_INT);
            $this->sqlBindValues($st, $data, 2);
            $st->execute();
            $res = $st->fetch(\PDO::FETCH_NUM);
            $st->closeCursor();
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to get user domain data', -1, $e);
        }
        return boolval($res);
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
            $this->sqlBindValues($st, $data, 1);
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
                throw new DatabaseFatalException('Failed to update the domain data', -1, $e);
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
     * @param int $id Domain ID
     *
     * @return void
     */
    public function delete(int $id): void
    {
        $db = $this->connector->dbh();
        $db->beginTransaction();
        try {
            $filter = [ 'domain' => $id ];
            $limit  = [ 'offset' => 0, 'count' => 0 ];
            $r_count = $this->connector->getMapper('report')->count($filter, $limit, 0);
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
            $st = $db->prepare(
                'DELETE FROM `' . $this->connector->tablePrefix('userdomains') . '` WHERE `domain_id` = ?'
            );
            $st->bindValue(1, $id, \PDO::PARAM_INT);
            $st->execute();
            $st->closeCursor();
            $st = $db->prepare('DELETE FROM `' . $this->connector->tablePrefix('domains') . '` WHERE `id` = ?');
            $st->bindValue(1, $id, \PDO::PARAM_INT);
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
     * @param int $user_id User ID to retrieve the list for
     *
     * @return array
     */
    public function list(int $user_id): array
    {
        $list = [];
        try {
            $query_str = 'SELECT `id`, `fqdn`, `active`, `description`, `created_time`, `updated_time` FROM `';
            if ($user_id) {
                $query_str .= $this->connector->tablePrefix('userdomains') . '` INNER JOIN `'
                    . $this->connector->tablePrefix('domains') . '` ON `domain_id` = `id` WHERE `user_id` = '
                    . $user_id;
            } else {
                $query_str .= $this->connector->tablePrefix('domains') . '`';
            }
            $st = $this->connector->dbh()->query($query_str);
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
     * @param int $user_id User ID to retrieve the list for
     *
     * @return array
     */
    public function names(int $user_id): array
    {
        $res = [];
        try {
            if ($user_id) {
                $query_str = 'SELECT `fqdn` FROM `' . $this->connector->tablePrefix('userdomains')
                    . '` INNER JOIN `' . $this->connector->tablePrefix('domains')
                    . '` ON `domain_id` = `id` WHERE `user_id` = ' . $user_id . ' ORDER BY `fqdn`';
            } else {
                $query_str = 'SELECT `fqdn` FROM `' . $this->connector->tablePrefix('domains') . '` ORDER BY `fqdn`';
            }
            $st = $this->connector->dbh()->query($query_str, \PDO::FETCH_NUM);
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
     * @param int $user_id User ID
     * @param int $max     The maximum number of records to count. 0 means no limitation.
     *
     * @return int The total number of domains
     */
    public function count(int $user_id, int $max = 0): int
    {
        $number = 0;
        try {
            if ($user_id === 0) {
                $tn = 'domains';
                $wr = '';
            } else {
                $tn = 'userdomains';
                $wr = " WHERE `user_id` = {$user_id}";
            }
            $tn = $this->connector->tablePrefix($tn);
            $query_str = "SELECT COUNT(*) FROM `{$tn}`{$wr}";
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
     * Assigns the domain to a user
     *
     * @param array $data    Domain data
     * @param int   $user_id User ID
     *
     * @return void
     */
    public function assignUser(array &$data, int $user_id): void
    {
        if (!$user_id) {
            throw new LogicException('Attempting to assign a domain to admin');
        }

        $db = $this->connector->dbh();
        $db->beginTransaction();
        try {
            $st = $db->prepare(
                'SELECT `id` FROM `' . $this->connector->tablePrefix('domains')
                . '` WHERE ' . $this->sqlCondition($data)
            );
            $this->sqlBindValues($st, $data, 1);
            $st->execute();
            $id = $st->fetchColumn(0);
            $st->closeCursor();
            if ($id !== false) {
                $data['id'] = intval($id);
                $st = $db->prepare(
                    'SELECT 1 FROM `' . $this->connector->tablePrefix('users') . '` WHERE `id` = ?'
                );
                $st->bindValue(1, $user_id, \PDO::PARAM_INT);
                $st->execute();
                $res = $st->fetchColumn(0);
                $st->closeCursor();
                if ($res) {
                    $ud_tn = $this->connector->tablePrefix('userdomains');
                    $st = $db->prepare('SELECT 1 FROM `' . $ud_tn . '` WHERE `domain_id` = ? AND `user_id` = ?');
                    $st->bindValue(1, $data['id'], \PDO::PARAM_INT);
                    $st->bindValue(2, $user_id, \PDO::PARAM_INT);
                    $st->execute();
                    $res = $st->fetchColumn(0);
                    $st->closeCursor();
                    if (!$res) {
                        $st = $db->prepare('INSERT INTO `' . $ud_tn . '` (`domain_id`, `user_id`) VALUES (?, ?)');
                        $st->bindValue(1, $data['id'], \PDO::PARAM_INT);
                        $st->bindValue(2, $user_id, \PDO::PARAM_INT);
                        $st->execute();
                        $st->closeCursor();
                    }
                }
            }
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Unassign the domain from a user
     *
     * @param array $data    Domain data
     * @param int   $user_id User ID
     *
     * @return void
     */
    public function unassignUser(array &$data, int $user_id): void
    {
        if (!$user_id) {
            throw new LogicException('Attempting to unassign a domain from admin');
        }

        try {
            $dm_tn = $this->connector->tablePrefix('domains');
            $ud_tn = $this->connector->tablePrefix('userdomains');
            $st = $this->connector->dbh()->prepare(
                "DELETE `{$ud_tn}` FROM `{$ud_tn}` INNER JOIN `{$dm_tn}` ON `domain_id` = `id` WHERE "
                . $this->sqlCondition($data)
            );
            $this->sqlBindValues($st, $data, 1);
            $st->execute();
            $st->closeCursor();
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to unassign a domain', -1, $e);
        }
    }

    /**
     * Updates the list of domains assigned to a user
     *
     * @param array $domains List of domains
     * @param int   $user_id User ID
     *
     * @return void
     */
    public function updateUserDomains(array &$domains, int $user_id): void
    {
        if (!$user_id) {
            throw new LogicException('Attempting to udpate domains for admin');
        }

        $db = $this->connector->dbh();
        $db->beginTransaction();
        try {
            $st = $db->prepare(
                'DELETE FROM `' . $this->connector->tablePrefix('userdomains') . '` WHERE `user_id` = ?'
            );
            $st->bindValue(1, $user_id, \PDO::PARAM_INT);
            $st->execute();
            $st->closeCursor();
            $cnt = count($domains);
            if ($cnt) {
                $query_str = 'INSERT INTO `' . $this->connector->tablePrefix('userdomains')
                    . '` (`domain_id`, `user_id`) SELECT `id`, ' . $user_id . ' FROM `'
                    . $this->connector->tablePrefix('domains') . '` WHERE `fqdn` IN ('
                    . substr(str_repeat('?,', $cnt), 0, -1) . ')';
                $st = $db->prepare($query_str);
                $pos = 0;
                foreach ($domains as $dom_str) {
                    $st->bindValue(++$pos, $dom_str, \PDO::PARAM_STR);
                }
                $st->execute();
                $st->closeCursor();
            }
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
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
     * @param array         $data Domain data
     * @param int           $pos  Start position for binding
     *
     * @return void
     */
    private function sqlBindValues($st, array &$data, int $pos): void
    {
        if (isset($data['id'])) {
            $st->bindValue($pos, $data['id'], \PDO::PARAM_INT);
        } else {
            $st->bindValue($pos, $data['fqdn'], \PDO::PARAM_STR);
        }
    }
}
