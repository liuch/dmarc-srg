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

use PDO;
use Exception;

class DatabaseUpgrader
{
    public static function go()
    {
        $ver = Database::parameter('version');
        if ($ver == '') {
            $ver = 'null';
        }

        while ($ver !== Database::REQUIRED_VERSION) {
            if (!isset(self::$upways['ver_' . $ver])) {
                throw new Exception('Upgrading failed: There is no way to upgrade from ' . $ver . ' to ' . Database::REQUIRED_VERSION, -1);
            }
            $um = self::$upways['ver_' . $ver];
            $ver = self::$um();
        }
    }

    private static $upways = [
        'ver_null' => 'upNull',
        'ver_0.1'  => 'up01'
    ];

    private static function upNull()
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $db->query('INSERT INTO `system` (`key`, `value`) VALUES ("version", "0.1")');
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
        return '0.1';
    }

    private static function up01()
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            if (!self::columnExists($db, 'domains', 'active')) {
                $db->query('ALTER TABLE `domains` ADD COLUMN `active` boolean NOT NULL AFTER `fqdn`');
            }
            if (!self::columnExists($db, 'domains', 'created_time')) {
                $db->query('ALTER TABLE `domains` ADD COLUMN `created_time` datetime NOT NULL');
            }
            if (!self::columnExists($db, 'domains', 'updated_time')) {
                $db->query('ALTER TABLE `domains` ADD COLUMN `updated_time` datetime NOT NULL');
            }
            $db->query('UPDATE `domains` SET `active` = TRUE, `created_time` = NOW(), `updated_time` = NOW()');
            $db->query('UPDATE `system` SET `value` = "1.0" WHERE `key` = "version"');
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
        return '1.0';
    }

    private static function columnExists($db, $table, $column)
    {
        $st = $db->prepare('SELECT NULL FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `table_schema` = ? AND `table_name` = ? AND `column_name` = ?');
        $st->bindValue(1, Database::name(), PDO::PARAM_STR);
        $st->bindValue(2, $table, PDO::PARAM_STR);
        $st->bindValue(3, $column, PDO::PARAM_STR);
        $st->execute();
        $res = $st->fetch(PDO::FETCH_NUM);
        $st->closeCursor();
        return $res ? true : false;
    }
}

