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
 * This file contains the class Core
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\LogicException;

/**
 * It's class for accessing to most methods for working with http, json data,
 * the user session, getting instances of some classes
 */
class Core
{
    public const APP_VERSION = '1.7';
    private const SESSION_NAME = 'session';
    private const HTML_FILE_NAME = 'index.html';

    private $modules = [];
    private static $instance = null;

    /**
     * The constructor
     *
     * @param array $params Array with modules to be bind to
     */
    public function __construct($params)
    {
        foreach ([ 'admin', 'auth', 'config', 'ehandler', 'status' ] as $key) {
            if (isset($params[$key])) {
                $this->modules[$key] = $params[$key];
            }
        }
        self::$instance = $this;
    }

    /**
     * Returns the method of the current http request.
     *
     * @return string http method
     */
    public static function method(): string
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * Returns array of request headers in lowercase mode.
     *
     * @return array
     */
    public static function getHeaders(): array
    {
        return array_change_key_case(getallheaders(), CASE_LOWER);
    }

    /**
     * Sets or gets the current user's id
     *
     * In case $id is null, the method returns the current user's id.
     * In case $id is integer value, the method sets this value as the current user's id.
     * It returns false if there is an error.
     *
     * @param int|void $id User id to set it.
     *
     * @return int|bool User id or false in case of error.
     */
    public function userId($id = null)
    {
        $start_f = false;
        if ((self::cookie(self::SESSION_NAME) !== '' || $id !== null) && session_status() !== PHP_SESSION_ACTIVE) {
            $start_f = true;
            self::sessionStart();
        }
        $res = null;
        if (gettype($id) === 'integer') {
            $_SESSION['user_id'] = $id;
        }
        $res = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : false;
        if ($start_f) {
            session_write_close();
        }
        return $res;
    }

    /**
     * Deletes the session of the current user and the corresponding cookie.
     *
     * @return void
     */
    public function destroySession(): void
    {
        if (self::cookie(self::SESSION_NAME)) {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                self::sessionStart();
            }
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $scp = session_get_cookie_params();
                $cep = [
                    'expires'  => time() - 42000,
                    'path'     => $scp['path'],
                    'domain'   => $scp['domain'],
                    'secure'   => $scp['secure'],
                    'httponly' => $scp['httponly'],
                    'samesite' => $scp['samesite']
                ];
                setcookie(self::SESSION_NAME, '', $cep);
                session_write_close();
            }
        }
    }

    /**
     * Returns true if the http request asks for json data.
     *
     * @return bool
     */
    public static function isJson(): bool
    {
        $headers = self::getHeaders();
        return (isset($headers['accept']) && $headers['accept'] === 'application/json');
    }

    /**
     * Sends the html file to the client.
     *
     * @return void
     */
    public static function sendHtml(): void
    {
        if (file_exists(Core::HTML_FILE_NAME)) {
            readfile(Core::HTML_FILE_NAME);
        }
    }

    /**
     * Sends data from an array as json string to the client.
     *
     * @param array $data - Data to send.
     *
     * @return void
     */
    public static function sendJson(array $data): void
    {
        $res_str = json_encode($data);
        if ($res_str === false) {
            $res_str = '[]';
        }
        header('content-type: application/json; charset=UTF-8');
        echo $res_str;
    }

    /**
     * Sends a Bad Request response to the client.
     *
     * @return void
     */
    public static function sendBad(): void
    {
        http_response_code(400);
        echo 'Bad request';
    }

    /**
     * Retrieves json data from the request and return it as an array.
     *
     * Returns an array with data or null if there is an error.
     *
     * @return array|null Data from the request
     */
    public static function getJsonData()
    {
        $res = null;
        $headers = self::getHeaders();
        if (isset($headers['content-type']) && $headers['content-type'] === 'application/json') {
            $str = file_get_contents('php://input');
            if ($str) {
                $res = json_decode($str, true);
            }
        }
        return $res;
    }

    /**
     * Returns an instance of the class Auth.
     *
     * @return Auth
     */
    public function auth()
    {
        return $this->getModule('auth', true);
    }

    /**
     * Returns an instance of the class Status.
     *
     * @return Status instance of Status
     */
    public function status()
    {
        return $this->getModule('status', true);
    }

    /**
     * Returns an instance of the class Admin.
     *
     * @return Admin instance of Admin
     */
    public function admin()
    {
        return $this->getModule('admin', false);
    }

    /**
     * Returns an instance of the class ErrorHandler
     *
     * @return ErrorHandler
     */
    public function errorHandler()
    {
        return $this->getModule('ehandler', true);
    }

    /**
     * Returns the current logger.
     * Just a proxy method to return the logger from ErrorHandler
     *
     * @return LoggerInterface
     */
    public function logger()
    {
        return $this->errorHandler()->logger();
    }

    /**
     * Returns instance of the object
     *
     * @return self
     */
    public static function instance()
    {
        return self::$instance;
    }

    /**
     * Returns the config value by its name
     *
     * @param string $name    Config item name. Hierarchy supported via '/'
     * @param mixed  $default Value to be returned if the required config item is missing or null
     *
     * @return mixed
     */
    public function config(string $name, $default = null)
    {
        return $this->getModule('config', false)->get($name, $default);
    }

    /**
     * Gets or sets a cookie with the specified name.
     *
     * @param string      $name   the cookie name to get or to set
     * @param string|null $value
     * @param array|null  $params
     *
     * @return string|boolean The cookie value or false if there is an error
     */
    private static function cookie($name, $value = null, $params = null)
    {
        if (!$value) {
            return isset($_COOKIE[$name]) ? $_COOKIE[$name] : '';
        }
        if (setcookie($name, $value, $params)) {
            return $value;
        }
        return false;
    }

    /**
     * Starts the user session
     *
     * @return void
     */
    private static function sessionStart(): void
    {
        if (!session_start(
            [
                'name'            => self::SESSION_NAME,
                'cookie_path'     => dirname($_SERVER['REQUEST_URI']),
                'cookie_httponly' => true,
                'cookie_samesite' => 'Strict'
            ]
        )
        ) {
            throw new SoftException('Failed to start a user session');
        }
    }

    /**
     * Returns a module instance by its name. Lazy initialization is used.
     *
     * @param string $name Module name
     * @param bool   $core Whether to pass $this to the constructor
     *
     * @return object
     */
    private function getModule(string $name, bool $core)
    {
        $module = $this->modules[$name] ?? null;
        switch (gettype($module)) {
            case 'array':
                if ($core) {
                    $module = new $module[0]($this, ...($module[1] ?? []));
                } else {
                    $module = new $module[0](...($module[1] ?? []));
                }
                $this->modules[$name] = $module;
                break;
            case 'NULL':
                throw new LogicException('Attempt to initiate an unloaded module ' . $name);
        }
        return $module;
    }
}
