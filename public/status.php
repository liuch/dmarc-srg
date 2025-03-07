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
 */

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Users\User;
use Liuch\DmarcSrg\Domains\Domain;
use Liuch\DmarcSrg\Settings\SettingsList;
use Liuch\DmarcSrg\Exception\RuntimeException;

require realpath(__DIR__ . '/..') . '/init.php';

if (Core::isJson()) {
    if (Core::requestMethod() == 'GET') {
        try {
            $core = Core::instance();
            $core->auth()->isAllowed(User::LEVEL_USER);

            $fields = explode(',', $_GET['fields'] ?? '');
            if (in_array('state', $fields)) {
                $result = $core->status()->get();
            } else {
                $result = [];
            }

            if (!($result['error_code'] ?? 0)) {
                if (in_array('user', $fields)) {
                    $user = $core->getCurrentUser();
                    $result['user'] = [
                        'name'  => $user->name(),
                        'level' => User::levelToString($user->level())
                    ];
                }

                if (in_array('settings', $fields) && !empty($_GET['settings'])) {
                    $settings = [];
                    foreach (explode(',', $_GET['settings']) as $name) {
                        $setting = SettingsList::getSettingByName($name);
                        $settings[$name] = $setting->value();
                    }
                    $result['settings'] = $settings;
                }

                if (in_array('emails', $fields)) {
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
                    $stat->setUser($core->getCurrentUser());
                    if ($filter) {
                        $stat->setFilter($filter);
                    }
                    $result['emails'] = $stat->summary()['emails'];
                    $result['emails']['days'] = $days;
                }
            }

            Core::sendJson($result);
        } catch (RuntimeException $e) {
            $r = ErrorHandler::exceptionResult($e);
            Core::sendJson($r);
        }
        return;
    }
}

Core::sendBad();
