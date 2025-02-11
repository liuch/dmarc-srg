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
 * This file contains the ReportMapperInterface
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Database;

use Liuch\DmarcSrg\Report\ReportData;

interface ReportMapperInterface
{
    /**
     * Fetches report data from the database and stores it in the passed array
     *
     * @param ReportData $data Report data class.
     *                         To identify the report it must contain at least these fields:
     *                         `report_id`   - External report id from the xml file
     *                         `domain`      - Fully Qualified Domain Name without a trailing dot
     *                         `org_name`    - Organization name
     *                         `date=>begin` - Begin timestamp of the report time range
     *
     * @return void
     */
    public function fetch($data): void;

    /**
     * Inserts report data into the database.
     *
     * @param ReportData $data Report data
     *
     * @return void
     */
    public function save($data): void;

    /**
     * Sets report record property in database.
     *
     * It has nothing to do with the fields of the report itself.
     *
     * @param ReportData $data  Report data.
     * @param string     $name  Name of property to change. Currently only `seen` is supported.
     * @param mixed      $value New property value
     *
     * @return void
     */
    public function setProperty($data, string $name, $value): void;

    /**
     * Returns a list of reports with specified parameters
     *
     * This method returns a list of reports that depends on the $filter, $order and $limit.
     *
     * @param array $filter  Key-value array with filtering parameters
     * @param array $order   Key-value array:
     *                       'field'     => string, 'begin_time'
     *                       'direction' => string, 'ascent' or 'descent'
     * @param array $limit   Key-value array with two keys: `offset` and `count`
     * @param int   $user_id User ID to retrieve the list for
     *
     * @return array
     */
    public function list(array &$filter, array &$order, array &$limit, int $user_id): array;

    /**
     * Returns the number of reports matching the specified filter and limits
     *
     * @param array $filter  Key-value array with filtering parameters
     * @param array $limit   Key-value array with two keys: `offset` and `count`
     * @param int   $user_id User ID to count reports for
     *
     * @return int
     */
    public function count(array &$filter, array &$limit, int $user_id): int;

    /**
     * Deletes reports from the database
     *
     * It deletes repors form the database. The filter options `dkim` and `spf` do not affect this.
     *
     * @param array $filter Key-value array with filtering parameters
     * @param array $order  Key-value array:
     *                      'field'     => string, 'begin_time'
     *                      'direction' => string, 'ascent' or 'descent'
     * @param array $limit  Key-value array with two keys: `offset` and `count`
     *
     * @return void
     */
    public function delete(array &$filter, array &$order, array &$limit): void;

    /**
     * Returns a list of months with years of the form: 'yyyy-mm' for which there is at least one report
     *
     * @param int $user_id User ID to retrieve the list for
     *
     * @return array
     */
    public function months(int $user_id): array;

    /**
     * Returns a list of reporting organizations from which there is at least one report
     *
     * @param int $user_id User ID to retrieve the list for
     *
     * @return array
     */
    public function organizations(int $user_id): array;
}
