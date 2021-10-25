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

/**
 * It's the main class for accessing administration functions.
 */
class Admin
{
    /**
     * Returns information about the database and mailboxes as an array.
     *
     * @return array Contains fields: `database`, `state`, `mailboxes`.
     */
    public function state(): array
    {
        $res = [
            'database'  => Database::state(),
            'mailboxes' => (new MailBoxes())->list()
        ];

        if ($res['database']['correct'] ?? false) {
            $res['state'] = 'Ok';
        } else {
            $res['state'] = 'Err';
        }

        return $res;
    }

    /**
     * Checks the availability of report sources. So far these are only mailboxes.
     *
     * @param int    $id   Id of the checked source. If $id == 0 then all available
     *                     mailboxes will be checked.
     * @param string $type Type of the checked source.
     *                     So far it is only a `mailbox`.
     *
     * @return array Result array with `error_code` and `message` fields.
     *               For one resource and if there is no error,
     *               a field `status` will be added to the result.
     */
    public function checkSource(int $id, string $type): array
    {
        try {
            if ($type === 'mailbox') {
                return (new MailBoxes())->check($id);
            } else {
                throw new \Exception('Unknown resource type', -1);
            }
        } catch (\Exception $e) {
            return [
                'error_code' => $e->getCode(),
                'message'    => $e->getMessage()
            ];
        }
        return [ 'message' => 'Successfully' ];
    }
}
