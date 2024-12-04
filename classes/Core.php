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
 * This file contains the Core class
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Users\User;
use Liuch\DmarcSrg\Users\DbUser;
use Liuch\DmarcSrg\Users\UserList;
use Liuch\DmarcSrg\Users\AdminUser;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\LogicException;
use Liuch\DmarcSrg\Exception\ForbiddenException;

/**
 * It's class for accessing to most methods for working with http, json data,
 * the user session, getting instances of some classes
 */
class Core
{
    public const APP_VERSION = '2.2.1';

    private const SESSION_NAME = 'session';

    private $user     = null;
    private $modules  = [];
    private $template = null;

    /** @var self|null */
    private static $instance = null;

    /**
     * The constructor
     *
     * @param array $params Array with modules to be bind to
     */
    public function __construct($params)
    {
        foreach ([ 'admin', 'auth', 'config', 'database', 'ehandler', 'status' ] as $key) {
            if (isset($params[$key])) {
                $this->modules[$key] = $params[$key];
            }
        }
        if (isset($params['template'])) {
            $this->template = $params['template'];
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
     * Determines whether the current invocation is being run via a web server
     *
     * @return bool
     */
    public static function isWEB(): bool
    {
        return isset($_SERVER['REQUEST_METHOD']);
    }

    /**
     * Sets or gets the current user instance
     *
     * In case $user is null, the method returns the current user instance or null.
     * In case $user is User, the method sets this instance as the current user.
     * In case $user is string, the method treats it as a username to set as the current user.
     *
     * @param User|string|null $user Instance of User to set
     *
     * @return User|null
     */
    public function user($user = null)
    {
        $web = self::isWEB();
        $start_f = false;
        if ($web) {
            if ((self::cookie(self::SESSION_NAME) !== '' || !is_null($user)) &&
                session_status() !== PHP_SESSION_ACTIVE
            ) {
                $start_f = true;
                self::startSession();
            }
        }
        if (is_null($user)) {
            if (!$this->auth()->isEnabled()) {
                return $this->user(new AdminUser($this));
            }
            if (!$this->user && isset($_SESSION['user']) && gettype($_SESSION['user']) === 'array') {
                $nm = $_SESSION['user']['name'] ?? '';
                if ($nm === 'admin') {
                    if (($_SESSION['user']['level'] ?? -1) !== User::LEVEL_ADMIN) {
                        throw new ForbiddenException('The user session has been broken!');
                    }
                    $this->user = new AdminUser($this);
                } elseif ($this->config('users/user_management', false)) {
                    $this->user = new DbUser($_SESSION['user']);
                    try {
                        $cts = (new DateTime())->getTimestamp();
                        if (!isset($_SESSION['s_time']) || $_SESSION['s_time'] + 5 <= $cts) {
                            if (isset($_SESSION['s_id']) &&
                                $this->user->session() === $_SESSION['s_id'] &&
                                $this->user->isEnabled()
                            ) {
                                $_SESSION['s_time'] = $cts;
                                $_SESSION['user']['level'] = $this->user->level();
                            } else {
                                $this->destroySession();
                            }
                        }
                    } catch (SoftException $e) {
                        if (!$this->user->exists()) {
                            $this->user = null;
                            $this->destroySession();
                        }
                        throw $e;
                    }
                } else {
                    $this->destroySession();
                }
            }
        } else {
            if (gettype($user) === 'string') {
                $user = UserList::getUserByName($user, $this);
            }
            $this->user = $user;
            if ($web) {
                $_SESSION['user'] = [
                    'id'    => $user->id(),
                    'name'  => $user->name(),
                    'level' => $user->level()
                ];
                $_SESSION['s_id']   = $user->session();
                $_SESSION['s_time'] = (new DateTime())->getTimestamp();
            }
        }
        if ($start_f) {
            session_write_close();
        }
        return $this->user;
    }

    /**
     * Deletes the session of the current user, corresponding cookie and instance.
     *
     * @return void
     */
    public function destroySession(): void
    {
        if (self::isWEB()) {
            if (self::cookie(self::SESSION_NAME)) {
                if (session_status() !== PHP_SESSION_ACTIVE) {
                    self::startSession();
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
        $this->user = null;
    }

    /**
     * Returns true if the http request asks for json data.
     *
     * @return bool
     */
    public static function isJson(): bool
    {
        return ($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json';
    }

    /**
     * Sends the html file to the client and inserts a link with a custom CSS file if necessary
     *
     * @return void
     */
    public function sendHtml(): void
    {
        if (is_readable($this->template)) {
            $ccf = $this->config('custom_css', '');
            if (substr_compare($ccf, '.css', -4) === 0) { // replacement for str_ends_with
                $ccf = '<link rel="stylesheet" href="' . htmlspecialchars($ccf) . '" type="text/css" />';
            } else {
                $ccf = '';
            }
            $fd = fopen($this->template, 'r');
            if ($fd) {
                while (($buffer = fgets($fd)) !== false) {
                    if (substr_compare($buffer, "<!-- Custom CSS -->\n", -20) === 0) {
                        if (!empty($ccf)) {
                            $buffer = str_replace('<!-- Custom CSS -->', $ccf, $buffer);
                            echo $buffer;
                        }
                    } else {
                        echo $buffer;
                    }
                }
                fclose($fd);
            }
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
        if (($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '') === 'application/json') {
            $str = file_get_contents('php://input');
            if ($str) {
                $res = json_decode($str, true);
            }
        }
        return $res;
    }

    /**
     * Checks if the dependencies passed in the parameter are installed
     *
     * @param string $deps Comma-separated string of dependency names to be checked
     *
     * @return void
     */
    public function checkDependencies(string $deps): void
    {
        $adeps = explode(',', $deps);
        $no_deps = [];
        foreach ($adeps as $ext) {
            $no_f = false;
            switch ($ext) {
                case 'flyfs':
                    if (!class_exists('League\Flysystem\Filesystem')) {
                        $no_f = true;
                    }
                    break;
                default:
                    if (!extension_loaded($ext)) {
                        $no_f = true;
                    }
                    break;
            }
            if ($no_f) {
                $no_deps[] = strtoupper($ext);
            }
        }
        if (count($no_deps)) {
            if (count($no_deps) === 1) {
                $s1 = '';
                $s2 = 'is';
            } else {
                $s1 = 's';
                $s2 = 'are';
            }
            $msg = "Required extension$s1 $s2 missing";
            if ($this->user() && $this->user()->level() === User::LEVEL_ADMIN) {
                $msg .= ': ' . implode(', ', $no_deps) . '.';
            } else {
                $msg .= '. Contact the administrator.';
            }
            throw new SoftException($msg);
        }
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
        return $this->getModule('admin', true);
    }

    /**
     * Returns an instance of the class Database.
     *
     * @return Database\DatabaseController
     */
    public function database()
    {
        return $this->getModule('database', true);
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
     * @return Log\LoggerInterface
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
    private static function startSession(): void
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
