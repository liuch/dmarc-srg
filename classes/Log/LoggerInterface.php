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
 * This file contains the interface LoggerInterface
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Log;

/**
 * Describes a logger instance
 * The message MUST be a string or object implementing __toString().
 */
interface LoggerInterface
{
    /**
     * System is unusable
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function emergency($message, array $context = []): void;

    /**
     * Action must be taken immediately
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function alert($message, array $context = []): void;

    /**
     * Critical conditions
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function critical($message, array $context = []): void;

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function error($message, array $context = []): void;

    /**
     * Exceptional occurrences that are not errors
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function warning($message, array $context = []): void;

    /**
     * Normal but significant events
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function notice($message, array $context = []): void;

    /**
     * Interesting events
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function info($message, array $context = []): void;

    /**
     * Detailed debug information
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function debug($message, array $context = []): void;

    /**
     * Logs with an arbitrary level
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function log($level, $message, array $context = []): void;
}
