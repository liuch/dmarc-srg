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
 * This file contains the ReportLogMapperInterface
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Database;

interface ReportLogMapperInterface
{
    /**
     * Fetches data of report log item from the database by id
     *
     * @param Report log data
     *
     * @return void
     */
    public function fetch(array &$data): void;

    /**
     * Saves data of report log item to the database
     *
     * @return void
     */
    public function save(array &$data): void;

    /**
     * Returns a list of report log items with given criteria
     *
     * @param array $filter Key-value array:
     *                      'from_time' => DateTime
     *                      'till_time' => DateTime
     * @param array $order  Key-value array with order options:
     *                      'direction' => string, 'ascent' or 'descent'
     * @param array $limit  Key-value array:
     *                      'offset' => int
     *                      'count'  => int
     *
     * @return array
     */
    public function list(array &$filter, array &$order, array &$limit): array;

    /**
     * Returns the number of report log items matching the specified filter and limits
     *
     * @param array $filter Key-value array with filtering parameters
     * @param array $limit  Key-value array with limits
     *
     * @return int
     */
    public function count(array &$filter, array &$limit): int;

    /**
     * Deletes report log items from the database
     *
     * @param array $filter Key-value array with filtering parameters
     * @param array $order  Key-value array with order options:
     *                      'direction' => string, 'ascent' or 'descent'
     * @param array $limit  Key-value array with limits
     *
     * @return void
     */
    public function delete(array &$filter, array &$order, array &$limit): void;
}
