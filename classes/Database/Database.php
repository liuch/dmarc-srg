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

class Database
{
    public const REQUIRED_VERSION = '1.0';

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

    public static function parameter($key, $value = null)
    {
        $db = self::connection();
        $st = null;
        try {
            if ($value === null) {
                try {
                    $st = $db->prepare('SELECT `value` FROM `system` WHERE `key` = ?');
                    $st->bindValue(1, strval($key), PDO::PARAM_STR);
                    $st->execute();
                    $res = $st->fetch(PDO::FETCH_NUM);
                    return $res ? $res[0] : null;
                } catch (Exception $e) {
                    throw new Exception('Failed to get a system parameter', -1);
                }
            } else {
                $db->beginTransaction();
                try {
                    $st = $db->prepare('SELECT COUNT(*) FROM `system` WHERE `key` = ?');
                    $st->bindValue(1, strval($value), PDO::PARAM_STR);
                    $st->execute();
                    $res = $st->fetch(PDO::FETCH_NUM);
                    $st->closeCursor();
                    if (intval($res[0]) == 0) {
                        $st = $db->prepare('INSERT INTO `system` (`key`, `value`) VALUES (?, ?)');
                        $st->bindValue(1, strval($key), PDO::PARAM_STR);
                        $st->bindValue(2, strval($value), PDO::PARAM_STR);
                    } else {
                        $st = $db->prepare('UPDATE `system` SET `value` = ? WHERE `key` = ?');
                        $st->bindValue(1, strval($value), PDO::PARAM_STR);
                        $st->bindValue(2, strval($key), PDO::PARAM_STR);
                    }
                    $st->execute();
                    $db->commit();
                } catch (Exception $e) {
                    $db->rollBack();
                    throw new Exception('Failed to set a system parameter', -1);
                }
            }
        } finally {
            if ($st) {
                $st->closeCursor();
            }
        }
    }

    private function establishConnection()
    {
        global $database;
        try {
            $dsn = "{$database['type']}:host={$database['host']};dbname={$database['name']};charset=utf8";
            $this->conn = new PDO(
                $dsn,
                $database['user'],
                $database['password'],
                [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ]
            );
            $this->conn->query('SET time_zone = "+00:00"');
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), -1);
        }
    }

    public static $schema = [
        'system' => [
            'columns' => [
                [
                    'name' => 'key',
                    'definition' => 'varchar(25) NOT NULL'
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

