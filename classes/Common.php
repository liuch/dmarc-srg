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

use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\RuntimeException;

/**
 * Static common arrays and methods
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

    /**
     * Retrieves filter values from HTTP GET parameters and returns them as a key-value array or null
     *
     * @return array|null
     */
    public static function getFilter()
    {
        $filter = null;
        if (isset($_GET['filter'])) {
            $filter = [];
            $pa = gettype($_GET['filter']) == 'array' ? $_GET['filter'] : [ $_GET['filter'] ];
            foreach ($pa as $it) {
                $ia = explode(':', $it, 2);
                if (count($ia) == 2) {
                    $filter[$ia[0]] = $ia[1];
                }
            }
        }
        return $filter;
    }

    /**
     * Converts month to date range
     *
     * @param string $month   Month representation in yyyy-mm format
     *
     * @return array Range array:
     *               - DateTime, Start of the month
     *               - DateTime, First second of the next month
     */
    public static function monthToRange(string $month): array
    {
        $ma = explode('-', $month);
        if (count($ma) != 2) {
            throw new SoftException('Incorrect date format');
        }
        $year = intval($ma[0]);
        $month = intval($ma[1]);
        if ($year <= 0 || $month < 1 || $month > 12) {
            throw new SoftException('Incorrect month or year value');
        }
        $date1 = new DateTime("{$year}-{$month}-01");
        $date2 = (clone $date1)->modify('first day of next month');
        return [ $date1, $date2 ];
    }

    /**
     * Returns the range string in short format (without the current year)
     *
     * @param array $range Array with two dates
     *
     * @return string
     */
    public static function rangeToString(array $range): string
    {
        $cyear = (new \Datetime())->format('Y');
        $dform = ($range[0]->format('Y') !== $cyear || $range[1]->format('Y') !== $cyear) ? 'M d Y' : 'M d';
        $res = $range[0]->format($dform);
        if ($range[0] != $range[1]) {
            $res .= ' - ' . $range[1]->format($dform);
        }
        return $res;
    }

    /**
     * Returns the percentage with the original number. If $per is 0 then '0' is returned.
     *
     * @param int  $per      Value
     * @param int  $cent     Divisor for percentage calculation
     * @param bool $with_num Whether to add the numeric value to the result
     *
     * @return string
     */
    public static function num2percent(int $per, int $cent, bool $with_num): string
    {
        if (!$per) {
            return '0';
        }
        $res = sprintf('%.0f%%', $per / $cent * 100);
        if ($with_num) {
            $res .= "({$per})";
        }
        return $res;
    }

    /**
     * Converts array to CSV string
     *
     * @param array $data Array of data to be converted
     *
     * @return string CSV string
     */
    public static function arrayToCSV(array $data): string
    {
        $tfd = fopen('php://temp/maxmemory:' . (10 * 1024 * 1024), 'r+');
        foreach ($data as &$row) {
            if (!fputcsv($tfd, is_array($row) ? $row : [ $row ], ',', '"', "\\", "\n\r")) {
                throw new RuntimeException('fputcsv failed');
            }
        }
        unset($row);
        rewind($tfd);
        $res = stream_get_contents($tfd);
        fclose($tfd);
        return $res;
    }

    /**
     * Returns a random string of the specified length
     *
     * @param int String length
     *
     * @return string
     */
    public static function randomString(int $len = 16): string
    {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $c_len = strlen($chars);
        $res   = '';
        do {
            $res .= $chars[random_int(0, 61)];
        } while (--$len > 0);
        return $res;
    }
}
