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
 */

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Domains\Domain;
use Liuch\DmarcSrg\Settings\SettingsList;
use Liuch\DmarcSrg\Exception\RuntimeException;

require 'init.php';

if (Core::isJson()) {
    if (Core::method() == 'GET') {
        try {
            Core::instance()->auth()->isAllowed();

            $result = Core::instance()->status()->get();
            if (!($result['error_code'] ?? 0)) {
                $settings_query = $_GET['settings'] ?? '';
                if (!empty($settings_query)) {
                    $settings = [];
                    foreach (explode(',', $settings_query) as $name) {
                        $setting = SettingsList::getSettingByName($name);
                        $settings[$name] = $setting->value();
                    }
                    $result['settings'] = $settings;
                }
            }

            if (!isset($result['error_code']) || $result['error_code'] === 0) {
                $filter = Common::getFilter();
                if (isset($filter['domain'])) {
                    $domain = new Domain($filter['domain']);
                } else {
                    $domain = null;
                }
                if (isset($filter['month'])) {
                    $range = Common::monthToRange($filter['month']);
                    $stat = Statistics::fromTo($domain, $range[0], $range[1]);
                    $days = $range[0]->format('F Y') . ' (filtered)';
                } else {
                    $days = SettingsList::getSettingByName('status.emails-for-last-n-days')->value();
                    $stat = Statistics::lastNDays($domain, $days);
                    $days = "the last {$days} days";
                }
                if ($filter) {
                    $stat->setFilter($filter);
                }
                $result['emails'] = $stat->summary()['emails'];
                $result['emails']['days'] = $days;
            }

            Core::sendJson($result);
        } catch (RuntimeException $e) {
            $r = ErrorHandler::exceptionResult($e);
            if ($e->getCode() == -2) {
                $r['authenticated'] = 'no';
            }
            Core::sendJson($r);
        }
        return;
    }
}

Core::sendBad();
