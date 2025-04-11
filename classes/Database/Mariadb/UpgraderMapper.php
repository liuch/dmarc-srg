<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2022-2023 Aleksey Andreev (liuch)
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

namespace Liuch\DmarcSrg\Database\Mariadb;

use Liuch\DmarcSrg\Database\UpgraderMapperInterface;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\DatabaseFatalException;
use Liuch\DmarcSrg\Exception\DatabaseNotFoundException;

/**
 * UpgraderMapper class implementation for MariaDB
 */
class UpgraderMapper implements UpgraderMapperInterface
{
    /** @var Connector */
    private $connector = null;

    /**
     * The constructor
     *
     * @param Connector $connector
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
            $cur_ver = $this->connector->getMapper('setting')->value('version', 0);
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
            $this->connector->setANSIMode(false);
            $db->query(
                'INSERT INTO ' . $this->connector->tablePrefix('system')
                . ' (`key`, value) VALUES ("version", "0.1")'
            );
            $db->commit();
        } catch (\PDOException $e) {
            $db->rollBack();
            throw $this->dbFatalException($e);
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        } finally {
            $this->connector->setANSIMode(true);
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
            $this->connector->setANSIMode(false);
            $dom_tn = $this->connector->tablePrefix('domains');
            if (!$this->columnExists($db, $dom_tn, 'active')) {
                $db->query(
                    'ALTER TABLE ' . $dom_tn . ' ADD COLUMN active boolean NOT NULL AFTER fqdn'
                );
            }
            if (!$this->columnExists($db, $dom_tn, 'created_time')) {
                $db->query(
                    'ALTER TABLE ' . $dom_tn . ' ADD COLUMN created_time datetime NOT NULL'
                );
            }
            if (!$this->columnExists($db, $dom_tn, 'updated_time')) {
                $db->query(
                    'ALTER TABLE ' . $dom_tn . ' ADD COLUMN updated_time datetime NOT NULL'
                );
            }
            $db->query(
                'UPDATE ' . $dom_tn . ' SET active = TRUE, created_time = NOW(), updated_time = NOW()'
            );
            $db->query(
                'UPDATE ' . $this->connector->tablePrefix('system') . ' SET value = "1.0" WHERE `key` = "version"'
            );
        } catch (\PDOException $e) {
            throw $this->dbFatalException($e);
        } finally {
            $this->connector->setANSIMode(true);
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
            $this->connector->setANSIMode(false);
            $sys_tn = $this->connector->tablePrefix('system');
            $db->query(
                'ALTER TABLE ' . $sys_tn . ' MODIFY COLUMN `key` varchar(64) NOT NULL'
            );
            $db->query(
                'UPDATE ' . $sys_tn . ' SET value = "2.0" WHERE `key` = "version"'
            );
        } catch (\PDOException $e) {
            throw $this->dbFatalException($e);
        } finally {
            $this->connector->setANSIMode(true);
        }
        return '2.0';
    }

    /**
     * Upgrades the database structure from v2.0 to v3.0
     *
     * @return string New version of the database structure
     */
    private function up20(): string
    {
        $db = $this->connector->dbh();
        // Transaction would be useful here but it doesn't work with ALTER TABLE in MySQL/MariaDB
        try {
            $this->connector->setANSIMode(false);
            $rep_tn = $this->connector->tablePrefix('reports');
            if (!$this->columnExists($db, $rep_tn, 'policy_np')) {
                $db->query(
                    'ALTER TABLE ' . $rep_tn . ' ADD COLUMN policy_np varchar(20) NULL AFTER policy_sp'
                );
            }
            $sys_tn = $this->connector->tablePrefix('system');
            $db->query(
                'UPDATE ' . $sys_tn . ' SET value = "3.0" WHERE `key` = "version"'
            );
        } catch (\PDOException $e) {
            throw $this->dbFatalException($e);
        } finally {
            $this->connector->setANSIMode(true);
        }
        return '3.0';
    }

    /**
     * Upgrades the database structure from v3.0 to v3.1
     *
     * @return string New version of the database
     */
    private function up30(): string
    {
        $db = $this->connector->dbh();
        try {
            $this->connector->setANSIMode(false);
            $rep_tn = $this->connector->tablePrefix('reports');
            if (!$this->indexExists($db, $rep_tn, 'org_time_id')) {
                $db->query(
                    'CREATE INDEX org_time_id ON ' . $rep_tn . ' (domain_id, begin_time, org, external_id)'
                );
            }
            if ($this->indexExists($db, $rep_tn, 'external_id')) {
                $db->query(
                    'DROP INDEX external_id ON ' . $rep_tn
                );
            }
            $sys_tn = $this->connector->tablePrefix('system');
            $db->query(
                'UPDATE ' . $sys_tn . ' SET value = "3.1" WHERE `key` = "version"'
            );
        } catch (\PDOException $e) {
            throw $this->dbFatalException($e);
        } finally {
            $this->connector->setANSIMode(true);
        }
        return '3.1';
    }

