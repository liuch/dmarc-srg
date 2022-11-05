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
 * This file contains Auth class.
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Exception\AuthException;

/**
 * Class for working with authentication data.
 */
class Auth
{
    private $core = null;

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
     * Checks if authentication is enabled.
     *
     * The method examines the key `password` in $admin array from the config file.
     * It must exist and not be null.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->core->config('admin/password') !== null;
    }

    /**
     * The authetication with a username and password.
     *
     * This method checks the password passed in $password and creates a user session.
     * This method throws an exception if the passed password is wrong.
     * The password with an empty string is always wrong!
     *
     * @param string $username - Must be an empty string, it is currently not used.
     * @param string $password - Must not be an empty string.
     *
     * @return array Array with `error_code` and `message` fields.
     */
    public function login(string $username, string $password): array
    {
        if ($username !== '' || $this->core->config('admin/password') === '' || !$this->isAdminPassword($password)) {
            throw new AuthException('Authentication failed. Try again');
        }
        $this->core->userId(0);
        return [
            'error_code' => 0,
            'message'    => 'Authentication succeeded'
        ];
    }

    /**
     * Removes the current user's session.
     *
     * @return array Array with `error_code` and `message` fields.
     */
    public function logout(): array
    {
        $this->core->destroySession();
        return [
            'error_code' => 0,
            'message'    => 'Logged out successfully'
        ];
    }

    /**
     * Checks if the user session exists.
     *
     * This method throws an exception if authentication needed.
     *
     * @return void
     */
    public function isAllowed(): void
    {
        if ($this->isEnabled()) {
            if ($this->core->userId() === false) {
                throw new AuthException('Authentication needed', -2);
            }
        }
    }

    /**
     * Checks if the passed password is the admin password.
     *
     * Throws an exception if the passed password is not the admin password.
     *
     * @param string $password Password to check
     *
     * @return void
     */
    public function checkAdminPassword(string $password): void
    {
        if (!$this->isAdminPassword($password)) {
            throw new AuthException('Incorrect password');
        }
    }

    /**
     * Checks if $password equals the admin password.
     *
     * @param string $password Password to check
     *
     * @return bool
     */
    private function isAdminPassword(string $password): bool
    {
        return $password === $this->core->config('admin/password');
    }
}
