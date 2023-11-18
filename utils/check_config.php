<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2023 Aleksey Andreev (liuch)
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
 * This script is for checking configuration.
 *
 * The script can be useful for checking your configuration.
 *
 * @category Utilities
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Mail\MailBoxes;
use Liuch\DmarcSrg\Directories\DirectoryList;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\RuntimeException;

require realpath(__DIR__ . '/..') . '/init.php';

if (php_sapi_name() !== 'cli') {
    echo 'Forbidden';
    exit(1);
}

const RESULT_SUCCESS = 1;
const RESULT_WARNING = 2;
const RESULT_ERROR   = 3;
const RESULT_FATAL   = 4;

const CONFIG_FILE = 'config/conf.php';

$e_cnt = 0;
$w_cnt = 0;
$m_str = '';

$startChecking = function (string $message, int $margin = 0) use (&$m_str): void {
    $col_w = 35;
    $m_str = str_repeat('  ', $margin);
    $s = "{$m_str}  * {$message}";
    echo $s, str_repeat('.', max($col_w - strlen($s), 0)), ' ';
};

$endChecking = function (string $message = '', int $result = 0) use (&$e_cnt, &$w_cnt, &$m_str): void {
    if ($result === 0) {
        $result = empty($message) ? RESULT_SUCCESS : RESULT_ERROR;
    }
    switch ($result) {
        case RESULT_SUCCESS:
            echo 'Ok', PHP_EOL;
            break;
        case RESULT_WARNING:
            ++$w_cnt;
            echo 'Warning', PHP_EOL;
            break;
        case RESULT_ERROR:
            ++$e_cnt;
            echo 'Fail', PHP_EOL;
            break;
        case RESULT_FATAL:
            echo 'Fail', PHP_EOL;
            throw new SoftException($message);
    }
    if (!empty($message)) {
        echo "{$m_str}    Message: ", $message, PHP_EOL;
    }
};

$core = Core::instance();
$core->user('admin');

ob_start(null, 0, PHP_OUTPUT_HANDLER_FLUSHABLE | PHP_OUTPUT_HANDLER_REMOVABLE);
$core->config('debug'); // Just in order to load the config file
$empty_buf = empty(ob_get_flush());

