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
 * This file contains ErrorHandler class
 *
 * @category Common
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Log\LoggerInterface;
use Liuch\DmarcSrg\Log\LoggerAwareInterface;
use Liuch\DmarcSrg\Exception\SoftException;

/**
 * Uncaught exception handler
 */
class ErrorHandler implements LoggerAwareInterface
{
    private $core   = null;
    private $logger = null;

    /**
     * The constructor
     *
     * @param Core $core
     */
    public function __construct(object $core)
    {
        $this->core = $core;
    }

    /**
     * Handle uncaught exceptions. Used by set_exception_handler and set_error_handler functions
     *
     * @param \Throwable $e an exception to handle. For set_error_handler it is ErrorException.
     *
     * @return void
     */
    public function handleException(\Throwable $e): void
    {
        $debug = $this->core->config('debug', 0);

        if ($this->logger) {
            $this->logger->error(strval($e));
        }

        if (php_sapi_name() === 'cli') {
            echo self::getText($e, $debug);
            exit(1);
        } else {
            Core::sendJson(self::getResult($e, $debug));
        }
    }

    /**
     * Returns an result array based on the passed exception's data.
     * If the debug mode is enabled, the `debug_info` field will be added to the result.
     *
     * @param \Throwable $e an exception for which the result is generated
     *
     * @return array
     */
    public static function exceptionResult(\Throwable $e): array
    {
        return self::getResult($e, Core::instance()->config('debug', 0));
    }

    /**
     * Returns information about the passed exception as text.
     * If the debug is enabled, debug information will be added.
     *
     * @param \Throwable $e an exception for which the text is generated
     *
     * @return string
     */
    public static function exceptionText(\Throwable $e): string
    {
        return self::getText($e, Core::instance()->config('debug', 0));
    }

    /**
     * Sets a logger to log uncaught exceptions and errors
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Returns the current logger
     */
    public function logger()
    {
        return $this->logger;
    }

    private static function getResult(\Throwable $e, int $debug): array
    {
        $code = $e->getCode();
        if ($code === 0) {
            $code = -1;
        }
        $res = [
            'error_code' => $code,
            'message'    => $e->getMessage()
        ];
        if ($debug &&
            (Core::instance()->userId() !== false || php_sapi_name() === 'cli') &&
            !($e instanceof SoftException)
        ) {
            $prev = $e->getPrevious();
            $res['debug_info'] = [
                'code'    => ($prev ?? $e)->getCode(),
                'content' => strval($prev ?? $e)
            ];
        }
        return $res;
    }

    private static function getText(\Throwable $e, int $debug): string
    {
        $msg = 'Error: ' . $e->getMessage() . ' (' . $e->getCode() . ')' . PHP_EOL;
        if (!$debug) {
            return $msg;
        }

        return '-----' . PHP_EOL
            . $msg
            . '-----' . PHP_EOL
            . $e . PHP_EOL;
    }
}
