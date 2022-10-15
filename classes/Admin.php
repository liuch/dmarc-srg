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
 * This file contains Admin class
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Mail\MailBoxes;
use Liuch\DmarcSrg\Database\Database;
use Liuch\DmarcSrg\Directories\DirectoryList;
use Liuch\DmarcSrg\Exception\LogicException;

/**
 * It's the main class for accessing administration functions.
 */
class Admin
{
    /**
     * Returns information about the database, directories, and mailboxes as an array.
     *
     * @return array Contains fields: `database`, `state`, `mailboxes`, `directories`.
     */
    public function state(): array
    {
        $res = [];
        $res['database']    = Database::state();
        $res['mailboxes']   = (new MailBoxes())->list();
        $res['directories'] = array_map(function ($dir) {
            return $dir->toArray();
        }, (new DirectoryList())->list());

        if ($res['database']['correct'] ?? false) {
            $res['state'] = 'Ok';
        } else {
            $res['state'] = 'Err';
        }

        return $res;
    }

    /**
     * Checks the availability of report sources.
     *
     * @param int    $id   Id of the checked source. If $id == 0 then all available sources with the passed type
     *                     will be checked.
     * @param string $type Type of the checked source.
     *
     * @return array Result array with `error_code` and `message` fields.
     *               For one resource and if there is no error,
     *               a field `status` will be added to the result.
     */
    public function checkSource(int $id, string $type): array
    {
        switch ($type) {
            case 'mailbox':
                return (new MailBoxes())->check($id);
            case 'directory':
                return (new DirectoryList())->check($id);
        }
        throw new LogicException('Unknown resource type');
    }
}
