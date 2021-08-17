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
 * This file contains the class DomainList
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Domains;

use PDO;
use Liuch\DmarcSrg\Database\Database;

/**
 * This class is designed to work with the list of domains
 */
class DomainList
{
    /**
     * Returns a list of domains from the database
     *
     * @return array Array with instances of Domain class
     */
    public function getList(): array
    {
        $list = [];
        $st = Database::connection()->query('SELECT `id`, `fqdn`, `active`, `description`, `created_time`, `updated_time` FROM `domains`');
        while ($row = $st->fetch(PDO::FETCH_NUM)) {
            $list[] = new Domain([
                'id'           => intval($row[0]),
                'fqdn'         => $row[1],
                'active'       => boolval($row[2]),
                'description'  => $row[3],
                'created_time' => strtotime($row[4]),
                'updated_time' => strtotime($row[5])
            ]);
        }
        $st->closeCursor();
        return [
            'domains' => $list,
            'more'    => false
        ];
    }

    /**
     * Returns the total number of domains in the database
     *
     * @return int The total number of domains
     */
    public static function count(): int
    {
        $st = Database::connection()->query('SELECT COUNT(*) FROM `domains`', PDO::FETCH_NUM);
        $res = intval($st->fetchColumn(0));
        $st->closeCursor();
        return $res;
    }
}

