<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2022-2024 Aleksey Andreev (liuch)
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
 * This file contains the DomainMapperInterface
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Database;

interface DomainMapperInterface
{
    /**
     * Return true if the domain exists or false otherwise.
     *
     * @param array $data Array with domain data to search
     *
     * @return bool
     */
    public function exists(array &$data): bool;

    /**
     * Returns true if the domain exists and is assigned to the user
     *
     * @param array $data    Array with domain data to check
     * @param int   $user_id User ID to check
     *
     * @return bool
     */
    public function isAssigned(array &$data, int $user_id): bool;

    /**
     * Fetch the domain data from the database by its id or name
     *
     * @param array $data Domain data to update
     *
     * @return void
     */
    public function fetch(array &$data): void;

    /**
     * Saves domain data to the database (updates or inserts an record)
     *
     * @param array $data Domain data
     *
     * @return void
     */
    public function save(array &$data): void;

    /**
     * Deletes the domain from the database
     *
     * Deletes the domain if there are no reports for this domain in the database.
     *
     * @param int $id Domain ID
     *
     * @return void
     */
    public function delete(int $id): void;

    /**
     * Returns a list of domains data from the database
     *
     * @param int $user_id User ID to retrieve the list for
     *
     * @return array
     */
    public function list(int $user_id): array;

    /**
     * Returns an ordered array with domain names from the database
     *
     * @param int $user_id User ID to retrieve the list for
     *
     * @return array
     */
    public function names(int $user_id): array;

    /**
     * Returns the total number of domains in the database
     *
     * @param int $user_id User ID
     * @param int $max     The maximum number of records to count. 0 means no limitation.
     *
     * @return int The total number of domains
     */
    public function count(int $user_id, int $max = 0): int;

    /**
     * Assigns the domain to a user
     *
     * @param array $data    Domain data
     * @param int   $user_id User ID
     *
     * @return void
     */
    public function assignUser(array &$data, int $user_id): void;

    /**
     * Unassign the domain from a user
     *
     * @param array $data    Domain data
     * @param int   $user_id User ID
     *
     * @return void
     */
    public function unassignUser(array &$data, int $user_id): void;

    /**
     * Updates the list of domains assigned to a user
     *
     * @param array $domains List of domains
     * @param int   $user_id User ID
     *
     * @return void
     */
    public function updateUserDomains(array &$domains, int $user_id): void;
}
