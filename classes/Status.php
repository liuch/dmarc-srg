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
     * Returns general state of DmarcSrg
     *
     * This method returns an array with general state of the modules Admin, Auth
     * and statistics for the last N days.
     *
     * @return array
     */
    public function get(): array
    {
        $adm_res = $this->core->admin()->state();
        $res = [
            'state' => $adm_res['state']
        ];

        if (isset($adm_res['error_code'])) {
            $res['error_code'] = $adm_res['error_code'];
            if (isset($adm_res['message'])) {
                $res['message'] = $adm_res['message'];
            }
            if (isset($adm_res['debug_info'])) {
                $res['debug_info'] = $adm_res['debug_info'];
            }
        } elseif (isset($adm_res['database']['error_code'])) {
            $res['error_code'] = $adm_res['database']['error_code'];
            if (isset($adm_res['database']['message'])) {
                $res['message'] = $adm_res['database']['message'];
            }
            if (isset($adm_res['database']['debug_info'])) {
                $res['debug_info'] = $adm_res['database']['debug_info'];
            }
        } elseif (isset($adm_res['message'])) {
            $res['message'] = $adm_res['message'];
        } elseif (isset($adm_res['database']['message'])) {
            $res['message'] = $adm_res['database']['message'];
        }

        $auth = null;
        if ($this->core->auth()->isEnabled()) {
            $res['authenticated'] = $this->core->getCurrentUser() ? 'yes' : 'no';
        } else {
            $res['authenticated'] = 'disabled';
        }
        $res['auth_type'] = $this->core->auth()->authenticationType();
        $res['version'] = APP_VERSION;
        $res['php_version'] = phpversion();

        return $res;
    }
}
