<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2026 Aleksey Andreev (liuch)
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
 * This file contains the class AdHocUser
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Users;

use Liuch\DmarcSrg\Core;
use Liuch\DmarcSrg\Exception\LogicException;

/**
 * The class implements a temporary user with the specified permissions.
 */
class AdHocUser extends User
{
    /**
     * Constructor
     *
     * @param array Array of permissions
     *
     * @return void
     */
    public function __construct(int $permissions = 0)
    {
        $this->permissions = $permissions;
    }

    /**
     * Returns the user Id
     *
     * @return int
     */
    public function id():int
    {
        return -1;
    }

    /**
     * Returns the user name
     *
     * @return string
     */
    public function name(): string
    {
        $this->throwUnsupportedMethod(__METHOD__);
    }

    /**
     * Returns the admin's access level
     *
     * @return int
     */
    public function level(): int
    {
        return static::LEVEL_SERVICE;
    }

    /**
     * Checks if the user is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * Returns the admin's data as an array
     *
     * @return array
     */
    public function toArray(): array
    {
        $this->throwUnsupportedMethod(__METHOD__);
    }

    /**
     * Verifies the passed password with the password from the configuration file
     *
     * @param string $password Password to validate
     *
     * @return bool
     */
    public function verifyPassword(string $password): bool
    {
        $this->throwUnsupportedMethod(__METHOD__);
    }

    /**
     * Throws an exception for unsupported methods
     *
     * @param string $method Method name
     *
     * @return never
     *
     * @throws LogicException
     */
    private function throwUnsupportedMethod(string $method): never
    {
        throw new LogicException("Method \"$method\" is strictly prohibited for service-level users");
    }
}
