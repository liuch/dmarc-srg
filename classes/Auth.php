<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2020-2025 Aleksey Andreev (liuch)
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

use Liuch\DmarcSrg\ErrorCodes;
use Liuch\DmarcSrg\Users\UserList;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\AuthException;
use Liuch\DmarcSrg\Exception\ForbiddenException;

/**
 * Class for working with authentication data.
 */
class Auth
{
    /** @var Core */
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
     * @param string $username - User name.
     * @param string $password - Password. Must not be an empty string.
     *
     * @return array Array with `error_code` and `message` fields.
     */
    public function login(string $username, string $password): array
    {
        $atype = $this->authenticationType();
        if ($atype === 'password-only') {
            $username = empty($username) ? 'admin' : '';
        }
        if (!empty($username)) {
            try {
                $user = UserList::getUserByName($username, $this->core);
                if ($user->verifyPassword($password)) {
                    if (!$user->isEnabled()) {
                        throw new AuthException('The user is disabled. Contact the administrator');
                    }
                    $this->core->user($user);
                    return [
                        'error_code' => 0,
                        'message'    => 'Authentication succeeded'
                    ];
                }
            } catch (AuthException $e) {
                throw $e;
            } catch (SoftException $e) {
            }
        }
        throw new AuthException('Authentication failed. Try again');
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
     * This method throws an exception if the authentication or authorization check fails.
     *
     * @param int $level Minimum access level required
     *
     * @return void
     */
    public function isAllowed(int $level): void
    {
        if ($this->isEnabled()) {
            $user = $this->core->user();
            if (!$user) {
                throw new AuthException('Authentication needed', ErrorCodes::AUTH_NEEDED, $this->authenticationType());
            }
            if ($user->level() < $level) {
                throw new ForbiddenException('Forbidden');
            }
        }
    }

    /**
     * Returns the type of authentication
     *
     * @return string `base`          - User name and password
     *                `password-only` - Password only
     *                `none`          - Authentication is disabled
     */
    public function authenticationType(): string
    {
        if ($this->isEnabled()) {
            return $this->core->config('users/user_management', false) ? 'base' : 'password-only';
        }
        return 'none';
    }
}
