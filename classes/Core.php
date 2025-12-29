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
 * getting instances of some classes
 */
class Core
{
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
        foreach ([ 'admin', 'auth', 'config', 'database', 'ehandler', 'session', 'status' ] as $key) {
            if (isset($params[$key])) {
                $this->modules[$key] = $params[$key];
            }
        }
        if (isset($params['template'])) {
            $this->template = $params['template'];
        }
        if (!self::$instance) {
            self::$instance = $this;
        }
    }

    /**
     * Returns the method of the current http request.
     *
     * @return string http method
     */
    public static function requestMethod(): string
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
     * Returns an instance of the current user
     *
     * @return User|null
     */
    public function getCurrentUser()
    {
        $session = $this->session();
        if (!$this->auth()->isEnabled()) {
            return $this->user = new AdminUser($this);
        }

        if (!$this->user) {
            $data = $session->getData();
            if (isset($data['user']) && gettype($data['user']) == 'array') {
                if ($data['user']['name'] === 'admin') {
                    if (($data['user']['id'] ?? -1) !== 0 || ($data['user']['level'] ?? -1) !== User::LEVEL_ADMIN) {
                            throw new ForbiddenException('The user session has been broken!');
                    }
                    $this->user = new AdminUser($this);
                    $session->commit();
                } elseif ($this->config('users/user_management', false)) {
                    try {
                        $this->user = new DbUser($data['user'], $this->database());
                        $cts = (new DateTime())->getTimestamp();
                        if (($data['s_time'] ?? 0) + 5 <= $cts) {
                            if (isset($data['s_id']) &&
                                $this->user->session() === $data['s_id'] &&
                                $this->user->isEnabled()
                            ) {
                                $data['s_time'] = $cts;
                                $data['user']['level'] = $this->user->level();
                                $session->setData($data);
                                $session->commit();
                            } else {
                                $this->user = null;
                                $session->destroy();
                            }
                        } else {
                            $session->commit();
                        }
                    } catch (SoftException $e) {
                        if (!$this->user->exists()) {
                            $this->user = null;
                            $session->destroy();
                        }
                        throw $e;
                    }
                } else {
                    $session->destroy();
                }
            }
        }
        return $this->user;
    }

    /**
     * Sets the passed user as the current user
     *
     * @param User|string|null $user User (instance, name or none) to set
     *
     * @return void
     */
    public function setCurrentUser($user): void
    {
        if (gettype($user) == 'string') {
            $user = UserList::getUserByName($user, $this);
        } elseif (!is_null($user) && !($user instanceof User)) {
            throw new LogicException('Wrong user object was passed');
        }
        $this->user = $user;
        if (self::isWEB()) {
            $session = $this->session();
            $session->destroy();
            if ($user) {
                $session->setData([
                    'user' => [
                        'id'    => $user->id(),
                        'name'  => $user->name(),
                        'level' => $user->level()
                    ],
                    's_id'   => $user->session(),
                    's_time' => (new DateTime())->getTimestamp()
                ]);
                $session->commit();
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
        $final_no_list = [];
        $and_item_list = explode(',', $deps);
        foreach ($and_item_list as $and_item) {
            $no_list = [];
            $or_item_list = explode('|', $and_item);
            foreach ($or_item_list as $ext) {
                $no_dep = null;
                switch ($ext) {
                    case 'flyfs':
                        if (!class_exists('League\Flysystem\Filesystem')) {
                            $no_dep = 'Flysystem';
                        }
                        break;
                    case 'imap-engine':
                        if (!class_exists('DirectoryTree\ImapEngine\Mailbox')) {
                            $no_dep = 'ImapEngine';
                        }
                        break;
                    default:
                        if (!extension_loaded($ext)) {
                            $no_dep = 'ext-' . $ext;
                        }
                        break;
                }
                if ($no_dep) {
                    $no_list[] = $no_dep;
                }
            }
            if (count($or_item_list) === count($no_list)) {
                $final_no_list[] = implode(' or ', $no_list);
            }
        }
        if (count($final_no_list)) {
            if (count($final_no_list) === 1) {
                $s1 = 'y';
                $s2 = 'is';
            } else {
                $s1 = 'ies';
                $s2 = 'are';
            }
            $msg = "Required dependenc$s1 $s2 missing";
            $usr = $this->getCurrentUser();
            if ($usr && $usr->level() === User::LEVEL_ADMIN) {
                $msg .= ': ' . implode(', ', $final_no_list) . '.';
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
     * Returns an instance of the Session class
     *
     * @return Session
     */
    public function session()
    {
        return $this->getModule('session', false);
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
