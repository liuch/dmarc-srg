<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2022-2023 Aleksey Andreev (liuch)
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
 * This file contains DatabaseExceptionFactory class
 *
 * @category Common
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Exception;

/**
 * Factory class for DatabaseException
 */
class DatabaseExceptionFactory
{
    /**
     * Creates a DatabaseException instance with an appropriate message based on the passed class's name and error code.
     *
     * @param \Exception $origin The original exception
     *
     * @return DatabaseException
     */
    public static function fromException(\Throwable $origin)
    {
        $msg = null;
        if (get_class($origin) === 'PDOException') {
            switch ($origin->getCode()) {
                case 1044:
                case 1045:
                    $msg = 'Database access denied';
                    break;
                case 2002:
                case 2006:
                    $msg = 'Database connection error';
                    break;
            }
        }
        if (!$msg) {
            $msg = 'Database error';
        }
        return new DatabaseException($msg, -1, $origin);
    }
}
