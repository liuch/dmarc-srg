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
 * This file contains the UpgraderMapper class
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Database\Sqlite;

use Liuch\DmarcSrg\Database\UpgraderMapperInterface;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\DatabaseFatalException;
use Liuch\DmarcSrg\Exception\DatabaseNotFoundException;

/**
 * UpgraderMapper class implementation for MariaDB
 */
class UpgraderMapper implements UpgraderMapperInterface
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
     * Starts upgrading the database structure
     *
     * @param string $target Target version of the database structure to upgrade to
     *
     * @return void
     */
    public function go(string $target): void
    {
        try {
            $cur_ver = $this->connector->getMapper('setting')->value('version');
        } catch (DatabaseNotFoundException $e) {
            $cur_ver = 'null';
        }

        while ($cur_ver !== $target) {
            if (!isset(self::$upways['ver_' . $cur_ver])) {
                throw new SoftException(
                    "Upgrading failed: There is no way to upgrade from {$cur_ver} to {$target}"
                );
            }
            $um = self::$upways['ver_' . $cur_ver];
            $cur_ver = $this->$um();
        }
    }

    /**
     * Upgrades the database structure from None to 0.1
     *
     * @return string New version of the database structure
     */
    private function upNull(): string
    {
        $db = $this->connector->dbh();
        $db->beginTransaction();
        try {
            $db->query(
                'INSERT INTO ' . $this->connector->tablePrefix('system')
                . ' (key, value) VALUES ("version", "0.1")'
            );
            $db->commit();
        } catch (\PDOException $e) {
            $db->rollBack();
            throw $this->dbFatalException($e);
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
        return '0.1';
    }

    /**
     * Upgrades the database structure from 0.1 to 1.0
     *
     * @return string New version of the database structure
     */
    private function up01(): string
    {
        $db = $this->connector->dbh();
        // Transaction would be useful here but it doesn't work with ALTER TABLE in MySQL/MariaDB
        try {
            $dom_tn = $this->connector->tablePrefix('domains');
            if (!$this->columnExists($db, $dom_tn, 'active')) {
                $db->query(
                    'ALTER TABLE ' . $dom_tn . ' ADD COLUMN active INTEGER NOT NULL AFTER fqdn'
                );
            }
            if (!$this->columnExists($db, $dom_tn, 'created_time')) {
                $db->query(
                    'ALTER TABLE ' . $dom_tn . ' ADD COLUMN created_time TEXT NOT NULL'
                );
            }
            if (!$this->columnExists($db, $dom_tn, 'updated_time')) {
                $db->query(
                    'ALTER TABLE ' . $dom_tn . ' ADD COLUMN updated_time TEXT NOT NULL'
                );
            }
            $db->query(
                'UPDATE ' . $dom_tn . ' SET active = TRUE, created_time = NOW(), updated_time = NOW()'
            );
            $db->query(
                'UPDATE ' . $this->connector->tablePrefix('system') . ' SET value = "1.0" WHERE key = "version"'
            );
        } catch (\PDOException $e) {
            throw $this->dbFatalException($e);
        }
        return '1.0';
    }

    /**
     * Upgrades the database structure from 1.0 to 2.0
     *
     * @return string New version of the database structure
     */
    private function up10(): string
    {
        $db = $this->connector->dbh();
        // Transaction would be useful here but it doesn't work with ALTER TABLE in MySQL/MariaDB
        try {
            $sys_tn = $this->connector->tablePrefix('system');
            // $db->query(
            //     'ALTER TABLE ' . $sys_tn . ' MODIFY COLUMN key varchar(64) NOT NULL'
            // );
            $db->query(
                'UPDATE ' . $sys_tn . ' SET value = "2.0" WHERE key = "version"'
            );
        } catch (\PDOException $e) {
            throw $this->dbFatalException($e);
        }
        return '2.0';
    }

    /**
     * Checks if the spefied column exists in the spefied table of the database
     *
     * @param object $db     Connection handle of the database
     * @param string $table  Table name with the prefix
     * @param string $columb Column name
     *
     * @return bool
     */
    private function columnExists($db, string $table, string $column): bool
    {
        $st = $db->prepare(
            'PRAGMA table_info(?)'
        );
        $st->bindValue(2, $table, \PDO::PARAM_STR);
        $st->execute();
        $res = $st->fetch(\PDO::FETCH_ASSOC);
        $st->closeCursor();
        return $res ? true : false;
    }

    /**
     * Return an instance of DatabaseFatalException
     *
     * @param Exception $e The original exception
     *
     * @return DatabaseFatalException
     */
    private function dbFatalException($e)
    {
        return new DatabaseFatalException('Failed to upgrade the database structure', -1, $e);
    }

    private static $upways = [
        'ver_null' => 'upNull',
        'ver_0.1'  => 'up01',
        'ver_1.0'  => 'up10'
    ];
}
