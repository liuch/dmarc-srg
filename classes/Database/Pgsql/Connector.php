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
 * This file contains the DatabaseConnector class
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Database\Pgsql;

use Liuch\DmarcSrg\ErrorHandler;
use Liuch\DmarcSrg\Database\DatabaseConnector;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\RuntimeException;
use Liuch\DmarcSrg\Exception\DatabaseFatalException;
use Liuch\DmarcSrg\Exception\DatabaseExceptionFactory;
use Liuch\DmarcSrg\Exception\DatabaseNotFoundException;

class Connector extends DatabaseConnector
{
    protected $dbh = null;

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
     *               tables        - an array of tables with their properties;
     *               correct       - true if the database is correct;
     *               version       - the current version of the database structure;
     *               message       - a state message;
     *               error_code    - an error code;
     */
    public function state(): array
    {
        $this->ensureConnection();

        $res = [];
        $p_len = strlen($this->prefix);
        if ($p_len > 0) {
            $like_str  = ' WHERE tablename LIKE "' . str_replace('_', '\\_', $this->prefix) . '%"';
        } else {
            $like_str  = '';
        }

        try {
            $tables = [];
            $st = $this->dbh->query(
//                'SELECT * FROM pg_catalog.pg_tables ' . str_replace('', '', $this->name) . '' . $like_str
                "SELECT * FROM pg_catalog.pg_tables WHERE schemaname='public'"
            );
            while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
                $tname = $row['tablename'];
                $rcnt  = $this->dbh->query('SELECT COUNT(*) FROM "' . $tname . '"')->fetch(\PDO::FETCH_NUM)[0];
                $tables[substr($tname, $p_len)] = [
                    //'engine'       => $row['Engine'],
                    'rows'         => intval($rcnt),
                    //'data_length'  => intval($row['Data_length']),
                    //'index_length' => intval($row['Index_length']),
                    //'create_time'  => $row['Create_time'],
                    //'update_time'  => $row['Update_time']
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
                    $res['error_code'] = -4;
                    $res['message'] = 'Incomplete set of the tables';
                }
            }
            if ($system_exs) {
                try {
                    $res['version'] = $this->getMapper('setting')->value('version', 0);
                } catch (DatabaseNotFoundException $e) {
                }
            }
        } catch (\PDOException $e) {
            $res = array_replace($res, ErrorHandler::exceptionResult(
                new DatabaseFatalException('Failed to get the database information', -1, $e)
            ));
        } catch (RuntimeException $e) {
            $res = array_replace($res, ErrorHandler::exceptionResult($e));
        }
        return $res;
    }

    /**
     * Initiates the database.
     *
     * This method creates needed tables and indexes in the database.
     * The method will fail if the database already have tables with the table prefix.
     *
     * @param $version The current version of the database schema
     *
     * @return void
     */
    public function initDb(string $version): void
    {
        $this->ensureConnection();
        try {
            $st = $this->dbh->query($this->sqlShowTablesQuery());
            try {
                if ($st->fetch()) {
                    if (empty($this->tablePrefix())) {
                        throw new SoftException('The database is not empty', -4);
                    } else {
                        throw new SoftException('Database tables already exist with the given prefix', -4);
                    }
                }
                foreach (self::$schema as $t_name => &$t_schema) {
                    $this->createDbTable($this->tablePrefix($t_name), $t_schema);
                    $this->createDbIndexes($this->tablePrefix($t_name), $t_schema);
                }
                unset($t_schema);
            } finally {
                $st->closeCursor();
            }
            $st = $this->dbh->prepare(
                'INSERT INTO ' . $this->tablePrefix('system')
                . ' (key, user_id, value) VALUES (\'version\', 0, ?)'
            );
            $st->bindValue(1, $version, \PDO::PARAM_STR);
            $st->execute();
            $st->closeCursor();
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to create required tables in the database', -1, $e);
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
            $db = $this->dbh;
            $st = $db->query($this->sqlShowTablesQuery());
            while ($table = $st->fetchColumn(2)) {
                $db->query('DROP TABLE ' . $table . ' CASCADE');
            }
            $st->closeCursor();
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to drop the database tables', -1, $e);
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
                    "pgsql:host={$this->host};dbname={$this->name}",
                    $this->user,
                    $this->password,
                    [ \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION ]
                );
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
        $res = 'SELECT * FROM information_schema.tables where table_schema=\'public\'';
        $prefix = $this->tablePrefix();
        if (strlen($prefix) > 0) {
            $res .= ' AND table_name LIKE \'' . str_replace('_', '\\_', $prefix) . '%\'';
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
        $query = 'CREATE TABLE ' . $name . ' (';
        $col_num = 0;
        foreach ($definitions['columns'] as $column) {
            if ($col_num > 0) {
                $query .= ', ';
            }
            $query .= $column['name'] . ' ' . $column['definition'];
            $col_num += 1;
        }
        $query .= ', ' . $definitions['additional'] . ') ' . $definitions['table_options'];
        $this->dbh->query($query);
    }


    /**
     * Creates all indexes on a table.
     *
     * @param string $name        Table name
     * @param array  $definitions Table structure
     *
     * @return void
     */    private function createDbIndexes(string $name, array $definitions): void
    {
        if (isset($definitions['indexes']) && is_array($definitions['indexes'])){
            foreach ($definitions['indexes'] as $index) {
                print_r($index);
                // Autonaming by default
                $query = 'CREATE ' . ((isset($index['unique']) && $index['unique'])? 'UNIQUE' : '') . ' INDEX ON ' . $name . '(' . $index['columns'] . ')';
                $this->dbh->query($query);
            }
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
                    'name' => 'user_id',
                    'definition' => 'integer NOT NULL DEFAULT 0'
                ],
                [
                    'name' => 'value',
                    'definition' => 'varchar(255) DEFAULT NULL'
                ]
            ],
            'additional' => 'PRIMARY KEY (user_id, key)',
            'table_options' => ''
        ],
        'domains' => [
            'columns' => [
                [
                    'name' => 'id',
                    'definition' => 'SERIAL'
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
                    'definition' => 'TIMESTAMP NOT NULL'
                ],
                [
                    'name' => 'updated_time',
                    'definition' => 'TIMESTAMP NOT NULL'
                ]
            ],
            'additional' => 'PRIMARY KEY (id), UNIQUE (fqdn)',
            'table_options' => ''
        ],
        'users' => [
            'columns' => [
                [
                    'name' => 'id',
                    'definition' => 'SERIAL'
                ],
                [
                    'name' => 'name',
                    'definition' => 'varchar(32) NOT NULL'
                ],
                [
                    'name' => 'level',
                    'definition' => 'smallint NOT NULL'
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
                    'definition' => 'integer NOT NULL'
                ],
                [
                    'name' => 'created_time',
                    'definition' => 'TIMESTAMP NOT NULL'
                ],
                [
                    'name' => 'updated_time',
                    'definition' => 'TIMESTAMP NOT NULL'
                ]
            ],
            'additional' => 'PRIMARY KEY (id), UNIQUE (name)',
            'table_options' => ''
        ],
        'userdomains' => [
            'columns' => [
                [
                    'name' => 'domain_id',
                    'definition' => 'integer NOT NULL'
                ],
                [
                    'name' => 'user_id',
                    'definition' => 'integer NOT NULL'
                ]
            ],
            'additional' => 'PRIMARY KEY (domain_id, user_id)',
            'table_options' => ''
        ],
        'reports' => [
            'columns' => [
                [
                    'name' => 'id',
                    'definition' => 'SERIAL'
                ],
                [
                    'name' => 'domain_id',
                    'definition' => 'integer NOT NULL'
                ],
                [
                    'name' => 'begin_time',
                    'definition' => 'TIMESTAMP NOT NULL'
                ],
                [
                    'name' => 'end_time',
                    'definition' => 'TIMESTAMP NOT NULL'
                ],
                [
                    'name' => 'loaded_time',
                    'definition' => 'TIMESTAMP NOT NULL'
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
            'additional' => 'PRIMARY KEY (id)',
            'table_options' => '',
            'indexes' => [
                [
                    'columns' => 'domain_id, begin_time, org, external_id',
                    'unique' => true
                ],
                [
                    'columns' => 'begin_time',
                    'unique' => false
                ],
                [
                    'columns' => 'end_time',
                    'unique' => false
                ],
                [
                    'columns' => 'org, begin_time',
                    'unique' => false
                ]
            ]
        ],
        'rptrecords' => [
            'columns' => [
                [
                    'name' => 'id',
                    'definition' => 'SERIAL'
                ],
                [
                    'name' => 'report_id',
                    'definition' => 'integer NOT NULL'
                ],
                [
                    'name' => 'ip',
                    'definition' => 'inet NOT NULL'
                ],
                [
                    'name' => 'rcount',
                    'definition' => 'integer NOT NULL'
                ],
                [
                    'name' => 'disposition',
                    'definition' => 'smallint NOT NULL'
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
                    'definition' => 'smallint NOT NULL'
                ],
                [
                    'name' => 'spf_align',
                    'definition' => 'smallint NOT NULL'
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
            'additional' => 'PRIMARY KEY (id)',
            'table_options' => '',
            'indexes' => [
                [
                    'columns' => 'report_id'
                ],
                [
                    'columns' => 'ip'
                ]
            ]
        ],
        'reportlog' => [
            'columns' => [
                [
                    'name' => 'id',
                    'definition' => 'SERIAL'
                ],
                [
                    'name' => 'user_id',
                    'definition' => 'integer NOT NULL DEFAULT 0'
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
                    'definition' => 'TIMESTAMP NOT NULL'
                ],
                [
                    'name' => 'filename',
                    'definition' => 'varchar(255) NULL'
                ],
                [
                    'name' => 'source',
                    'definition' => 'smallint NOT NULL'
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
            'additional' => 'PRIMARY KEY (id)',
            'table_options' => '',
            'indexes' => [
                [
                    'columns' => 'event_time'
                ],
                [
                    'columns' => 'user_id, event_time'
                ]
            ]
        ]
    ];
}
