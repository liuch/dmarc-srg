<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2023 Aleksey Andreev (liuch)
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
 * This file contains the UserMapperInterface
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Database;

interface UserMapperInterface
{
    /**
     * Return true if the user exists or false otherwise.
     *
     * @param array $data Array with user data to search
     *
     * @return bool
     */
    public function exists(array &$data): bool;

    /**
     * Fetch the user data from the database by its id or name
     *
     * @param array $data User data to update
     *
     * @return void
     */
    public function fetch(array &$data): void;

    /**
     * Saves user data to the database (updates or inserts an record)
     *
     * @param array $data User data
     *
     * @return void
     */
    public function save(array &$data): void;

    /**
     * Deletes the user from the database
     *
     * Deletes the user if there are no reports for this user in the database.
     *
     * @param array $data User data
     *
     * @return void
     */
    public function delete(array &$data): void;

    /**
     * Returns a list of users data from the database
     *
     * @return array
     */
    public function list(): array;

    /**
     * Returns the user's password hash
     *
     * @param array $data User data
     *
     * @return string
     */
    public function getPasswordHash(array &$data): string;

    /**
     * Replaces the user's password hash with the passed one
     *
     * @param array  $data User data
     * @param string $hash Password hash to save
     */
    public function savePasswordHash(array &$data, string $hash): void;
}
