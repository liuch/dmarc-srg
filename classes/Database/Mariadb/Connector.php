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
 * This file contains the DatabaseConnector class
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Database\Mariadb;

use Liuch\DmarcSrg\ErrorCodes;
use Liuch\DmarcSrg\ErrorHandler;
use Liuch\DmarcSrg\Database\DatabaseConnector;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\RuntimeException;
use Liuch\DmarcSrg\Exception\DatabaseFatalException;
use Liuch\DmarcSrg\Exception\DatabaseExceptionFactory;
use Liuch\DmarcSrg\Exception\DatabaseNotFoundException;

class Connector extends DatabaseConnector
{
    protected $dbh      = null;
    protected $ansiMode = true;

    /**
     * Returns an instance of PDO class
     *
     * @return \PDO
     */
    public function dbh(): object
    {
        $this->ensureConnection();
        return $this->dbh;
    }

    /**
     * Returns information about the database as an array.
     *
     * @return array May contain the following fields:
     *               `tables`        - an array of tables with their properties;
     *               `correct`       - true if the database is correct;
     *               `version`       - the current version of the database structure;
     *               `message`       - a state message;
     *               `error_code`    - an error code;
     */
    public function state(): array
    {
        $this->ensureConnection();

        $res = [];
        $p_len = strlen($this->prefix);
        if ($p_len > 0) {
            $like_str  = ' WHERE NAME LIKE "' . str_replace('_', '\\_', $this->prefix) . '%"';
        } else {
            $like_str  = '';
        }

        try {
            $this->setANSIMode(false);
            $tables = [];
            $st = $this->dbh->query(
                'SHOW TABLE STATUS FROM `' . str_replace('`', '', $this->name) . '`' . $like_str
            );
            while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
                $tname = $row['Name'];
                $rcnt  = $this->dbh->query('SELECT COUNT(*) FROM `' . $tname . '`')->fetch(\PDO::FETCH_NUM)[0];
                $tables[substr($tname, $p_len)] = [
                    'engine'       => $row['Engine'],
                    'rows'         => intval($rcnt),
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
            $exist_cnt  = 0;
            $absent_cnt = 0;
            $tables_res = [];
            $system_exs = false;
            foreach ($tables as $tname => $tval) {
                $t = null;
                if ($tval) {
                    $t = $tval;
                    $t['exists'] = true;
                    if (isset(self::$schema[$tname])) {
                        ++$exist_cnt;
                        $t['message'] = 'Ok';
                    } else {
                        $t['message'] = 'Unknown table';
                    }
                    if ($tname === 'system') {
                        $system_exs = true;
                    }
                } else {
                    ++$absent_cnt;
                    $t = [
                        'error_code' => 1,
                        'message'    => 'Not exist'
                    ];
                }
                $t['name'] = $tname;
                $tables_res[] = $t;
            }
            $res['tables']  = $tables_res;
            if ($absent_cnt === 0) {
                $res['correct'] = true;
                $res['message'] = 'Ok';
            } else {
                if ($exist_cnt == 0) {
                    $res['error_code'] = -1;
                    $res['message'] = 'The database schema is not initiated';
                } else {
                    $res['error_code'] = ErrorCodes::INCORRECT_TABLE_SET;
                    $res['message'] = 'Incomplete set of the tables';
                }
            }
            if ($system_exs) {
                try {
                    $this->setANSIMode(true);
                    $res['version'] = $this->getMapper('setting')->value('version', 0);
                    $this->setANSIMode(false);
                } catch (DatabaseNotFoundException $e) {
                }
            }
        } catch (\PDOException $e) {
            $res = array_replace($res, ErrorHandler::exceptionResult(
                new DatabaseFatalException('Failed to get the database information', -1, $e)
            ));
        } catch (RuntimeException $e) {
            $res = array_replace($res, ErrorHandler::exceptionResult($e));
        } finally {
            $this->setANSIMode(true);
        }
        return $res;
    }

    /**
     * Initiates the database.
     *
     * This method creates needed tables and indexes in the database.
     * The method will fail if the database already have tables with the table prefix.
     *
     * @param string $version The current version of the database schema
     *
     * @return void
     */
    public function initDb(string $version): void
    {
        $this->ensureConnection();
        try {
            $this->setANSIMode(false);
            $st = $this->dbh->query($this->sqlShowTablesQuery());
            try {
                if ($st->fetch()) {
                    if (empty($this->tablePrefix())) {
                        throw new SoftException('The database is not empty', ErrorCodes::DB_NOT_EMPTY);
                    } else {
                        throw new SoftException(
                            'Database tables already exist with the given prefix',
                            ErrorCodes::DB_NOT_EMPTY
                        );
                    }
                }
                foreach (self::$schema as $t_name => &$t_schema) {
                    $this->createDbTable($this->tablePrefix($t_name), $t_schema);
                }
                unset($t_schema);
            } finally {
                $st->closeCursor();
            }
            $st = $this->dbh->prepare(
                'INSERT INTO ' . $this->tablePrefix('system')
                . ' (`key`, user_id, value) VALUES ("version", 0, ?)'
            );
            $st->bindValue(1, $version, \PDO::PARAM_STR);
            $st->execute();
            $st->closeCursor();
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to create required tables in the database', -1, $e);
        } finally {
            $this->setANSIMode(true);
        }
    }

    /**
     * Cleans up the database
     *
     * Drops tables with the table prefix in the database or all tables in the database
     * if no table prefix is set.
     *
     * @return void
     */
    public function cleanDb(): void
    {
        $this->ensureConnection();
        try {
            $this->setANSIMode(false);
            $db = $this->dbh;
            $db->query('SET foreign_key_checks = 0');
            $st = $db->query($this->sqlShowTablesQuery());
            while ($table = $st->fetchColumn(0)) {
                $db->query('DROP TABLE `' . $table . '`');
            }
            $st->closeCursor();
            $db->query('SET foreign_key_checks = 1');
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to drop the database tables', -1, $e);
        } finally {
            $this->setANSIMode(true);
        }
    }

    /**
     * Enables or disables ANSI query mode for the current datatabase connection
     *
     * @param bool $on True turns ANSI mode on, False turns it off
     *
     * @return void
     */
    public function setANSIMode(bool $on): void
    {
        if ($on !== $this->ansiMode) {
            $this->ansiMode = $on;
            if ($this->dbh) {
                if ($on) {
                    $this->dbh->query('SET SESSION sql_mode=\'ANSI\'');
                } else {
                    $this->dbh->query('SET SESSION sql_mode=@prev_sql_mode');
                }
            }
        }
    }

    /**
     * Sets the database connection if it hasn't connected yet.
     *
     * @return void
     */
    private function ensureConnection(): void
    {
        if (!$this->dbh) {
            try {
                $this->dbh = new \PDO(
                    "mysql:host={$this->host};dbname={$this->name};charset=utf8",
                    $this->user,
                    $this->password,
                    [ \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION ]
                );
                $this->dbh->query('SET @prev_sql_mode=@@sql_mode');
                if ($this->ansiMode) {
                    $this->dbh->query('SET SESSION sql_mode=\'ANSI\'');
                }
                $this->dbh->query('SET time_zone = \'+00:00\'');
            } catch (\PDOException $e) {
                throw DatabaseExceptionFactory::fromException($e);
            }
        }
    }

    /**
     * Return SHOW TABLES SQL query string for tables with the table prefix
     *
     * @return string
     */
    private function sqlShowTablesQuery(): string
    {
        $res = 'SHOW TABLES';
        $prefix = $this->tablePrefix();
        if (strlen($prefix) > 0) {
            $res .= ' WHERE `tables_in_' . str_replace('`', '', $this->name)
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
        $this->dbh->query($query);
    }

    private static $schema = [
        'system' => [
            'columns' => [
                [
                    'name' => 'key',
                    'definition' => 'varchar(64) NOT NULL'
                ],
                [
                    'name' => 'user_id',
                    'definition' => 'int(10) unsigned NOT NULL DEFAULT 0'
                ],
                [
                    'name' => 'value',
                    'definition' => 'varchar(255) DEFAULT NULL'
                ]
            ],
            'additional' => 'PRIMARY KEY (user_id, `key`)',
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
            'additional' => 'PRIMARY KEY (id), UNIQUE KEY fqdn (fqdn)',
            'table_options' => 'ENGINE=InnoDB DEFAULT CHARSET=utf8'
        ],
        'users' => [
            'columns' => [
                [
                    'name' => 'id',
                    'definition' => 'int(10) unsigned NOT NULL AUTO_INCREMENT'
                ],
                [
                    'name' => 'name',
                    'definition' => 'varchar(32) NOT NULL'
                ],
                [
                    'name' => 'level',
                    'definition' => 'smallint unsigned NOT NULL'
                ],
                [
                    'name' => 'enabled',
                    'definition' => 'boolean NOT NULL'
                ],
                [
                    'name' => 'password',
                    'definition' => 'varchar(255) NULL'
                ],
                [
                    'name' => 'email',
                    'definition' => 'varchar(64) NULL'
                ],
                [
                    'name' => 'key',
                    'definition' => 'varchar(64) NULL'
                ],
                [
                    'name' => 'session',
                    'definition' => 'int(10) unsigned NOT NULL'
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
            'additional' => 'PRIMARY KEY (id), UNIQUE KEY name (name)',
            'table_options' => 'ENGINE=InnoDB DEFAULT CHARSET=utf8'
        ],
        'userdomains' => [
            'columns' => [
                [
                    'name' => 'domain_id',
                    'definition' => 'int(10) unsigned NOT NULL'
                ],
                [
                    'name' => 'user_id',
                    'definition' => 'int(10) unsigned NOT NULL'
                ]
            ],
            'additional' => 'PRIMARY KEY (domain_id, user_id)',
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
                    'name' => 'policy_np',
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
            'additional' => 'PRIMARY KEY (id),' .
                            ' UNIQUE KEY org_time_id_u (domain_id, begin_time, org, external_id),' .
                            ' KEY (begin_time), KEY (end_time),' .
                            ' KEY org (org, begin_time)',
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
            'additional' => 'PRIMARY KEY (id), KEY (report_id), KEY (ip)',
            'table_options' => 'ENGINE=InnoDB DEFAULT CHARSET=utf8'
        ],
        'reportlog' => [
            'columns' => [
                [
                    'name' => 'id',
                    'definition' => 'int(10) unsigned NOT NULL AUTO_INCREMENT'
                ],
                [
                    'name' => 'user_id',
                    'definition' => 'int(10) unsigned NOT NULL DEFAULT 0'
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
            'additional' => 'PRIMARY KEY (id), KEY(event_time), KEY user_id (user_id, event_time)',
            'table_options' => 'ENGINE=InnoDB DEFAULT CHARSET=utf8'
        ]
    ];
}
