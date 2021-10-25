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
 * This script performs some administration functions with the database.
 * It has one required parameter, it can has follow values: `status`, `init`, `upgrade`, `drop`.
 *   `status`  - Will display the database status.
 *   `init`    - Will create all the required tables in the database.
 *               The database must contain no tables.
 *   `upgrade` - Will upgrade the database structure if it is necessary.
 *   `drop`    - Will remove all tables from the database.
 *
 * @category Utilities
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg;

use Exception;
use Liuch\DmarcSrg\Database\Database;
use Liuch\DmarcSrg\Database\DatabaseUpgrader;
use Liuch\DmarcSrg\Settings\SettingString;

require 'init.php';

if (php_sapi_name() !== 'cli') {
    echo 'Forbidden';
    exit(1);
}

$action = $argv[1] ?? '';
$res = null;
try {
    switch ($action) {
        case 'status':
            $res = Core::admin()->state()['database'];
            $tcn = 0;
            foreach ($res['tables'] as &$t) {
                if (isset($t['exists']) && $t['exists'] === true) {
                    ++$tcn;
                }
            }
            unset($t);
            echo 'Version: ' . (isset($res['version']) ? $res['version'] : 'n/a') . "\n";
            echo 'Tables:  ' . $tcn . "\n";
            break;
        case 'init':
            $res = Database::initDb();
            break;
        case 'upgrade':
            $cur_ver = (new SettingString('version'))->value();
            if ($cur_ver === '') {
                $cur_ver = 'n/a';
            }
            echo "Current version:  ${cur_ver}\n";
            echo 'Required version: ' . Database::REQUIRED_VERSION . "\n";
            if ($cur_ver !== Database::REQUIRED_VERSION) {
                DatabaseUpgrader::go();
                $res = [
                    'error_code' => 0,
                    'message'    => 'Upgrated successfully'
                ];
            } else {
                $res = [
                    'error_code' => 0,
                    'message'    => 'No upgrade required'
                ];
            }
            break;
        case 'drop':
            $res = Database::dropTables();
            break;
        default:
            echo "Usage: ${argv[0]} status|init|upgrade|drop\n";
            exit(1);
    }
} catch (Exception $e) {
    $res = [
        'error_code' => $e->getCode(),
        'message'    => $e->getMessage()
    ];
}

$error = (isset($res['error_code']) && $res['error_code'] !== 0) ? 'Error! ' : '';
echo "Message: ${error}${res['message']}\n";

exit(0);
