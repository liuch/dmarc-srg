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

namespace Liuch\DmarcSrg\Database\Sqlite;

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
     * Returns the name of the database
     *
     * @return string
     */
    public function dbName(): string
    {
        return $this->name;
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

        try {
            $tables = [];
            $st = $this->dbh->query(
                'SELECT name FROM sqlite_schema WHERE type=\'table\''
            );
            while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
                $tname = $row['name'];
                $rcnt  = $this->dbh->query('SELECT COUNT(*) FROM ' . $tname . '')->fetch(\PDO::FETCH_NUM)[0];
                $tables[$tname] = [
                    'engine'       => 'sqlite',
                    'rows'         => intval($rcnt),
                    'data_length'  => null,
                    'index_length' => null,
                    'create_time'  => null,
                    'update_time'  => null
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
                try {
                    $res['version'] = $this->getMapper('setting')->value('version');
                } catch (DatabaseNotFoundException $e) {
                }
            } else {
                $res['error_code'] = -1;
                if ($exist_cnt == 0) {
                    $res['message'] = 'The database schema is not initiated';
                } else {
                    $res['message'] = 'Incomplete set of the tables';
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
     * Inites the database.
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
                }
                unset($t_schema);
            } finally {
                $st->closeCursor();
            }
            $st = $this->dbh->prepare(
                'INSERT INTO ' . $this->tablePrefix('system') . ' (key, value) VALUES ("version", ?)'
            );
            $st->bindValue(1, $version, \PDO::PARAM_STR);
            $st->execute();
            $st->closeCursor();
        } catch (\PDOException $e) {
		die(Throw $e);
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
            $db->query('PRAGMA FOREIGN_KEYS = OFF');
            $st = $db->query($this->sqlShowTablesQuery());
            while ($table = $st->fetchColumn(0)) {
                $db->query('DROP TABLE ' . $table . '');
            }
            $st->closeCursor();
            $db->query('PRAGMA FOREIGN_KEYS = ON');
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
                    "sqlite:$this->name",
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
        $res = 'SELECT name FROM sqlite_schema WHERE type=\'table\' ORDER BY name;';
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
            $query .= '' . $column['name'] . ' ' . $column['definition'];
            $col_num += 1;
        }
        $query .= '); ' . $definitions['table_options'];
        $this->dbh->query($query);
		foreach($definitions['sub_queries'] as $sub_query) {
			$this->dbh->query($sub_query);
		}
    }

    private static $schema = [
        'system' => [
            'columns' => [
                [
                    'name' => 'key',
                    'definition' => 'TEXT NOT NULL PRIMARY KEY'
                ],
                [
                    'name' => 'value',
                    'definition' => 'TEXT DEFAULT NULL'
                ]
            ],
            'additional' => '',
            'table_options' => '',
			'sub_queries' => []
        ],
        'domains' => [
            'columns' => [
                [
                    'name' => 'id',
                    'definition' => 'INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT'
                ],
                [
                    'name' => 'fqdn',
                    'definition' => 'TEXT NOT NULL UNIQUE'
                ],
                [
                    'name' => 'active',
                    'definition' => 'INTEGER NOT NULL'
                ],
                [
                    'name' => 'description',
                    'definition' => 'TEXT NULL'
                ],
                [
                    'name' => 'created_time',
                    'definition' => 'TEXT NOT NULL'
                ],
                [
                    'name' => 'updated_time',
                    'definition' => 'TEXT NOT NULL'
                ]
            ],
            'additional' => '',
            'table_options' => '',
			'sub_queries' => []
        ],
        'reports' => [
            'columns' => [
                [
                    'name' => 'id',
                    'definition' => 'INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT'
                ],
                [
                    'name' => 'domain_id',
                    'definition' => 'INTEGER NOT NULL'
                ],
                [
                    'name' => 'begin_time',
                    'definition' => 'TEXT NOT NULL'
                ],
                [
                    'name' => 'end_time',
                    'definition' => 'TEXT NOT NULL'
                ],
                [
                    'name' => 'loaded_time',
                    'definition' => 'TEXT NOT NULL'
                ],
                [
                    'name' => 'org',
                    'definition' => 'TEXT NOT NULL'
                ],
                [
                    'name' => 'external_id',
                    'definition' => 'TEXT NOT NULL'
                ],
                [
                    'name' => 'email',
                    'definition' => 'TEXT NOT NULL'
                ],
                [
                    'name' => 'extra_contact_info',
                    'definition' => 'TEXT NULL'
                ],
                [
                    'name' => 'error_string',
                    'definition' => 'text NULL'
                ],
                [
                    'name' => 'policy_adkim',
                    'definition' => 'TEXT NULL'
                ],
                [
                    'name' => 'policy_aspf',
                    'definition' => 'TEXT NULL'
                ],
                [
                    'name' => 'policy_p',
                    'definition' => 'TEXT NULL'
                ],
                [
                    'name' => 'policy_sp',
                    'definition' => 'TEXT NULL'
                ],
                [
                    'name' => 'policy_pct',
                    'definition' => 'TEXT NULL'
                ],
                [
                    'name' => 'policy_fo',
                    'definition' => 'TEXT NULL'
                ],
                [
                    'name' => 'seen',
                    'definition' => 'INTEGER NOT NULL'
                ]
            ],
            'additional' => '',
            'table_options' => '',
			'sub_queries' => [
				'CREATE UNIQUE INDEX reports_domain_id_external_id ON reports(domain_id, external_id)',
				'CREATE INDEX reports_begin_time ON reports(begin_time)',
				'CREATE INDEX reports_end_time ON reports(end_time)'
			]
        ],
        'rptrecords' => [
            'columns' => [
                [
                    'name' => 'id',
                    'definition' => 'INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT'
                ],
                [
                    'name' => 'report_id',
                    'definition' => 'INTEGER NOT NULL'
                ],
                [
                    'name' => 'ip',
                    'definition' => 'TEXT NOT NULL'
                ],
                [
                    'name' => 'rcount',
                    'definition' => 'INTEGER NOT NULL'
                ],
                [
                    'name' => 'disposition',
                    'definition' => 'INTEGER NOT NULL'
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
                    'definition' => 'INTEGER NOT NULL'
                ],
                [
                    'name' => 'spf_align',
                    'definition' => 'INTEGER NOT NULL'
                ],
                [
                    'name' => 'envelope_to',
                    'definition' => 'TEXT NULL'
                ],
                [
                    'name' => 'envelope_from',
                    'definition' => 'TEXT NULL'
                ],
                [
                    'name' => 'header_from',
                    'definition' => 'TEXT NULL'
                ]
            ],
            'additional' => '',
            'table_options' => '',
			'sub_queries' => [
				'CREATE INDEX rptrecords_report_id ON rptrecords(report_id)',
				'CREATE INDEX rptrecords_ip ON rptrecords(ip)'
			]
        ],
        'reportlog' => [
            'columns' => [
                [
                    'name' => 'id',
                    'definition' => 'INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT'
                ],
                [
                    'name' => 'domain',
                    'definition' => 'TEXT NULL'
                ],
                [
                    'name' => 'external_id',
                    'definition' => 'TEXT NULL'
                ],
                [
                    'name' => 'event_time',
                    'definition' => 'TEXT NOT NULL'
                ],
                [
                    'name' => 'filename',
                    'definition' => 'TEXT NULL'
                ],
                [
                    'name' => 'source',
                    'definition' => 'INTEGER NOT NULL'
                ],
                [
                    'name' => 'success',
                    'definition' => 'INTEGER NOT NULL'
                ],
                [
                    'name' => 'message',
                    'definition' => 'text NULL'
                ]
            ],
            'additional' => '',
            'table_options' => '',
			'sub_queries' => [
				'CREATE INDEX reportlog_event_time ON reportlog(event_time)'
			]
        ]
    ];
}
