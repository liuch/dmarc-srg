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
 * This file contains the DatabaseController class
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Database;

use Liuch\DmarcSrg\Exception\RuntimeException;
use Liuch\DmarcSrg\Exception\DatabaseFatalException;

/**
 * Proxy class for accessing a database of the selected type
 */
class DatabaseController
{
    public const REQUIRED_VERSION = '4.0';

    private $conf_data = null;
    /** @var DatabaseConnector|null */
    private $connector = null;

    /**
     * The constructor
     *
     * @param \Liuch\DmarcSrg\Core $core      Instance of the Core class
     * @param DatabaseConnector    $connector The connector class of the current database
     */
    public function __construct($core, $connector = null)
    {
        $this->conf_data = $core->config('database');
        $this->connector = $connector;
    }

    /**
     * Returns the database type
     *
     * @return string
     */
    public function type(): string
    {
        return $this->conf_data['type'] ?? '';
    }

    /**
     * Returns the database name
     *
     * @return string
     */
    public function name(): string
    {
        return $this->conf_data['name'] ?? '';
    }

    /**
     * Returns the database host
     *
     * @return string
     */
    public function location(): string
    {
        return $this->conf_data['host'] ?? '';
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
    public function state(): array
    {
        $this->ensureConnector();

        $res = $this->connector->state();
        $res['type']     = $this->type();
        $res['name']     = $this->name();
        $res['location'] = $this->location();
        if ((($res['correct'] ?? false) || ($res['error_code'] ?? 0) === -4)
            && ($res['version'] ?? 'null') !== self::REQUIRED_VERSION
        ) {
            $res['correct'] = false;
            $res['message'] = 'The database structure needs upgrading';
            $res['needs_upgrade'] = true;
            unset($res['error_code']);
        }
        return $res;
    }

    /**
     * Initiates the database.
     *
     * This method creates needed tables and indexes in the database.
     * The method will fail if the database already have tables with the table prefix.
     *
     * @return array Result array with `error_code` and `message` fields.
     */
    public function initDb(): array
    {
        $this->ensureConnector();
        $this->connector->initDb(self::REQUIRED_VERSION);
        return [ 'message' => 'The database has been initiated' ];
    }

    /**
     * Cleans up the database.
     *
     * Drops tables with the table prefix in the database or all tables in the database if no table prefix is set.
     *
     * @return array Result array with `error_code` and `message` fields.
     */
    public function cleanDb(): array
    {
        $this->ensureConnector();
        $this->connector->cleanDb();
        return [ 'message' => 'The database tables have been dropped' ];
    }

    /**
     * Returns a data mapper by its name from the current database connector
     *
     * @param string $name Mapper name
     *
     * @return object
     */
    public function getMapper(string $name): object
    {
        $this->ensureConnector();
        return $this->connector->getMapper($name);
    }

    /**
     * Finds the connector of the specified database type and initializes it
     * if it hasn't already been initialized
     *
     * @return void
     */
    private function ensureConnector(): void
    {
        if (!$this->connector) {
            switch ($this->conf_data['type']) {
                case 'mysql':
                case 'mariadb':
                    $type = 'mariadb';
                    break;
                default:
                    throw new RuntimeException('Unknown database type: ' . $this->conf_data['type']);
            }
            $c_name = __NAMESPACE__ . '\\' . \ucfirst($type) . '\\Connector';
            $this->connector = new $c_name($this->conf_data);
        }
    }
}
