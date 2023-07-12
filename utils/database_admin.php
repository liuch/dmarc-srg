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

use Liuch\DmarcSrg\Database\Database;
use Liuch\DmarcSrg\Database\DatabaseUpgrader;
use Liuch\DmarcSrg\Settings\SettingString;
use Liuch\DmarcSrg\Exception\RuntimeException;

require 'init.php';

if (php_sapi_name() !== 'cli') {
    echo 'Forbidden';
    exit(1);
}

$res  = null;
$core = Core::instance();
try {
    $core->user('admin');
    $action = $argv[1] ?? '';
    switch ($action) {
        case 'status':
            $res = $core->database()->state();
            $tcn = 0;
            if (isset($res['tables'])) {
                foreach ($res['tables'] as &$t) {
                    if (isset($t['exists']) && $t['exists'] === true) {
                        ++$tcn;
                    }
                }
                unset($t);
            }
            echo 'Version: ', ($res['version'] ?? 'n/a'), PHP_EOL;
            echo 'Tables:  ', $tcn, PHP_EOL;
            break;
        case 'init':
            $res = $core->database()->initDb();
            break;
        case 'upgrade':
            $db = $core->database();
            $cur_ver = $db->state()['version'] ?? 'n/a';
            echo "Current version:  {$cur_ver}", PHP_EOL;
            echo 'Required version: ', $db::REQUIRED_VERSION, PHP_EOL;
            if ($cur_ver !== $db::REQUIRED_VERSION) {
                $db->getMapper('upgrader')->go($db::REQUIRED_VERSION);
                $res = [
                    'error_code' => 0,
                    'message'    => 'Upgraded successfully'
                ];
            } else {
                $res = [
                    'error_code' => 0,
                    'message'    => 'No upgrade required'
                ];
            }
            break;
        case 'drop':
            $res = $core->database()->cleanDb();
            break;
        default:
            echo "Usage: {$argv[0]} status|init|upgrade|drop", PHP_EOL;
            exit(1);
    }
} catch (RuntimeException $e) {
    $res = ErrorHandler::exceptionResult($e);
}

$error = ($res['error_code'] ?? 0) ? 'Error! ' : '';
echo "Message: {$error}{$res['message']}", PHP_EOL;

if (isset($res['debug_info'])) {
    $debug_info = $res['debug_info'];
    echo "Debug info:", PHP_EOL;
    echo '-----', PHP_EOL;
    echo "[{$debug_info['code']}]", PHP_EOL;
    echo '-----', PHP_EOL;
    echo "{$debug_info['content']}", PHP_EOL;
}

exit(0);
