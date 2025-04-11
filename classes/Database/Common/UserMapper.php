<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2023-2025 Aleksey Andreev (liuch)
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
 * This file contains the UserMapper
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Database\Common;

use Liuch\DmarcSrg\DateTime;
use Liuch\DmarcSrg\Database\UserMapperInterface;
use Liuch\DmarcSrg\Exception\DatabaseFatalException;
use Liuch\DmarcSrg\Exception\DatabaseNotFoundException;

/**
 * Universal implementation of UserMapper class
 */
class UserMapper implements UserMapperInterface
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
     * Return true if the user exists or false otherwise.
     *
     * @param array $data Array with user data to search
     *
     * @return bool
     */
    public function exists(array &$data): bool
    {
        try {
            $st = $this->connector->dbh()->prepare(
                'SELECT id FROM ' . $this->connector->tablePrefix('users') . ' WHERE ' . $this->sqlCondition($data)
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
            throw new DatabaseFatalException('Failed to verify the user existence', -1, $e);
        }
        return true;
    }

    /**
     * Fetch the user data from the database by its id or name
     *
     * @param array $data User data to update
     *
     * @return void
     */
    public function fetch(array &$data): void
    {
        try {
            $st = $this->connector->dbh()->prepare(
                'SELECT id, name, level, enabled, IF(password IS NULL OR password = \'\', FALSE, TRUE),'
                . ' email, "key", session, created_time, updated_time FROM '
                . $this->connector->tablePrefix('users') . ' WHERE ' . $this->sqlCondition($data)
            );
            $this->sqlBindValues($st, $data, 1);
            $st->execute();
            $res = $st->fetch(\PDO::FETCH_NUM);
            $st->closeCursor();
            if (!$res) {
                 throw new DatabaseNotFoundException('User not found');
            }
            $data['id']           = intval($res[0]);
            $data['name']         = $res[1];
            $data['level']        = intval($res[2]);
            $data['enabled']      = boolval($res[3]);
            $data['password']     = boolval($res[4]);
            $data['email']        = $res[5];
            $data['key']          = $res[6];
            $data['session']      = intval($res[7]);
            $data['created_time'] = new DateTime($res[8]);
            $data['updated_time'] = new DateTime($res[9]);
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to fetch the user data', -1, $e);
        }
    }

    /**
     * Saves user data to the database (updates or inserts an record)
     *
     * @param array $data User data
     *
     * @return void
     */
    public function save(array &$data): void
    {
        $db = $this->connector->dbh();
        $data['updated_time'] = new DateTime();
        $enabled = $data['enabled'] ?? false;
        if ($this->exists($data)) {
            try {
                $id = $data['id'];
                $db->beginTransaction();
                $u_tn = $this->connector->tablePrefix('users');
                $extra = '';
                if (!$enabled) {
                    $st = $db->prepare("SELECT enabled FROM {$u_tn} WHERE id = ?");
                    $st->bindValue(1, $id, \PDO::PARAM_INT);
                    $st->execute();
                    $res = $st->fetch(\PDO::FETCH_NUM);
                    $st->closeCursor();
                    if ($res && boolval($res[0])) {
                        // The user got deactivated. Reset its active sessions.
                        $extra = ', session = session + 1';
                    }
                }
                $st = $db->prepare(
                    'UPDATE ' . $u_tn
                    . ' SET level = ?, enabled = ?, email = ?, "key" = ?, updated_time = ?'
                    . $extra . ' WHERE id = ?'
                );
                $st->bindValue(1, $data['level'], \PDO::PARAM_INT);
                $st->bindValue(2, $enabled, \PDO::PARAM_BOOL);
                if (empty($data['email'])) {
                    $st->bindValue(3, null, \PDO::PARAM_NULL);
                } else {
                    $st->bindValue(3, $data['email'], \PDO::PARAM_STR);
                }
                if (empty($data['key'])) {
                    $st->bindValue(4, null, \PDO::PARAM_NULL);
                } else {
                    $st->bindValue(4, $data['key'], \PDO::PARAM_STR);
                }
                $st->bindValue(5, $data['updated_time']->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
                $st->bindValue(6, $id, \PDO::PARAM_INT);
                $st->execute();
                $st->closeCursor();
                $db->commit();
            } catch (\PDOException $e) {
                $db->rollBack();
                throw new DatabaseFatalException('Failed to update the user data', -1, $e);
            } catch (\Exception $e) {
                $db->rollBack();
                throw $e;
            }
        } else {
            try {
                $data['created_time'] = $data['updated_time'];
                if (!is_null($data['email'])) {
                    $ss1 = ', email';
                    $ss2 = ', ?';
                } else {
                    $ss1 = '';
                    $ss2 = '';
                }
                if (!is_null($data['key'])) {
                    $ss1 .= ', "key"';
                    $ss2 .= ', ?';
                }
                $st = $db->prepare(
                    'INSERT INTO ' . $this->connector->tablePrefix('users')
                    . ' (name, level, enabled' . $ss1 . ', session, created_time, updated_time)'
                    . ' VALUES (?, ?, ?' . $ss2 . ', ?, ?, ?)'
                );
                $idx = 0;
                $st->bindValue(++$idx, $data['name'], \PDO::PARAM_STR);
                $st->bindValue(++$idx, $data['level'], \PDO::PARAM_INT);
                $st->bindValue(++$idx, $enabled, \PDO::PARAM_BOOL);
                if (!is_null($data['email'])) {
                    $st->bindValue(++$idx, $data['email'], \PDO::PARAM_STR);
                }
                if (!is_null($data['key'])) {
                    $st->bindValue(++$idx, $data['key'], \PDO::PARAM_STR);
                }
                $st->bindValue(++$idx, 0, \PDO::PARAM_INT);
                $st->bindValue(++$idx, $data['created_time']->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
                $st->bindValue(++$idx, $data['updated_time']->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
                $st->execute();
                $st->closeCursor();
                $data['id'] = intval($db->lastInsertId());
                $data['enabled'] = $enabled;
                $data['session'] = 0;
            } catch (\PDOException $e) {
                throw new DatabaseFatalException('Failed to insert the user data', -1, $e);
            }
        }
    }

    /**
     * Deletes the user from the database
     *
     * @param array $data User data
     *
     * @return void
     */
    public function delete(array &$data): void
    {
        $db = $this->connector->dbh();
        $db->beginTransaction();
        try {
            $id = $data['id'];
            $st = $db->prepare('DELETE FROM ' . $this->connector->tablePrefix('userdomains')
                . ' WHERE user_id = ?');
            $st->bindValue(1, $id, \PDO::PARAM_INT);
            $st->execute();
            $st->closeCursor();
            $st = $db->prepare('DELETE FROM ' . $this->connector->tablePrefix('system') . ' WHERE user_id = ?');
            $st->bindValue(1, $id, \PDO::PARAM_INT);
            $st->execute();
            $st->closeCursor();
            $st = $db->prepare('DELETE FROM ' . $this->connector->tablePrefix('users') . ' WHERE id = ?');
            $st->bindValue(1, $id, \PDO::PARAM_INT);
            $st->execute();
            $st->closeCursor();
            $db->commit();
        } catch (\PDOException $e) {
            $db->rollBack();
            throw new DatabaseFatalException('Failed to delete the user', -1, $e);
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Returns a list of users data from the database
     *
     * @return array
     */
    public function list(): array
    {
        $list = [];
        try {
            $st = $this->connector->dbh()->query(
                'SELECT id, name, level, enabled, email, "key", created_time, updated_time, '
                . '(SELECT COUNT(*) FROM ' . $this->connector->tablePrefix('userdomains')
                . ' WHERE user_id = id) AS domains FROM ' . $this->connector->tablePrefix('users')
            );
            while ($row = $st->fetch(\PDO::FETCH_NUM)) {
                $list [] = [
                    'id'           => intval($row[0]),
                    'name'         => $row[1],
                    'level'        => intval($row[2]),
                    'enabled'      => boolval($row[3]),
                    'email'        => $row[4],
                    'key'          => $row[5],
                    'created_time' => new DateTime($row[6]),
                    'updated_time' => new DateTime($row[7]),
                    'domains'      => intval($row[8])
                ];
            }
            $st->closeCursor();
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to get the domain list', -1, $e);
        }
        return $list;
    }

    /**
     * Returns the user's password hash
     *
     * @param array $data User data
     *
     * @return string
     */
    public function getPasswordHash(array &$data): string
    {
        $hash = '';
        try {
            $st = $this->connector->dbh()->prepare(
                'SELECT password FROM ' . $this->connector->tablePrefix('users')
                . ' WHERE ' . $this->sqlCondition($data)
            );
            $this->sqlBindValues($st, $data, 1);
            $st->execute();
            $res = $st->fetch(\PDO::FETCH_NUM);
            $st->closeCursor();
            if (!$res) {
                 throw new DatabaseNotFoundException('User not found');
            }
            $hash = strval($res[0]);
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to get user data', -1, $e);
        }
        return $hash;
    }

    /**
     * Replaces the user's password hash with the passed one
     *
     * @param array  $data User data
     * @param string $hash Password hash to save
     */
    public function savePasswordHash(array &$data, string $hash): void
    {
        try {
            $st = $this->connector->dbh()->prepare(
                'UPDATE ' . $this->connector->tablePrefix('users')
                . ' SET password = ?, session = session + 1 WHERE ' . $this->sqlCondition($data)
            );
            $st->bindValue(1, $hash, \PDO::PARAM_STR);
            $this->sqlBindValues($st, $data, 2);
            $st->execute();
            $st->closeCursor();
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to save user data', -1, $e);
        }
    }

    /**
     * Updates the user's key string
     *
     * @param array  $data User data
     * @param string $key  User key string to set
     *
     * @return void
     */
    public function setUserKey(array &$data, string $key): void
    {
        try {
            $st = $this->connector->dbh()->prepare(
                'UPDATE ' . $this->connector->tablePrefix('users')
                . ' SET "key" = ? WHERE ' . $this->sqlCondition($data)
            );
            $st->bindValue(1, $key, \PDO::PARAM_STR);
            $this->sqlBindValues($st, $data, 2);
            $st->execute();
            $st->closeCursor();
            $data['key'] = $key;
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to update user key', -1, $e);
        }
    }

    /**
     * Returns a condition string for a WHERE statement based on existing user data
     *
     * @param array $data User data
     *
     * @return string
     */
    private function sqlCondition(array &$data): string
    {
        if (isset($data['id'])) {
            return 'id = ?';
        }
        return 'name = ?';
    }

    /**
     * Binds values for SQL queries based on existing user data
     *
     * @param \PDOStatement $st   PDO statement to bind to
     * @param array         $data User data
     * @param int           $pos  Bind position
     *
     * @return void
     */
    private function sqlBindValues($st, array &$data, int $pos): void
    {
        if (isset($data['id'])) {
            $st->bindValue($pos, $data['id'], \PDO::PARAM_INT);
        } else {
            $st->bindValue($pos, $data['name'], \PDO::PARAM_STR);
        }
    }
}
