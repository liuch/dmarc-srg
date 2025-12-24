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

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__ . (__DIR__ === DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR));
}

$vc = require_once(ROOT_PATH . 'config' . DIRECTORY_SEPARATOR . 'vendor_config.php');
if (!is_array($vc) || !isset($vc['autoload_file'], $vc['config_file'], $vc['version_suffix'])) {
    echo 'Error: Incorrect vendor config file';
    exit;
}

if (defined('PHP_UNIT_TEST')) { /* stop warnings about headers having already been sent */
    ob_start();
}

define('APP_VERSION', '3.0-pre' . strval($vc['version_suffix']));
define('CONFIG_FILE', strval($vc['config_file']));

$va = strval($vc['autoload_file']);
if (is_readable($va)) {
    require_once($va);
}

spl_autoload_register(function ($class) {
    $prefix     = 'Liuch\\DmarcSrg\\';
    $prefix_len = 15;
    $base_dir   = ROOT_PATH . 'classes' . DIRECTORY_SEPARATOR;

    if (strncmp($prefix, $class, $prefix_len) === 0) {
        $relative_class = substr($class, $prefix_len);
        $file = $base_dir . str_replace('\\', DIRECTORY_SEPARATOR, $relative_class) . '.php';
        if (file_exists($file)) {
            require_once($file);
        }
    }
});

date_default_timezone_set('GMT');

$core = new Liuch\DmarcSrg\Core([
    'auth'     => [ 'Liuch\DmarcSrg\Auth' ],
    'admin'    => [ 'Liuch\DmarcSrg\Admin' ],
    'ehandler' => [ 'Liuch\DmarcSrg\ErrorHandler' ],
    'config'   => [ 'Liuch\DmarcSrg\Config', [ CONFIG_FILE ] ],
    'status'   => [ 'Liuch\DmarcSrg\Status' ],
    'session'  => [ 'Liuch\DmarcSrg\Session' ],
    'database' => [ 'Liuch\DmarcSrg\Database\DatabaseController' ],
    'template' => ROOT_PATH . 'template.html'
]);
$core->errorHandler()->setLogger(new Liuch\DmarcSrg\Log\PhpSystemLogger());

set_exception_handler(function ($e) {
    Liuch\DmarcSrg\Core::instance()->errorHandler()->handleException($e);
});

set_error_handler(function (int $severity, string $message, string $file, int $line) {
    if (error_reporting() === 0) {
        return false;
    }
    throw new \ErrorException($message, -1, $severity, $file, $line);
});

unset($vc, $va);
