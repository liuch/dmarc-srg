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
 * This file contains the StatisticsMapperInterface
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Database;

interface StatisticsMapperInterface
{
    /**
     * Returns summary information for the specified domain and date range
     *
     * @param Domain|null $domain Domain for which the information is needed. Null is for all domains.
     * @param array       $range  Array with two dates
     *
     * @return array Array with Summary information:
     *                          'emails' => [
     *                              'total'            => total email processed (int)
     *                              'dkim_spf_aligned' => Both DKIM and SPF aligned (int)
     *                              'dkim_aligned'     => Only DKIM aligned (int)
     *                              'spf_aligned'      => Only SPF aligned (int)
     *                              'quarantined'      => Quarantined (int)
     *                              'rejected'         => Rejected (int)
     *                          ];
     */
    public function summary($domain, array &$range): array;

    /**
     * Returns a list of ip-addresses from which the e-mail messages were received, with some statistics for each one
     *
     * @param Domain|null $domain Domain for which the information is needed. Null is for all domains.
     * @param array       $range  Array with two dates
     *
     * @return array A list of ip-addresses with fields `ip`, `emails`, `dkim_aligned`, `spf_aligned`
     */
    public function ips($domain, array &$range): array;

    /**
     * Returns a list of organizations that sent the reports with some statistics for each one
     *
     * @param Domain|null $domain Domain for which the information is needed. Null is for all domains.
     * @param array       $range  Array with two dates
     *
     * @return array List of organizations with fields `name`, `reports`, `emails`
     */
    public function organizations($domain, array &$range): array;
}
