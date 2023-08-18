<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2023 Aleksey Andreev (liuch)
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
 * This file contains the class User
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Users;

use Liuch\DmarcSrg\Core;
use Liuch\DmarcSrg\Exception\SoftException;

/**
 * It's an abstract class for accessing to stored user data
 */
abstract class User
{
    public const LEVEL_ADMIN   = 99;
    public const LEVEL_MANAGER = 50;
    public const LEVEL_USER    = 10;

    /**
     * Returns true if the user exists in the database or false otherwise
     *
     * @return bool Whether the user exists
     */
    public function exists(): bool
    {
        return true;
    }

    /**
     * Returns the user id
     *
     * @return int
     */
    abstract public function id(): int;

    /**
     * Returns the user name
     *
     * @return string
     */
    abstract public function name(): string;

    /**
     * Returns the user access level
     *
     * @return int
     */
    abstract public function level(): int;

    /**
     * Checks if the user is enabled
     *
     * @return bool
     */
    abstract public function isEnabled(): bool;

    /**
     * Returns the sequence number of the session
     *
     * It changes when the user credentials or state are changed.
     *
     * @return int
     */
    public function session(): int
    {
        return 1;
    }

    /**
     * Returns the user's data as an array
     *
     * @return array
     */
    abstract public function toArray(): array;

    /**
     * Converts the integer access level value to string
     *
     * @param int $level
     *
     * @return string
     */
    public static function levelToString(int $level): string
    {
        if ($level >= self::LEVEL_ADMIN) {
            return 'admin';
        }
        if ($level >= self::LEVEL_MANAGER) {
            return 'manager';
        }
        return 'user';
    }

    /**
     * Converts the string acess level to integer value
     *
     * @param string $name Access level name
     *
     * @return int
     */
    public static function stringToLevel(string $name): int
    {
        switch ($name) {
            case 'admin':
                return self::LEVEL_ADMIN;
            case 'manager':
                return self::LEVEL_MANAGER;
            case 'user':
                return self::LEVEL_USER;
        }
        throw new SoftException('Wrong access level name');
    }

    /**
     * Verifies the passed password with the hash stored in the database
     *
     * @param string $password Password to validate
     *
     * @return bool
     */
    abstract public function verifyPassword(string $password): bool;
}
