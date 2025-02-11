<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2021-2024 Aleksey Andreev (liuch)
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
 * This file contains the class DomainList
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Domains;

use Liuch\DmarcSrg\Core;

/**
 * This class is designed to work with the list of domains
 */
class DomainList
{
    private $db   = null;
    private $user = null;

    /**
     * The constructor
     *
     * @param \Liuch\DmarcSrg\Users\User                  $user User to which domais are assigned
     * @param \Liuch\DmarcSrg\Database\DatabaseController $db   The database controller
     */
    public function __construct($user, $db = null)
    {
        $this->user = $user;
        $this->db   = $db ?? Core::instance()->database();
    }

    /**
     * Returns a list of domains from the database
     *
     * @return array Array with instances of Domain class
     */
    public function getList(): array
    {
        $list = [];
        foreach ($this->db->getMapper('domain')->list($this->user->id()) as $dd) {
            $list[] = new Domain($dd, $this->db);
        }
        return [
            'domains' => $list,
            'more'    => false
        ];
    }

    /**
     * Returns an ordered array with domain names from the database
     *
     * @return array Array of strings
     */
    public function names(): array
    {
        return $this->db->getMapper('domain')->names($this->user->id());
    }
}
