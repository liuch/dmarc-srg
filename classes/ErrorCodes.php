<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2025 Aleksey Andreev (liuch)
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
 * This file contains error codes
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg;

/**
 * Error codes
 */
class ErrorCodes
{
    /*
     * Authentication is required
     */
    public const AUTH_NEEDED = -2;

    /*
     * The database is missing one or more tables (not all).
     */
    public const INCORRECT_TABLE_SET = -3;

    /*
     * Database initiation error. The database is not empty
     */
    public const DB_NOT_EMPTY = -4;

    /*
     * Domain deletion error: there are incoming reports for the domain.
     */
    public const DOMAIN_HAS_REPORTS = -10;
}
