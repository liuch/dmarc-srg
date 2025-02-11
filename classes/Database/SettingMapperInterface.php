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
 * This file contains the SettingMapperInterface
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Database;

interface SettingMapperInterface
{
    /**
     * Returns setting value as a string by key
     *
     * @param string $key
     * @param int    $user_id
     *
     * @return string
     */
    public function value(string $key, int $user_id): string;

    /**
     * Returns a key-value array of the setting list like this:
     * [ 'name1' => 'value1', 'name2' => 'value2' ]
     *
     * @param int $user_id User Id to get settings for
     *
     * @return array
     */
    public function list(int $user_id): array;

    /**
     * Saves the setting to the database
     *
     * Updates the value of the setting in the database if the setting exists there or insert a new record otherwise.
     *
     * @param string $name    Setting name
     * @param string $value   Setting value
     * @param int    $user_id User Id to save the setting for
     *
     * @return void
     */
    public function save(string $name, string $value, int $user_id): void;
}
