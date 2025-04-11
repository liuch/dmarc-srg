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
 * This file contains the abstract DatabaseConnector class
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Database;

use Liuch\DmarcSrg\Exception\LogicException;

abstract class DatabaseConnector
{
    protected static $names = [
        'domain'     => 'DomainMapper',
        'host'       => 'HostMapper',
        'report'     => 'ReportMapper',
        'report-log' => 'ReportLogMapper',
        'setting'    => 'SettingMapper',
        'statistics' => 'StatisticsMapper',
        'upgrader'   => 'UpgraderMapper',
        'user'       => 'UserMapper'
    ];

    protected $host     = null;
    protected $name     = null;
    protected $user     = null;
    protected $password = null;
    protected $prefix   = '';
    protected $mappers  = [];

    /**
     * The constructor
     *
     * @param array $conf Configuration data from the conf.php file
     */
    public function __construct(array $conf)
    {
        $this->host     = $conf['host'] ?? '';
        $this->name     = $conf['name'] ?? '';
        $this->user     = $conf['user'] ?? '';
        $this->password = $conf['password'] ?? '';
        $this->prefix   = $conf['table_prefix'] ?? '';
    }

    /**
     * Returns an instance of PDO class
     *
     * @return \PDO
     */
    abstract public function dbh(): object;

    /**
     * Returns the database state as an array
     *
     * @return array
     */
    abstract public function state(): array;

    /**
     * Returns a data mapper by its name.
     *
     * @param string $name Mapper name
     *
     * @return object
     */
    public function getMapper(string $name): object
    {
        if (isset($this->mappers[$name])) {
            return $this->mappers[$name];
        }

        if (!isset(self::$names[$name])) {
            throw new LogicException('Unknown mapper name: ' . $name);
        }

        $reflection = new \ReflectionClass($this);
        $mapper_name = $reflection->getNamespaceName() . '\\' . self::$names[$name];
        if (!class_exists($mapper_name)) {
            $reflection = $reflection->getParentClass();
            if ($reflection) {
                $mapper_name = $reflection->getNamespaceName() . '\\Common\\' . self::$names[$name];
            }
        }
        $mapper = new $mapper_name($this);
        $this->mappers[$name] = $mapper;
        return $mapper;
    }

    /**
     * Initiates the database.
     *
     * @return void
     */
    abstract public function initDb(string $version): void;

    /**
     * Cleans up the database
     *
     * @return void
     */
    abstract public function cleanDb(): void;

    /**
     * Returns the prefix for tables of the database
     *
     * @param string $postfix String to be concatenated with the prefix.
     *                        Usually, this is a table name.
     *
     * @return string
     */
    public function tablePrefix(string $postfix = ''): string
    {
        return $this->prefix . $postfix;
    }
}
