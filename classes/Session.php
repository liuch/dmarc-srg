<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2025 Aleksey Andreev (liuch)
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
 * This file contains the class Session
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

declare(strict_types=1);

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Exception\RuntimeException;

/**
 * The class is for managing the state of the user session
 */
class Session
{
    private const COOKIE_NAME       = 'session';
    private const LIFETIME_DURATION = 900;
    private const MIGRATE_DURATION  = 120;
    private const DATA_KEYS         = [ 'user', 's_id', 's_time' ];

    /**
     * Returns data of the session
     *
     * @return array
     */
    public function getData(): array
    {
        if (!$this->isStarted()) {
            $this->start();
        }
        return $this->userData();
    }

    /**
     * Stores the passed data to the session
     *
     * @param array $data Array of data to store
     *
     * @return void
     */
    public function setData(array $data): void
    {
        if (!$this->isStarted()) {
            $this->start();
        }
        foreach (self::DATA_KEYS as $key) {
            if (isset($data[$key])) {
                $_SESSION[$key] = $data[$key];
            }
        }
    }

    /**
     * Saves and closes the user session
     *
     * @return void
     */
    public function commit(): void
    {
        if ($this->isStarted()) {
            \session_write_close();
        }
    }

    /**
     * Closes the user session
     *
     * @return void
     */
    public function close(): void
    {
        if ($this->isStarted()) {
            \session_abort();
        }
    }

    /**
     * Destroys the user session and its data
     *
     * @return void
     */
    public function destroy(): void
    {
        if (!$this->isStarted()) {
            $this->strictStart();
        }
        if (\intval(\ini_get('session.use_cookies')) == 1) {
            $scp = \session_get_cookie_params();
            $cep = [
                'expires'  => time() - 42000,
                'path'     => $scp['path'],
                'domain'   => $scp['domain'],
                'secure'   => $scp['secure'],
                'httponly' => $scp['httponly'],
                'samesite' => $scp['samesite']
            ];
            \setcookie(self::COOKIE_NAME, '', $cep);
        }
        \session_unset();
        \session_destroy();
        \session_write_close();
    }

    /**
     * Checks if the session is started
     *
     * @return bool
     */
    private function isStarted(): bool
    {
        return \session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * Starts the user session
     *
     * @return void
     */
    private function start(): void
    {
        \ini_set('session.use_strict_mode', '1');
        $this->strictStart();

        // Session control
        if (!isset($_SESSION['_started'])) {
            // Looks like a new session
            $_SESSION['_started'] = \time();
        } elseif (isset($_SESSION['_expired'])) {
            // Session is outdated
            \session_abort();
            if ($_SESSION['_expired'] > \time() - self::MIGRATE_DURATION && isset($_SESSION['_new_session_id'])) {
                $s_id = $_SESSION['_new_session_id'];
            } else {
                $s_id = session_create_id();
                \ini_set('session.use_strict_mode', '0');
            }
            \session_id($s_id);
            $this->strictStart();
        } elseif ($_SESSION['_started'] <= \time() - self::LIFETIME_DURATION) {
            $this->migrate();
        }
    }

    /**
     * Starts a safety-optioned session
     *
     * @throws RuntimeException
     *
     * @return void
     */
    private function strictStart(): void
    {
        if (!\session_start([
                'name'            => self::COOKIE_NAME,
                'cookie_path'     => \dirname($_SERVER['REQUEST_URI']),
                'cookie_httponly' => true,
                'cookie_samesite' => 'Strict'
        ])) {
            throw new RuntimeException('Failed to start a new session');
        }
    }

    /**
     * Creates a new session and moves all data into it
     *
     * @return void
     */
    private function migrate(): void
    {
        $udata = $this->userData();
        $ct = \time();
        if (!isset($_SESSION['_expired'])) {
            $_SESSION['_expired'] = $ct;
        }
        $new_id = \session_create_id();
        $_SESSION['_new_session_id'] = $new_id;
        \session_write_close();

        \ini_set('session.use_strict_mode', '0');
        \session_id($new_id);
        $this->strictStart();

        $_SESSION = $udata;
        $_SESSION['_started'] = $ct;
    }

    /**
     * Returns all session data except session control data
     *
     * @return array
     */
    private function userData(): array
    {
        $res = [];
        foreach (self::DATA_KEYS as $key) {
            if (isset($_SESSION[$key])) {
                $res[$key] = $_SESSION[$key];
            }
        }
        return $res;
    }
}
