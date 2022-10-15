<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2020 Aleksey Andreev (liuch)
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
 */

namespace Liuch\DmarcSrg\Database;

use Liuch\DmarcSrg\Settings\SettingString;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\DatabaseException;
use Liuch\DmarcSrg\Exception\DatabaseExceptionFactory;

class DatabaseUpgrader
{
    public static function go()
    {
        $ver = (new SettingString('version'))->value();
        if ($ver == '') {
            $ver = 'null';
        }

        while ($ver !== Database::REQUIRED_VERSION) {
            if (!isset(self::$upways['ver_' . $ver])) {
                throw new SoftException(
                    "Upgrading failed: There is no way to upgrade from {$ver} to " . Database::REQUIRED_VERSION
                );
            }
            $um = self::$upways['ver_' . $ver];
            $ver = self::$um();
        }
    }

    private static $upways = [
        'ver_null' => 'upNull',
        'ver_0.1'  => 'up01',
        'ver_1.0'  => 'up10'
    ];

    private static function upNull()
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $db->query(
                'INSERT INTO `' . Database::tablePrefix('system') . '` (`key`, `value`) VALUES ("version", "0.1")'
            );
            $db->commit();
        } catch (\PDOException $e) {
            $db->rollBack();
            throw DatabaseExceptionFactory::fromException($e);
        }
        return '0.1';
    }

    private static function up01()
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $dom_tn = Database::tablePrefix('domains');
            if (!self::columnExists($db, $dom_tn, 'active')) {
                $db->query('ALTER TABLE `' . $dom_tn . '` ADD COLUMN `active` boolean NOT NULL AFTER `fqdn`');
            }
            if (!self::columnExists($db, $dom_tn, 'created_time')) {
                $db->query('ALTER TABLE `' . $dom_tn . '` ADD COLUMN `created_time` datetime NOT NULL');
            }
            if (!self::columnExists($db, $dom_tn, 'updated_time')) {
                $db->query('ALTER TABLE `' . $dom_tn . '` ADD COLUMN `updated_time` datetime NOT NULL');
            }
            $db->query('UPDATE `' . $dom_tn . '` SET `active` = TRUE, `created_time` = NOW(), `updated_time` = NOW()');
            $db->query('UPDATE `' . Database::tablePrefix('system') . '` SET `value` = "1.0" WHERE `key` = "version"');
            $db->commit();
        } catch (\PDOException $e) {
            $db->rollBack();
            throw DatabaseExceptionFactory::fromException($e);
        }
        return '1.0';
    }

    private static function up10()
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $sys_tn = Database::tablePrefix('system');
            $db->query('ALTER TABLE `' . $sys_tn . '` MODIFY COLUMN `key` varchar(64) NOT NULL');
            $db->query('UPDATE `' . $sys_tn . '` SET `value` = "2.0" WHERE `key` = "version"');
            $db->commit();
        } catch (\PDOException $e) {
            $db->rollBack();
            throw DatabaseExceptionFactory::fromException($e);
        }
        return '2.0';
    }

    private static function columnExists($db, $table, $column)
    {
        $st = $db->prepare(
            'SELECT NULL FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `table_schema` = ? AND `table_name` = ? AND `column_name` = ?'
        );
        $st->bindValue(1, Database::name(), \PDO::PARAM_STR);
        $st->bindValue(2, $table, \PDO::PARAM_STR);
        $st->bindValue(3, $column, \PDO::PARAM_STR);
        $st->execute();
        $res = $st->fetch(\PDO::FETCH_NUM);
        $st->closeCursor();
        return $res ? true : false;
    }
}
