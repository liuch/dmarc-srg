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
 *
 * =========================
 *
 * This file contains Admin class
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg;

use PDO;
use Exception;
use PDOException;
use Liuch\DmarcSrg\Mail\MailBoxes;
use Liuch\DmarcSrg\Database\Database;

/**
 * It's the main class for accessing administration functions.
 */
class Admin
{
    private $st = null;

    /**
     * Returns information about the database and mailboxes as an array.
     *
     * @return array Contains fields: `database`, `state`, `mailboxes`.
     */
    public function state(): array
    {
        $this->st = [ 'database' => [ 'tables' => [] ] ];
        try {
            $db = Database::connection();
            $db_tables = [];
            $st = $db->query('SHOW TABLE STATUS FROM `' . str_replace('`', '', Database::name()) . '`');
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $tnm = $row['Name'];
                $st2 = $db->query('SELECT COUNT(*) FROM `' . $tnm . '`');
                $rows = $st2->fetch(PDO::FETCH_NUM)[0];
                $db_tables[$tnm] = [
                    'engine'       => $row['Engine'],
                    'rows'         => intval($rows),
                    'data_length'  => intval($row['Data_length']),
                    'index_length' => intval($row['Index_length']),
                    'create_time'  => $row['Create_time'],
                    'update_time'  => $row['Update_time']
                ];
            }
            foreach (array_keys(Database::$schema) as $table) {
                if (!isset($db_tables[$table])) {
                    $db_tables[$table] = false;
                }
            }
            $exist_sys = false;
            $exist_cnt = 0;
            $absent_cnt = 0;
            $tables_res = [];
            foreach ($db_tables as $tname => $tval) {
                $t = null;
                if ($tval) {
                    $t = $tval;
                    $t['exists'] = true;
                    if (isset(Database::$schema[$tname])) {
                        $exist_cnt += 1;
                        $t['message'] = 'Ok';
                        if (!$exist_sys && $tname === 'system') {
                            $exist_sys = true;
                        }
                    } else {
                        $t['message'] = 'Unknown table';
                    }
                } else {
                    $absent_cnt += 1;
                    $t = [
                        'error_code' => 1,
                        'message'    => 'Not exist'
                    ];
                }
                $t['name'] = $tname;
                $tables_res[] = $t;
            }
            $this->st['database']['tables'] = $tables_res;
            $ver = $exist_sys ? Database::parameter('version') : null;
            if ($exist_sys && $ver !== Database::REQUIRED_VERSION) {
                $this->setDbMessage('The database structure needs upgrading', 0);
                $this->st['database']['needs_upgrade'] = true;
            } elseif ($absent_cnt == 0) {
                $this->st['state'] = 'Ok';
                $this->st['database']['correct'] = true;
                $this->setDbMessage('Ok', 0);
            } else {
                if ($absent_cnt !== 0) {
                    if ($exist_cnt == 0) {
                        $this->setDbMessage('The database schema is not initiated', -1);
                    } else {
                        $this->setDbMessage('Incomplete set of the tables', -2);
                    }
                }
            }
            if ($ver) {
                $this->st['database']['version'] = $ver;
            }
        } catch (Exception $e) {
            $this->st['database']['error_code'] = $e->getCode();
            $this->st['database']['message'] = $e->getMessage();
        }
        $this->st['database']['type'] = Database::type();
        $this->st['database']['name'] = Database::name();
        $this->st['database']['location'] = Database::location();
        if (!isset($this->st['state'])) {
            $this->st['state'] = 'Err';
        }

        // MailBoxes
        $mb = new MailBoxes();
        $this->st['mailboxes'] = $mb->list();

        return $this->st;
    }

    /**
     * Inites the database.
     *
     * This method creates needed tables and indexes in the database.
     * The method will fail if the database is not empty.
     *
     * @return array Result array with `error_code` and `message` fields.
     */
    public function initDb(): array
    {
        try {
            $db = Database::connection();
            $st = $db->query('SHOW TABLES');
            if ($st->fetch()) {
                throw new Exception('The database is not empty', -4);
            }
            foreach (array_keys(Database::$schema) as $table) {
                $this->createDbTable($table, Database::$schema[$table]);
            }
            $st = $db->prepare('INSERT INTO `system` (`key`, `value`) VALUES ("version", ?)');
            $st->bindValue(1, Database::REQUIRED_VERSION, PDO::PARAM_STR);
            $st->execute();
            $st->closeCursor();
        } catch (Exception $e) {
            return [
                'error_code' => $e->getCode(),
                'message'    => $e->getMessage()
            ];
        }
        return [ 'message' => 'The database has been initiated' ];
    }

    /**
     * Drops all tables in the database, thus clearing it up.
     *
     * @return array Result array with `error_code` and `message` fields.
     */
    public function dropTables(): array
    {
        try {
            $db = Database::connection();
            $db->query('SET foreign_key_checks = 0');
            $st = $db->query('SHOW TABLES');
            while ($table = $st->fetchColumn(0)) {
                $db->query('DROP TABLE `' . $table . '`');
            }
            $db->query('SET foreign_key_checks = 1');
        } catch (PDOException $e) {
            return [
                'error_code' => $e->getCode(),
                'message'    => $e->getMessage()
            ];
        }
        return [ 'message' => 'Database tables have been dropped' ];
    }

    /**
     * Checks the availability of report sources. So far these are only mailboxes.
     *
     * @param int    $id   Id of the checked source. If $id == 0 then all available
     *                     mailboxes will be checked.
     * @param string $type Type of the checked source.
     *                     So far it is only a `mailbox`.
     *
     * @return array Result array with `error_code` and `message` fields.
     *               For one resource and if there is no error,
     *               a field `status` will be added to the result.
     */
    public function checkSource(int $id, string $type): array
    {
        try {
            if ($type === 'mailbox') {
                return (new MailBoxes())->check($id);
            } else {
                throw new Exception('Unknown resource type', -1);
            }
        } catch (Exception $e) {
            return [
                'error_code' => $e->getCode(),
                'message'    => $e->getMessage()
            ];
        }
        return [ 'message' => 'Successfully' ];
    }

    /**
     * Creates a table in the database.
     *
     * @param string $name        Table name
     * @param array  $definitions Table structure
     *
     * @return void
     */
    private function createDbTable(string $name, array $definitions): void
    {
        $query = 'CREATE TABLE `' . $name . '` (';
        $col_num = 0;
        foreach ($definitions['columns'] as $column) {
            if ($col_num > 0) {
                $query .= ', ';
            }
            $query .= '`' . $column['name'] . '` ' . $column['definition'];
            $col_num += 1;
        }
        $query .= ', ' . $definitions['additional'] . ') ' . $definitions['table_options'];
        Database::connection()->query($query);
    }

    /**
     * Sets the database message and error code for the result array
     *
     * @param string $message  Message string
     * @param int    $err_code Error code
     *
     * @return void
     */
    private function setDbMessage(string $message, int $err_code): void
    {
        $this->st['database']['message'] = $message;
        if ($err_code !== 0) {
            $this->st['database']['error_code'] = $err_code;
        }
    }
}

