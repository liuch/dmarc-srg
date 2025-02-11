<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2020-2024 Aleksey Andreev (liuch)
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
 * This file contains ReportList class
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Report;

use Liuch\DmarcSrg\Core;

/**
 * It's the main class for working with the incoming reports, such as:
 *  - getting a list of reports
 *  - deleting several reports at once
 *  - getting the number of reports stored in the database
 */
class ReportList
{
    public const ORDER_NONE       = 0;
    public const ORDER_BEGIN_TIME = 1;
    public const ORDER_ASCENT     = 2;
    public const ORDER_DESCENT    = 3;

    private $db     = null;
    private $user   = null;
    private $limit  = 0;
    private $filter = [];
    private $order  = null;

    /**
     * The constructor
     *
     * @param \Liuch\DmarcSrg\Users\User                  $user User to get list
     * @param \Liuch\DmarcSrg\Database\DatabaseController $db   The database controller
     */
    public function __construct($user, $db = null)
    {
        $this->user = $user;
        $this->db   = $db ?? Core::instance()->database();
        $this->resetOrderParams();
    }

    /**
     * Returns a list of reports with specified parameters from position $pos
     *
     * This method returns a list of reports that depends on the filter and order.
     * The filter, order, and limit for the list can be set using the setFilter, setOrder and setMaxCount methods.
     *
     * @param int $pos The starting position from which the list will be returned
     *
     * @return array An array with keys `reports` and `more`.
     *               `reports` is an array of incoming reports which contains maximum 25 records by default.
     *                         Another value of the number of records can be specified by calling
     *                         the method setMaxCount.
     *               `more`    is true if there are more records in the database, false otherwise.
     */
    public function getList(int $pos): array
    {
        $max_rec = $this->limit > 0 ? $this->limit : 25;
        $limit = [
            'offset' => $pos,
            'count'  => $max_rec + 1
        ];

        $list = $this->db->getMapper('report')->list($this->filter, $this->order, $limit, $this->user->id());
        if (count($list) > $max_rec) {
            $more = true;
            unset($list[$max_rec]);
        } else {
            $more = false;
        }
        return [
            'reports' => $list,
            'more'    => $more
        ];
    }

    /**
     * Sets the sort order for the list and for deleting several reports at once
     *
     * @param int $field     The field to sort by. Currently only ORDER_BEGIN_TIME is available.
     * @param int $direction The sorting direction. ORDER_ASCENT or ORDER_DESCENT must be used here.
     *
     * @return self
     */
    public function setOrder(int $field, int $direction)
    {
        $this->order = null;
        if ($field > self::ORDER_NONE && $field < self::ORDER_ASCENT) {
            if ($direction !== self::ORDER_ASCENT) {
                $direction = self::ORDER_DESCENT;
            }
            $this->order = [
                'field'     => 'begin_time',
                'direction' => $direction === self::ORDER_ASCENT ? 'ascent' : 'descent'
            ];
        } else {
            $this->resetOrderParams();
        }
        return $this;
    }

    /**
     * Sets maximum numbers of records in the list and for deleting reports
     *
     * @param int $num Maximum number of records in the list
     *
     * @return self
     */
    public function setMaxCount(int $num)
    {
        if ($num > 0) {
            $this->limit = $num;
        } else {
            $this->limit = 0;
        }
        return $this;
    }

    /**
     * Sets filter values for the list and for deleting reports
     *
     * @param array $filter Key-value array:
     *                      'before_time'  => DateTime, timestamp
     *                      'dkim'         => string, 'fail' or 'pass'
     *                      'domain'       => string or instance of Domain class
     *                      'month'        => string, yyyy-mm format
     *                      'organization' => string
     *                      'spf'          => string, 'fail' or 'pass'
     *                      'status'       => string, 'read' or 'unread'
     *                      Note! 'dkim', 'spf' and 'disposition' do not affect the delete method
     *
     * @return self
     */
    public function setFilter(array $filter)
    {
        $this->filter = $filter;
        return $this;
    }

    /**
     * Returns the number of reports in the database
     *
     * It returns the number of reports in the database.
     * The limit and some filter items `dkim`, `spf` and `disposition` do not affect this.
     *
     * @return int The number of reports in the database
     */
    public function count(): int
    {
        $limit = [ 'offset' => 0, 'count' => $this->limit ];
        return $this->db->getMapper('report')->count($this->filter, $limit, $this->user->id());
    }

    /**
     * Deletes reports from the database
     *
     * It deletes repors form the database. The filter items `dkim`, `spf` and `disposition` do not affect this.
     *
     * @return void
     */
    public function delete(): void
    {
        $limit = [ 'offset' => 0, 'count' => $this->limit ];
        $this->db->getMapper('report')->delete($this->filter, $this->order, $limit);
    }

    /**
     * Returns a list of values for each filter item except for `before_time`
     *
     * @return array An key-value array, where the key is the filter item name
     *               and the value is an array of possible values for the item
     */
    public function getFilterList(): array
    {
        $domainMapper = $this->db->getMapper('domain');
        $reportMapper = $this->db->getMapper('report');
        $user_id      = $this->user->id();
        return [
            'domain'       => $domainMapper->names($user_id),
            'month'        => $reportMapper->months($user_id),
            'organization' => $reportMapper->organizations($user_id),
            'dkim'         => [ 'pass', 'fail' ],
            'spf'          => [ 'pass', 'fail' ],
            'disposition'  => [ 'none', 'reject', 'quarantine' ],
            'status'       => [ 'read', 'unread' ]
        ];
    }

    /**
     * Resets the sort params
     *
     * @return void
     */
    private function resetOrderParams(): void
    {
        $this->order = [
            'field'     => 'begin_time',
            'direction' => 'descent'
        ];
    }
}