try {
    echo '=== GENERAL INFORMATION ===', PHP_EOL;
    $uname = implode(' ', array_map(function ($mode) {
        return php_uname($mode);
    }, [ 's', 'r', 'v', 'm' ]));
    echo '  * OS information: ', $uname, PHP_EOL;
    echo '  * PHP version:    ', phpversion(), PHP_EOL;

    echo PHP_EOL, '=== EXTENSIONS ===', PHP_EOL;
    foreach ([ 'pdo_mysql', 'xmlreader', 'zip', 'json' ] as $ext) {
        $startChecking($ext);
        if (extension_loaded($ext)) {
            $endChecking();
        } else {
            $endChecking('The extension is not loaded');
        }
    }

    echo PHP_EOL, '=== CONFIG FILE ===', PHP_EOL;
    $startChecking('Checking if the file exists');
    $config_file_path = realpath(__DIR__ . '/..') . '/' . CONFIG_FILE;
    if (is_file($config_file_path)) {
        $endChecking();
    } else {
        $endChecking('The configuration file `config/conf.php` not found', RESULT_FATAL);
    }
    $startChecking('Checking read permission');
    if (is_readable($config_file_path)) {
        $endChecking();
    } else {
        $endChecking('The configuration file is not readable', RESULT_FATAL);
    }
    $startChecking('Checking write permission');
    if (!is_writable($config_file_path)) {
        $endChecking();
    } else {
        $endChecking('The configuration file is writable', RESULT_WARNING);
    }
    $startChecking('Checking access by other users');
    $r = fileperms($config_file_path);
    if ($r !== false) {
        if (($r & 0x6) !== 0) {
            $endChecking('The configuration file is accessible to other users', RESULT_WARNING);
        } else {
            $endChecking();
        }
    } else {
        $endChecking('Fileperms failed', RESULT_ERROR);
    }
    $startChecking('Checking the output buffer');
    if ($empty_buf) {
        $endChecking();
    } else {
        $endChecking('There are extra characters before the "<?php" string');
    }

    echo PHP_EOL, '=== DATABASE ===', PHP_EOL;
    $startChecking('Accessibility check');
    try {
        $db_s = $core->database()->state();
        $endChecking();
    } catch (RuntimeException $e) {
        $db_s = null;
        $endChecking($e->getMessage());
    }
    if ($db_s) {
        $startChecking('Checking for integrity');
        if ($db_s) {
            $endChecking();
        } else {
            $endChecking($db_s['message'], ($db_s['needs_upgrade'] ?? false) ? RESULT_WARNING : RESULT_ERROR);
        }
    }

    echo PHP_EOL, '=== MAILBOXES ===', PHP_EOL;
    $startChecking('Checking mailboxes config');
    try {
        $mb_list = new MailBoxes();
        $mb_lcnt = $mb_list->count();
        if ($mb_lcnt === 0) {
            $endChecking('No mailboxes found', RESULT_SUCCESS);
        } else {
            $endChecking("{$mb_lcnt} mailbox" . ($mb_lcnt > 1 ? 'ex' : '') . ' found', RESULT_SUCCESS);
            $startChecking('Imap extension');
            if (extension_loaded('imap')) {
                $endChecking();
                echo "  * Checking mailboxes ({$mb_lcnt})", PHP_EOL;
                for ($mb_num = 1; $mb_num <= $mb_lcnt; ++$mb_num) {
                    $mb = $mb_list->mailbox($mb_num);
                    echo "    - {$mb->name()}", PHP_EOL;
                    $startChecking('Accessibility', 2);
                    $res = $mb->check();
                    if (!$res['error_code']) {
                        $endChecking();
                    } else {
                        $endChecking($res['message'] ?? null);
                    }
                }
            } else {
                $endChecking('The extension is not loaded');
            }
        }
    } catch (RuntimeException $e) {
        $endChecking($e->getMessage());
    }

    echo PHP_EOL, '=== DIRECTORIES ===', PHP_EOL;
    $startChecking('Checking directories config');
    try {
        $dir_list = (new DirectoryList())->list();
        $dir_lcnt = count($dir_list);
        if ($dir_lcnt === 0) {
            $endChecking('No directories found', RESULT_SUCCESS);
        } else {
            $endChecking("{$dir_lcnt} director" . ($dir_lcnt > 1 ? 'ies' : 'y') . ' found', RESULT_SUCCESS);
            echo "  * Checking directories ({$dir_lcnt})", PHP_EOL;
            foreach ($dir_list as $dir) {
                $dir_a = $dir->toArray();
                echo "    - {$dir_a['name']}", PHP_EOL;
                $startChecking('Accessibility', 2);
                $res = $dir->check();
                if (!$res['error_code']) {
                    $endChecking();
                    $startChecking('Security', 2);
                    try {
                        $perms = fileperms($dir_a['location']);
                        if ($perms === false) {
                            throw new RuntimeException('Fileperms failed', RESULT_ERROR);
                        }
                        if ($perms & 0x2) {
                            throw new RuntimeException(
                                'Other users have write access to the directory',
                                RESULT_WARNING
                            );
                        }
                        $endChecking();
                    } catch (RuntimeException $e) {
                        $endChecking($e->getMessage(), $e->getCode());
                    } catch (\ErrorException $e) {
                        $endChecking($e->getMessage());
                    }
                } else {
                    $endChecking($res['message'] ?? null);
                }
            }
        }
    } catch (SoftException $e) {
        $endChecking($e->getMessage());
    }

    echo PHP_EOL, '===', PHP_EOL;
    if ($e_cnt === 0 && $w_cnt === 0) {
        echo 'Success!', PHP_EOL;
    } else {
        echo 'There ', $e_cnt + $w_cnt === 1 ? 'is' : 'are', ' ';
        $strs = [];
        foreach ([ [$e_cnt, 'error' ], [ $w_cnt, 'warning' ] ] as &$it) {
            $cnt = $it[0];
            if ($cnt > 0) {
                $strs[] = $cnt . ' ' . $it[1] . ($cnt > 1 ? 's' : '');
            }
        }
        unset($it);
        echo implode(' and ', $strs), '!', PHP_EOL;
    }
} catch (SoftException $e) {
    echo 'Fatal error: ', $e->getMessage(), PHP_EOL;
    exit(1);
}

if ($e_cnt > 0) {
    exit(2);
}
exit(0);
