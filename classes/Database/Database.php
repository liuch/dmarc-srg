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

use Liuch\DmarcSrg\ErrorHandler;
use Liuch\DmarcSrg\Settings\SettingString;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\RuntimeException;
use Liuch\DmarcSrg\Exception\DatabaseFatalException;
use Liuch\DmarcSrg\Exception\DatabaseExceptionFactory;

class Database
{
    public const REQUIRED_VERSION = '2.0';

    private $conn;
    private static $instance = null;

    private function __construct()
    {
        $this->conn = null;
        $this->establishConnection();
    }

    public static function connection()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance->conn;
    }

    public static function type()
    {
        global $database;
        return $database['type'];
    }

    public static function name()
    {
        global $database;
        return $database['name'];
    }

    public static function location()
    {
        global $database;
        return $database['host'];
    }

    /**
     * Returns the prefix for tables of the database
     *
     * @param string $postfix String to be concatenated with the prefix.
     *                        Usually, this is a table name.
     *
     * @return string
     */
    public static function tablePrefix(string $postfix = ''): string
    {
        global $database;
        return ($database['table_prefix'] ?? '') . $postfix;
    }

    /**
     * Returns information about the database as an array.
     *
     * @return array May contain the following fields:
     *               `tables`        - an array of tables with their properties;
     *               `needs_upgrade` - true if the database needs upgrading;
     *               `correct`       - true if the database is correct;
     *               `type`          - the database type;
     *               `name`          - the database name;
     *               `location`      - the database location;
     *               `version`       - the current version of the database structure;
     *               `message`       - a state message;
     *               `error_code`    - an error code;
     */
    public static function state(): array
    {
        $res = [];
        try {
            $prefix = self::tablePrefix();
            $p_len = strlen($prefix);
            if ($p_len > 0) {
                $like_str  = ' WHERE NAME LIKE "' . str_replace('_', '\\_', $prefix) . '%"';
            } else {
                $like_str  = '';
            }
            $db = self::connection();
            $tables = [];
            $st = $db->query(
                'SHOW TABLE STATUS FROM `' . str_replace('`', '', self::name()) . '`' . $like_str
            );
            while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
                $tnm = $row['Name'];
                $st2 = $db->query('SELECT COUNT(*) FROM `' . $tnm . '`');
                $rows = $st2->fetch(\PDO::FETCH_NUM)[0];
                $tables[substr($tnm, $p_len)] = [
                    'engine'       => $row['Engine'],
                    'rows'         => intval($rows),
                    'data_length'  => intval($row['Data_length']),
                    'index_length' => intval($row['Index_length']),
                    'create_time'  => $row['Create_time'],
                    'update_time'  => $row['Update_time']
                ];
            }
            foreach (array_keys(self::$schema) as $table) {
                if (!isset($tables[$table])) {
                    $tables[$table] = false;
                }
            }
            $exist_sys = false;
            $exist_cnt = 0;
            $absent_cnt = 0;
            $tables_res = [];
            foreach ($tables as $tname => $tval) {
                $t = null;
                if ($tval) {
                    $t = $tval;
                    $t['exists'] = true;
                    if (isset(self::$schema[$tname])) {
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
            $res['tables'] = $tables_res;
            $ver = $exist_sys ? (new SettingString('version'))->value() : null;
            if ($exist_sys && $ver !== self::REQUIRED_VERSION) {
                self::setDbMessage('The database structure needs upgrading', 0, $res);
                $res['needs_upgrade'] = true;
            } elseif ($absent_cnt == 0) {
                $res['correct'] = true;
                self::setDbMessage('Ok', 0, $res);
            } else {
                if ($exist_cnt == 0) {
                    self::setDbMessage('The database schema is not initiated', -1, $res);
                } else {
                    self::setDbMessage('Incomplete set of the tables', -1, $res);
                }
            }
            if ($ver) {
                $res['version'] = $ver;
            }
        } catch (\PDOException $e) {
            $res = array_replace($res, ErrorHandler::exceptionResult(
                new DatabaseFatalException('Failed to get the database information', -1, $e)
            ));
        } catch (RuntimeException $e) {
            $res = array_replace($res, ErrorHandler::exceptionResult($e));
        }
        $res['type']     = self::type();
        $res['name']     = self::name();
        $res['location'] = self::location();

        return $res;
    }

    /**
     * Inites the database.
     *
     * This method creates needed tables and indexes in the database.
     * The method will fail if the database already have tables with the table prefix.
     *
     * @return array Result array with `error_code` and `message` fields.
     */
    public static function initDb(): array
    {
        try {
            $db = self::connection();
            $st = $db->query(self::sqlShowTablesQuery());
            try {
                if ($st->fetch()) {
                    if (empty(self::tablePrefix())) {
                        throw new SoftException('The database is not empty', -4);
                    } else {
                        throw new SoftException('Database tables already exist with the given prefix', -4);
                    }
                }
                foreach (array_keys(self::$schema) as $table) {
                    self::createDbTable(self::tablePrefix($table), self::$schema[$table]);
                }
            } finally {
                $st->closeCursor();
            }
            $st = $db->prepare(
                'INSERT INTO `' . self::tablePrefix('system') . '` (`key`, `value`) VALUES ("version", ?)'
            );
            $st->bindValue(1, self::REQUIRED_VERSION, \PDO::PARAM_STR);
            $st->execute();
            $st->closeCursor();
        } catch (\PDOException $e) {
            new DatabaseFatalException('Failed to create required tables in the database', -1, $e);
        } catch (RuntimeException $e) {
            return ErrorHandler::exceptionResult($e);
        }
        return [ 'message' => 'The database has been initiated' ];
    }

    /**
     * Cleans up the database.
     *
     * Drops tables with the table prefix in the database or all tables in the database if no table prefix is set.
     *
     * @return array Result array with `error_code` and `message` fields.
     */
    public static function dropTables(): array
    {
        try {
            $db = self::connection();
            $db->query('SET foreign_key_checks = 0');
            $st = $db->query(self::sqlShowTablesQuery());
            while ($table = $st->fetchColumn(0)) {
                $db->query('DROP TABLE `' . $table . '`');
            }
            $st->closeCursor();
            $db->query('SET foreign_key_checks = 1');
        } catch (\PDOException $e) {
            new DatabaseFatalException('Failed to drop the database tables', -1, $e);
        }
        return [ 'message' => 'The database tables have been dropped' ];
    }

    private function establishConnection()
    {
        global $database;
        try {
            $dsn = "{$database['type']}:host={$database['host']};dbname={$database['name']};charset=utf8";
            $this->conn = new \PDO(
                $dsn,
                $database['user'],
                $database['password'],
                [ \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION ]
            );
            $this->conn->query('SET time_zone = "+00:00"');
        } catch (\PDOException $e) {
            throw DatabaseExceptionFactory::fromException($e);
        }
    }

    /**
     * Return SHOW TABLES SQL query string for tables with the table prefix
     *
     * @return string
     */
    private static function sqlShowTablesQuery(): string
    {
        $res = 'SHOW TABLES';
        $prefix = self::tablePrefix();
        if (strlen($prefix) > 0) {
            $res .= ' WHERE `tables_in_' . str_replace('`', '', self::name())
                . '` LIKE "' . str_replace('_', '\\_', $prefix) . '%"';
        }
        return $res;
    }

    /**
     * Creates a table in the database.
     *
     * @param string $name        Table name
     * @param array  $definitions Table structure
     *
     * @return void
     */
    private static function createDbTable(string $name, array $definitions): void
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
        self::connection()->query($query);
    }

    /**
     * Sets the database message and error code for the state array
     *
     * @param string $message  Message string
     * @param int    $err_code Error code
     * @param array  $state    Database state array
     *
     * @return void
     */
    private static function setDbMessage(string $message, int $err_code, array &$state): void
    {
        $state['message'] = $message;
        if ($err_code !== 0) {
            $state['error_code'] = $err_code;
        }
    }

    private static $schema = [
        'system' => [
            'columns' => [
                [
                    'name' => 'key',
                    'definition' => 'varchar(64) NOT NULL'
                ],
                [
                    'name' => 'value',
                    'definition' => 'varchar(255) DEFAULT NULL'
                ]
            ],
            'additional' => 'PRIMARY KEY (`key`)',
            'table_options' => 'ENGINE=InnoDB DEFAULT CHARSET=utf8'
        ],
        'domains' => [
            'columns' => [
                [
                    'name' => 'id',
                    'definition' => 'int(10) unsigned NOT NULL AUTO_INCREMENT'
                ],
                [
                    'name' => 'fqdn',
                    'definition' => 'varchar(255) NOT NULL'
                ],
                [
                    'name' => 'active',
                    'definition' => 'boolean NOT NULL'
                ],
                [
                    'name' => 'description',
                    'definition' => 'TEXT NULL'
                ],
                [
                    'name' => 'created_time',
                    'definition' => 'datetime NOT NULL'
                ],
                [
                    'name' => 'updated_time',
                    'definition' => 'datetime NOT NULL'
                ]
            ],
            'additional' => 'PRIMARY KEY (`id`), UNIQUE KEY `fqdn` (`fqdn`)',
            'table_options' => 'ENGINE=InnoDB DEFAULT CHARSET=utf8'
        ],
        'reports' => [
            'columns' => [
                [
                    'name' => 'id',
                    'definition' => 'int(10) unsigned NOT NULL AUTO_INCREMENT'
                ],
                [
                    'name' => 'domain_id',
                    'definition' => 'int(10) NOT NULL'
                ],
                [
                    'name' => 'begin_time',
                    'definition' => 'datetime NOT NULL'
                ],
                [
                    'name' => 'end_time',
                    'definition' => 'datetime NOT NULL'
                ],
                [
                    'name' => 'loaded_time',
                    'definition' => 'datetime NOT NULL'
                ],
                [
                    'name' => 'org',
                    'definition' => 'varchar(255) NOT NULL'
                ],
                [
                    'name' => 'external_id',
                    'definition' => 'varchar(255) NOT NULL'
                ],
                [
                    'name' => 'email',
                    'definition' => 'varchar(255) NOT NULL'
                ],
                [
                    'name' => 'extra_contact_info',
                    'definition' => 'varchar(255) NULL'
                ],
                [
                    'name' => 'error_string',
                    'definition' => 'text NULL'
                ],
                [
                    'name' => 'policy_adkim',
                    'definition' => 'varchar(20) NULL'
                ],
                [
                    'name' => 'policy_aspf',
                    'definition' => 'varchar(20) NULL'
                ],
                [
                    'name' => 'policy_p',
                    'definition' => 'varchar(20) NULL'
                ],
                [
                    'name' => 'policy_sp',
                    'definition' => 'varchar(20) NULL'
                ],
                [
                    'name' => 'policy_pct',
                    'definition' => 'varchar(20) NULL'
                ],
                [
                    'name' => 'policy_fo',
                    'definition' => 'varchar(20) NULL'
                ],
                [
                    'name' => 'seen',
                    'definition' => 'boolean NOT NULL'
                ]
            ],
            'additional' => 'PRIMARY KEY (`id`), UNIQUE KEY `external_id` (`domain_id`, `external_id`), KEY (`begin_time`), KEY (`end_time`), KEY `org` (`org`, `begin_time`)',
            'table_options' => 'ENGINE=InnoDB DEFAULT CHARSET=utf8'
        ],
        'rptrecords' => [
            'columns' => [
                [
                    'name' => 'id',
                    'definition' => 'int(10) unsigned NOT NULL AUTO_INCREMENT'
                ],
                [
                    'name' => 'report_id',
                    'definition' => 'int(10) unsigned NOT NULL'
                ],
                [
                    'name' => 'ip',
                    'definition' => 'varbinary(16) NOT NULL'
                ],
                [
                    'name' => 'rcount',
                    'definition' => 'int(10) unsigned NOT NULL'
                ],
                [
                    'name' => 'disposition',
                    'definition' => 'tinyint unsigned NOT NULL'
                ],
                [
                    'name' => 'reason',
                    'definition' => 'text NULL'
                ],
                [
                    'name' => 'dkim_auth',
                    'definition' => 'text NULL'
                ],
                [
                    'name' => 'spf_auth',
                    'definition' => 'text NULL'
                ],
                [
                    'name' => 'dkim_align',
                    'definition' => 'tinyint unsigned NOT NULL'
                ],
                [
                    'name' => 'spf_align',
                    'definition' => 'tinyint unsigned NOT NULL'
                ],
                [
                    'name' => 'envelope_to',
                    'definition' => 'varchar(255) NULL'
                ],
                [
                    'name' => 'envelope_from',
                    'definition' => 'varchar(255) NULL'
                ],
                [
                    'name' => 'header_from',
                    'definition' => 'varchar(255) NULL'
                ]
            ],
            'additional' => 'PRIMARY KEY (`id`), KEY (`report_id`), KEY (`ip`)',
            'table_options' => 'ENGINE=InnoDB DEFAULT CHARSET=utf8'
        ],
        'reportlog' => [
            'columns' => [
                [
                    'name' => 'id',
                    'definition' => 'int(10) unsigned NOT NULL AUTO_INCREMENT'
                ],
                [
                    'name' => 'domain',
                    'definition' => 'varchar(255) NULL'
                ],
                [
                    'name' => 'external_id',
                    'definition' => 'varchar(255) NULL'
                ],
                [
                    'name' => 'event_time',
                    'definition' => 'datetime NOT NULL'
                ],
                [
                    'name' => 'filename',
                    'definition' => 'varchar(255) NULL'
                ],
                [
                    'name' => 'source',
                    'definition' => 'tinyint unsigned NOT NULL'
                ],
                [
                    'name' => 'success',
                    'definition' => 'boolean NOT NULL'
                ],
                [
                    'name' => 'message',
                    'definition' => 'text NULL'
                ]
            ],
            'additional' => 'PRIMARY KEY (`id`), KEY(`event_time`)',
            'table_options' => 'ENGINE=InnoDB DEFAULT CHARSET=utf8'
        ]
    ];
}
