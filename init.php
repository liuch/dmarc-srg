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

spl_autoload_register(function ($class) {
    $prefix     = 'Liuch\\DmarcSrg\\';
    $prefix_len = 15;
    $base_dir   = __DIR__ . '/classes/';

    if (strncmp($prefix, $class, $prefix_len) === 0) {
        $relative_class = substr($class, $prefix_len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
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
    'config'   => [ 'Liuch\DmarcSrg\Config', [ __DIR__ . '/config/conf.php' ] ],
    'status'   => [ 'Liuch\DmarcSrg\Status' ],
    'database' => [ 'Liuch\DmarcSrg\Database\DatabaseController' ],
    'template' => __DIR__ . '/template.html'
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

if (!function_exists('getallheaders')) {
    function getallheaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) === 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))))] = $value;
            }
        }
        return $headers;
    }
}