    /**
     * Upgrades the database structure from v3.1 to v3.2
     *
     * @return string New version of the database structure
     */
    private function up31(): string
    {
        $db = $this->connector->dbh();
        try {
            $this->connector->setANSIMode(false);
            $rep_tn = $this->connector->tablePrefix('reports');
            // Remove duplicates
            $db->query(
                'CREATE TEMPORARY TABLE up31_ids AS (SELECT MIN(id) AS id FROM ' . $rep_tn
                . ' GROUP BY domain_id, begin_time, org, external_id)'
            );
            $db->query('DELETE FROM ' . $rep_tn . ' WHERE id NOT IN (SELECT id FROM up31_ids)');
            // Create a new unique index
            if (!$this->indexExists($db, $rep_tn, 'org_time_id_u')) {
                $db->query(
                    'CREATE UNIQUE INDEX org_time_id_u ON ' . $rep_tn
                    . ' (domain_id, begin_time, org, external_id)'
                );
            }
            // Remove the old index
            if ($this->indexExists($db, $rep_tn, 'org_time_id')) {
                $db->query(
                    'DROP INDEX org_time_id ON ' . $rep_tn
                );
            }
            // Update version
            $sys_tn = $this->connector->tablePrefix('system');
            $db->query(
                'UPDATE ' . $sys_tn . ' SET value = "3.2" WHERE `key` = "version"'
            );
        } catch (\PDOException $e) {
            throw $this->dbFatalException($e);
        } finally {
            $this->connector->setANSIMode(true);
        }
        return '3.2';
    }

    /**
     * Upgrades the database structure from v3.2 to v4.0
     *
     * @return string New version of the database structure
     */
    private function up32(): string
    {
        $db = $this->connector->dbh();
        try {
            $this->connector->setANSIMode(false);
            $usr_tn = $this->connector->tablePrefix('users');
            $db->query(
                'CREATE TABLE IF NOT EXISTS ' . $usr_tn . ' (id int(10) unsigned NOT NULL AUTO_INCREMENT,'
                . ' name varchar(32) NOT NULL, level smallint unsigned NOT NULL, enabled boolean NOT NULL,'
                . ' password varchar(255) NULL, email varchar(64) NULL, `key` varchar(64) NULL,'
                . ' session int(10) NOT NULL, created_time datetime NOT NULL, updated_time datetime NOT NULL,'
                . ' PRIMARY KEY (id), UNIQUE KEY name (name)) ENGINE=InnoDB DEFAULT CHARSET=utf8'
            );

            $ud_tn = $this->connector->tablePrefix('userdomains');
            $db->query(
                'CREATE TABLE IF NOT EXISTS ' . $ud_tn . ' (domain_id int(10) unsigned NOT NULL,'
                . ' user_id int(10) unsigned NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8'
            );
            if (!$this->indexExists($db, $ud_tn, 'PRIMARY')) {
                $db->query('ALTER TABLE ' . $ud_tn . ' ADD PRIMARY KEY (domain_id, user_id)');
            }

            $sys_tn = $this->connector->tablePrefix('system');
            if (!$this->columnExists($db, $sys_tn, 'user_id')) {
                $db->query(
                    'ALTER TABLE ' . $sys_tn . ' ADD COLUMN user_id int(10) NOT NULL DEFAULT 0 AFTER `key`'
                );
            }
            $db->query('UPDATE ' . $sys_tn . ' SET user_id = 0');
            if ($this->indexExists($db, $sys_tn, 'PRIMARY')) {
                $db->query('ALTER TABLE ' . $sys_tn . ' DROP PRIMARY KEY');
            }
            $db->query('ALTER TABLE ' . $sys_tn . ' ADD PRIMARY KEY (user_id, `key`)');

            $log_tn = $this->connector->tablePrefix('reportlog');
            if (!$this->columnExists($db, $log_tn, 'user_id')) {
                $db->query(
                    'ALTER TABLE ' . $log_tn . ' ADD COLUMN user_id int(10) NOT NULL DEFAULT 0 AFTER id'
                );
            }
            if (!$this->indexExists($db, $log_tn, 'user_id')) {
                $db->query('ALTER TABLE ' . $log_tn . ' ADD KEY user_id (user_id, event_time)');
            }

            $db->query(
                'UPDATE ' . $sys_tn . ' SET value = "4.0" WHERE user_id = 0 AND `key` = "version"'
            );
        } catch (\PDOException $e) {
            throw $this->dbFatalException($e);
        } finally {
            $this->connector->setANSIMode(true);
        }
        return '4.0';
    }

    /**
     * Checks if the specified column exists in the specified table of the database
     *
     * @param object $db     Connection handle of the database
     * @param string $table  Table name with the prefix
     * @param string $column Column name
     *
     * @return bool
     */
    private function columnExists($db, string $table, string $column): bool
    {
        $st = $db->prepare(
            'SELECT NULL FROM INFORMATION_SCHEMA.COLUMNS'
            . ' WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
        );
        $st->bindValue(1, $table, \PDO::PARAM_STR);
        $st->bindValue(2, $column, \PDO::PARAM_STR);
        $st->execute();
        $res = $st->fetch(\PDO::FETCH_NUM);
        $st->closeCursor();
        return $res ? true : false;
    }

    /**
     * Checks if the specified index exists in the specified table of the database
     *
     * @param object $db    Database connection handle
     * @param string $table Table name with the prefix
     * @param string $index Index name to check
     *
     * @return bool
     */
    private function indexExists($db, string $table, string $index): bool
    {
        $st = $db->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS'
            . ' WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1'
        );
        $st->bindValue(1, $table, \PDO::PARAM_STR);
        $st->bindValue(2, $index, \PDO::PARAM_STR);
        $st->execute();
        $res = $st->fetch(\PDO::FETCH_NUM);
        $st->closeCursor();
        return $res ? true : false;
    }

    /**
     * Return an instance of DatabaseFatalException
     *
     * @param \Exception $e The original exception
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
        'ver_1.0'  => 'up10',
        'ver_2.0'  => 'up20',
        'ver_3.0'  => 'up30',
        'ver_3.1'  => 'up31',
        'ver_3.2'  => 'up32'
    ];
}
