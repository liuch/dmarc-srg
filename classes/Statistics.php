<?php

/**
 * dmarc-srg - a php parser, viewer and summary report generator for incoming dmarc reports.
 * copyright (c) 2020 aleksey andreev (liuch)
 *
 * available at:
 * https://github.com/liuch/dmarc-srg
 *
 * this program is free software: you can redistribute it and/or modify it
 * under the terms of the gnu general public license as published by the free
 * software foundation, either version 3 of the license.
 *
 * this program is distributed in the hope that it will be useful, but without
 * any warranty; without even the implied warranty of  merchantability or
 * fitness for a particular purpose. see the gnu general public license for
 * more details.
 *
 * you should have received a copy of the gnu general public license along with
 * this program.  if not, see <http://www.gnu.org/licenses/>.
 *
 * =========================
 *
 * This file contains the class Statistics
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\DateTime;

/**
 * This class is designed to get statistics on DMARC reports of a specified period
 */
class Statistics
{
    /** @var Database\DatabaseController*/
    private $db     = null;
    /** @var Domains\Domain|null */
    private $domain = null;
    /** @var array<string,DateTime|null> */
    private $range  = [
            'date1' => null,
            'date2' => null
    ];
    private $filter = [];

    /**
     * The constructor of the class, it only uses in static methods of this class
     *
     * @param Domains\Domain|null              $domain The domain for which you need to get statistics,
     *                                                 null for all the domains.
     * @param Database\DatabaseController|null $db     The database controller
     *
     * @return void
     */
    private function __construct($domain, $db)
    {
        $this->domain = $domain;
        $this->db     = $db ?? Core::instance()->database();
    }

    /**
     * Returns an instance of the class for the period from $date1 to $date2
     *
     * @param Domains\Domain|null         $domain See the constructor for the details
     * @param DateTime                    $date1  The date you need statistics from
     * @param DateTime                    $date2  The date you need statistics to (not included)
     * @param Database\DatabaseController $db     The database controller
     *
     * @return Statistics Instance of the class
     */
    public static function fromTo($domain, $date1, $date2, $db = null)
    {
        $r = new Statistics($domain, $db);
        $r->range['date1'] = $date1;
        $r->range['date2'] = $date2;
        return $r;
    }

    /**
     * Returns an instance of the class for the last week
     *
     * @param Domains\Domain|null         $domain See the constructor for the details
     * @param int                         $offset Offset to the past (weeks)
     * @param Database\DatabaseController $db     The database controller
     *
     * @return Statistics Instance of the class
     */
    public static function lastWeek($domain, int $offset = 0, $db = null)
    {
        $r = new Statistics($domain, $db);
        $r->range['date1'] = new DateTime('monday last week');
        if ($offset > 0) {
            $r->range['date1']->sub(new \DateInterval("P{$offset}W"));
        }
        $r->range['date2'] = (clone $r->range['date1'])->add(new \DateInterval('P1W'));
        return $r;
    }

    /**
     * Returns an instance of the class for the last month
     *
     * @param Domains\Domain|null         $domain See the construct for the details
     * @param int                         $offset Offset to the past (months)
     * @param Database\DatabaseController $db     The database controller
     *
     * @return Statistics Instance of the class
     */
    public static function lastMonth($domain, int $offset = 0, $db = null)
    {
        $r = new Statistics($domain, $db);
        $r->range['date1'] = new DateTime('midnight first day of last month');
        if ($offset > 0) {
            $r->range['date1']->sub(new \DateInterval("P{$offset}M"));
        }
        $r->range['date2'] = (clone $r->range['date1'])->add(new \DateInterval('P1M'));
        return $r;
    }

    /**
     * Returns an instance of the class for the last N days
     *
     * @param Domains\Domain|null         $domain See the construct for the details
     * @param int                         $ndays  Number of days
     * @param int                         $offset Offset to the past (days)
     * @param Database\DatabaseController $db     The database controller
     *
     * @return Statistics Instance of the class
     */
    public static function lastNDays($domain, int $ndays, int $offset = 0, $db = null)
    {
        $r = new Statistics($domain, $db);
        $r->range['date2'] = new DateTime('midnight');
        if ($offset > 0) {
            $r->range['date2']->sub(new \DateInterval("P{$offset}D"));
        }
        $r->range['date1'] = (clone $r->range['date2'])->sub(new \DateInterval("P{$ndays}D"));
        return $r;
    }

    /**
     * Returns the date from and the date to in an array
     *
     * @return DateTime[] - The range of the statistics
     */
    public function range(): array
    {
        return [ (clone $this->range['date1']), (clone $this->range['date2'])->sub(new \DateInterval('PT1S')) ];
    }

    /**
     * Returns summary information for e-mail messages as an array
     *
     * @return array Array with summary information
     */
    public function summary(): array
    {
        return $this->db->getMapper('statistics')->summary($this->domain, $this->range, $this->filter);
    }

    /**
     * Returns a list of ip-addresses from which the e-mail messages were received, with some statistics for each one
     *
     * @return array A list of ip-addresses with fields
     *               `ip`, `emails`, `dkim_aligned`, `spf_aligned`
     */
    public function ips(): array
    {
        return $this->db->getMapper('statistics')->ips($this->domain, $this->range, $this->filter);
    }

    /**
     * Returns a list of organizations that sent the reports with some statistics for each one
     *
     * @return array List of organizations with fields `name`, `reports`, `emails`
     */
    public function organizations(): array
    {
        return $this->db->getMapper('statistics')->organizations($this->domain, $this->range, $this->filter);
    }

    /**
     * Sets filter values for the returning information
     *
     * @param array $filter Key-value array with filtering parameters:
     *                      'dkim'         => string, 'fail' or 'pass'
     *                      'organization' => string
     *                      'spf'          => string, 'fail' or 'pass'
     *                      'status'       => string, 'read' or 'unread'
     *
     * @return void
     */
    public function setFilter(array $filter): void
    {
        $this->filter = $filter;
    }
}
