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
 * This file contains the class UserList
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
 * This class is designed to work with the list of users
 */
class UserList
{
    private $db = null;

    /**
     * The constructor
     *
     * @param \Liuch\DmarcSrg\Database\DatabaseController $db The database controller
     */
    public function __construct($db = null)
    {
        $this->db = $db ?? Core::instance()->database();
    }

    /**
     * Returns a list of users from the database
     *
     * @return array Array with instances of User class
     */
    public function getList(): array
    {
        $list = array_map(function ($udata) {
            return new DbUser($udata, $this->db);
        }, $this->db->getMapper('user')->list());
        return [
            'users' => $list,
            'more'  => false
        ];
    }

    /**
     * Returns an instance of the User class with the username from $username if such a user exists
     *
     * @param string    $username User name
     * @param Core|null $core     Instance of the Core class
     *
     * @return User
     */
    public static function getUserByName(string $username, $core = null)
    {
        if ($username === 'admin') {
            return new AdminUser($core);
        }
        $user = new DbUser($username, ($core ?? Core::instance())->database());
        if (!$user->exists()) {
            throw new SoftException('Unknown user: ' . $username);
        }
        return $user;
    }
}
