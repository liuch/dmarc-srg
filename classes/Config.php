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
 * This file contains the class Config
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Exception\LogicException;

/**
 * This class is for storing configuration data to avoid using globas variables
 */
class Config
{
    private $data = [];

    /**
     * The constructor
     *
     * @param string $config_file A php config file to load.
     */
    public function __construct(string $config_file)
    {
        require($config_file);
        foreach ([
                'debug', 'database', 'mailboxes', 'directories',
                'admin', 'mailer', 'fetcher', 'cleaner'
            ] as $key
        ) {
            $this->data[$key] = $$key ?? null;
        }
    }

    /**
     * Returns config value by its name
     *
     * @param string $name    Setting name. Hierarchy supported via '/'
     * @param mixed  $default Value to be returned if the required config item is missing or null
     *
     * @return mixed
     */
    public function get(string $name, $default = null)
    {
        $nm_i = 0;
        $path = explode('/', $name);
        $data = $this->data;
        do {
            $key = $path[$nm_i++];
            if (empty($key)) {
                throw new LogicException('Incorrect setting name: ' .$name);
            }
            if (!isset($data[$key])) {
                return $default;
            }
            $data = $data[$key];
            if (!isset($path[$nm_i])) {
                return $data;
            }
        } while (gettype($data) === 'array');

        return $default;
    }
}
