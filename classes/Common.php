<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2020 Aleksey Andreev (liuch)
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
 * This file contains common classes
 *
 * @category Common
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg;

/**
 * Static common arrays
 */
class Common
{
    /**
     * This array needs for converting the align result text constant to integer value and back
     * in Report and ReportList classes
     *
     * @var string[]
     */
    public static $align_res = [ 'fail', 'unknown', 'pass' ];

    /**
     * This array needs for converting the the disposition result text constant to integer value and back
     * in Report and ReportList classes
     *
     * @var string[]
     */
    public static $disposition = [ 'reject', 'quarantine', 'none' ];
}
