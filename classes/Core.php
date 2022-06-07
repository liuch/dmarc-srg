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

use Exception;

/**
 * It's class for accessing to most methods for working with http, json data,
 * the user session, getting instances of some classes
 */
class Core
{
    public const APP_VERSION = '1.4';
    private const SESSION_NAME = 'session';
    private static $html_file_name = 'index.html';
    private static $v_auth = null;
    private static $v_status = null;
    private static $v_admin = null;

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
     * Sets of gets the current user's id
     *
     * In case $id is null, the method returns the current user's id.
     * In case $id is integer value, the method sets this value as the current user's id.
     * It returns false if there is an error.
     *
     * @param int|void $id User id to set it.
     *
     * @return int|bool User id or false in case of error.
     */
    public static function userId($id = null)
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
    public static function destroySession(): void
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
        if (file_exists(Core::$html_file_name)) {
            if (ob_get_level() != 0) {
                ob_end_clean();
            }
            readfile(Core::$html_file_name);
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
        $res_str = null;
        try {
            $res_str = json_encode($data);
            if ($res_str === false) {
                throw new Exception('Incorrect data format', -1);
            }
        } catch (Exception $e) {
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
     * Returns a singleton of the class Auth.
     *
     * @return Auth instance of Auth
     */
    public static function auth()
    {
        if (!self::$v_auth) {
            self::$v_auth = new Auth();
        }
        return self::$v_auth;
    }

    /**
     * Returns a singleton of the class Status.
     *
     * @return Status instance of Status
     */
    public static function status()
    {
        if (!self::$v_status) {
            self::$v_status = new Status();
        }
        return self::$v_status;
    }

    /**
     * Returns a singleton of the class Admin.
     *
     * @return Admin instance of Admin
     */
    public static function admin()
    {
        if (!self::$v_admin) {
            self::$v_admin = new Admin();
        }
        return self::$v_admin;
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
            throw new Exception('Failed to start a user session', -1);
        }
    }
}
