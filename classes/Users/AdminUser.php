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
 * This file contains the class AdminUser
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Users;

use Liuch\DmarcSrg\Core;

/**
 * The class implements the built-in admin user
 */
class AdminUser extends User
{
    /** @var Core */
    private $core = null;

    /**
     * Constructor
     *
     * @param Core|null $core Instance of the Core class
     *
     * @return void
     */
    public function __construct($core = null)
    {
        $this->core = $core ?? Core::instance();
    }

    /**
     * Returns the admin Id
     *
     * @return int
     */
    public function id():int
    {
        return 0;
    }

    /**
     * Returns the admin name
     *
     * @return string
     */
    public function name(): string
    {
        return 'admin';
    }

    /**
     * Returns the admin's access level
     *
     * @return int
     */
    public function level(): int
    {
        return static::LEVEL_ADMIN;
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
        return [
            'id'      => $this->id(),
            'name'    => $this->name(),
            'level'   => $this->level(),
            'enabled' => true
        ];
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
        $r_password = $this->core->config('admin/password');
        return $r_password === null || ($r_password !== '' && $r_password === $password);
    }
}
