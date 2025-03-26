<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2022-2025 Aleksey Andreev (liuch)
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
 * This file contains the SettingMapper class
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Database\Common;

use Liuch\DmarcSrg\Database\SettingMapperInterface;
use Liuch\DmarcSrg\Exception\DatabaseFatalException;
use Liuch\DmarcSrg\Exception\DatabaseNotFoundException;

/**
 * Universal implementation of SettingMapper class
 */
class SettingMapper implements SettingMapperInterface
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
     * Returns setting value as a string by key
     *
     * The method contains a workaround to update the database structure from v3.2 to v4.0.
     * The method does two attempts to execute the query: with and without the user_id field.
     * It needs to be fixed in the future.
     *
     * @param string $key
     * @param int    $user_id
     *
     * @return string
     */
    public function value(string $key, int $user_id): string
    {
        $db = $this->connector->dbh();
        $sn = $this->connector->tablePrefix('system');
        $tr = [
            [
                "SELECT value FROM {$sn} WHERE user_id = ? AND \"key\" = ?",
                [ [ 1, $user_id, \PDO::PARAM_INT ], [ 2, $key, \PDO::PARAM_STR ] ],
            ],
            [
                "SELECT value FROM {$sn} WHERE \"key\" = ?",
                [ [ 1, $key, \PDO::PARAM_STR ] ],
            ]
        ];
        try {
            for ($i = 0; $i < 2; ++$i) {
                $it = $tr[$i];
                $st = $db->prepare($it[0]);
                foreach ($it[1] as &$b) {
                    $st->bindValue($b[0], $b[1], $b[2]);
                }
                unset($b);
                try {
                    $st->execute();
                    if (!($res = $st->fetch(\PDO::FETCH_NUM))) {
                        throw new DatabaseNotFoundException('Setting not found: ' . $key);
                    }
                    $st->closeCursor();
                    return $res[0];
                } catch (\PDOException $e) {
                    if ($i !== 0 || $user_id !== 0 || !str_contains($e->getMessage(), 'user_id')) {
                        throw $e;
                    }
                }
            }
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to get a setting', -1, $e);
        }
    }

    /**
     * Returns a key-value array of the setting list like this:
     * [ 'name1' => 'value1', 'name2' => 'value2' ]
     *
     * @param int $user_id User Id to get settings for
     *
     * @return array
     */
    public function list(int $user_id): array
    {
        $res = [];
        try {
            $st = $this->connector->dbh()->prepare(
                'SELECT "key", value FROM ' . $this->connector->tablePrefix('system')
                . ' WHERE user_id = ? ORDER BY "key"'
            );
            $st->bindValue(1, $user_id, \PDO::PARAM_INT);
            $st->execute();
            while ($row = $st->fetch(\PDO::FETCH_NUM)) {
                $res[$row[0]] = $row[1];
            }
            $st->closeCursor();
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to get a list of the settings', -1, $e);
        }
        return $res;
    }

    /**
     * Saves the setting to the database
     *
     * Updates the value of the setting in the database if the setting exists there or insert a new record otherwise.
     *
     * @param string $name    Setting name
     * @param string $value   Setting value
     * @param int    $user_id User Id to save the setting for
     *
     * @return void
     */
    public function save(string $name, string $value, int $user_id): void
    {
        $db = $this->connector->dbh();
        try {
            $st = $db->prepare(
                'INSERT INTO ' . $this->connector->tablePrefix('system') .
                ' ("key", user_id, value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = ?'
            );
            $st->bindValue(1, $name, \PDO::PARAM_STR);
            $st->bindValue(2, $user_id, \PDO::PARAM_INT);
            $st->bindValue(3, $value, \PDO::PARAM_STR);
            $st->bindValue(4, $value, \PDO::PARAM_STR);
            $st->execute();
            $st->closeCursor();
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to update a setting', -1, $e);
        }
    }
}
