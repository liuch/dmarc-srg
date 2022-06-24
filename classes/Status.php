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
 * This file contains the class Status
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Settings\SettingsList;

/**
 * This class is designed to get the general state of DmarcSrg
 */
class Status
{
    /**
     * Returns general state of DmarcSrg
     *
     * This method returns an array with general state of the modules Admin, Auth
     * and statistics for the last N days.
     *
     * @return array
     */
    public function get(): array
    {
        $adm_res = Core::admin()->state();
        $res = [
            'state' => $adm_res['state']
        ];

        if (isset($adm_res['error_code'])) {
            $res['error_code'] = $adm_res['error_code'];
            if (isset($adm_res['message'])) {
                $res['message'] = $adm_res['message'];
            }
        } elseif (isset($adm_res['database']['error_code'])) {
            $res['error_code'] = $adm_res['database']['error_code'];
            if (isset($adm_res['database']['message'])) {
                $res['message'] = $adm_res['database']['message'];
            }
        } elseif (isset($adm_res['message'])) {
            $res['message'] = $adm_res['message'];
        } elseif (isset($adm_res['database']['message'])) {
            $res['message'] = $adm_res['database']['message'];
        }

        if (!isset($res['error_code']) || $res['error_code'] === 0) {
            $days = SettingsList::getSettingByName('status.emails-for-last-n-days')->value();
            $stat = Statistics::lastNDays(null, $days);
            $res['emails'] = $stat->summary()['emails'];
            $res['emails']['days'] = $days;
        }

        $auth = null;
        if (Core::auth()->isEnabled()) {
            $auth = Core::userId() !== false ? 'yes' : 'no';
        } else {
            $auth = 'disabled';
        }
        $res['authenticated'] = $auth;
        $res['version'] = Core::APP_VERSION;

        return $res;
    }
}
