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
 * This file contains the class DbUser
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Users;

use Liuch\DmarcSrg\Core;
use Liuch\DmarcSrg\Common;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\LogicException;
use Liuch\DmarcSrg\Exception\DatabaseNotFoundException;

/**
 * It's a class for accessing to stored user data
 *
 * This class is designed for storing and manipulating user data.
 * All queries to the database are made in lazy mode.
 */
class DbUser extends User
{
    private $db = null;
    private $ex_f = null;
    private $data = [
            'id'           => null,
            'name'         => null,
            'enabled'      => null,
            'password'     => null, // If the password exists (boolean)
            'level'        => null,
            'email'        => null,
            'key'          => null,
            'session'      => null,
            'domains'      => null,
            'created_time' => null,
            'updated_time' => null
    ];

    /**
     * Constructor
     *
     * Some examples of use:
     * (new DbUser('user1'))->verifyPassword($passw) - will verify password $passw for user with name 'user1'
     * (new DbUser([ 'name' => 'user2', 'level' => 10, 'enabled' => true ]))->save() - will add this user to
     * the database if it doesn't exist or update it otherwise.
     *
     * @param int|string|array                            $data Some user data to identify it
     *                                                          string value is treated as a name
     *                                                          array value is threated as user data with fields
     * @param \Liuch\DmarcSrg\Database\DatabaseController $db   The database controller
     *
     * @return void
     */
    public function __construct($data, $db = null)
    {
        $this->db = $db ?? Core::instance()->database();
        switch (gettype($data)) {
            case 'string':
                $this->data['name'] = strtolower(trim($data));
                $this->checkName();
                return;
            case 'integer':
                if ($data <= 0) {
                    break;
                }
                $this->data['id'] = $data;
                return;
            case 'array':
                if (isset($data['id'])) {
                    if (gettype($data['id']) !== 'integer' || $data['id'] <= 0) {
                        break;
                    }
                    $this->data['id'] = $data['id'];
                }
                if (isset($data['name'])) {
                    if (gettype($data['name']) !== 'string') {
                        break;
                    }
                    $this->data['name'] = strtolower(trim($data['name']));
                    $this->checkName();
                }
                if (isset($data['enabled'])) {
                    if (gettype($data['enabled']) !== 'boolean') {
                        break;
                    }
                    $this->data['enabled'] = $data['enabled'];
                }
                if (isset($data['level'])) {
                    if (gettype($data['level']) !== 'integer') {
                        break;
                    }
                    $this->data['level'] = $data['level'];
                }
                if (isset($data['email'])) {
                    if (gettype($data['email']) !== 'string') {
                        break;
                    }
                    $this->data['email'] = $data['email'];
                }
                if (isset($data['key'])) {
                    if (gettype($data['key']) !== 'string') {
                        break;
                    }
                    $this->data['key'] = $data['key'];
                }
                if (isset($data['created_time'])) {
                    if (gettype($data['created_time']) !== 'object') {
                        break;
                    }
                    $this->data['created_time'] = $data['created_time'];
                }
                if (isset($data['updated_time'])) {
                    if (gettype($data['updated_time']) !== 'object') {
                        break;
                    }
                    $this->data['updated_time'] = $data['updated_time'];
                }
                if (isset($data['domains'])) {
                    if (!is_int($data['domains'])) {
                        break;
                    }
                    $this->data['domains'] = $data['domains'];
                }
                if (!is_null($this->data['id']) || !is_null($this->data['name'])) {
                    return;
                }
        }
        throw new LogicException('Wrong user data');
    }

    /**
     * Returns true if the user exists in the database or false otherwise
     *
     * @return bool Whether the user exists
     */
    public function exists(): bool
    {
        if (is_null($this->ex_f)) {
            $this->ex_f = $this->db->getMapper('user')->exists($this->data);
        }
        return $this->ex_f;
    }

    /**
     * Ensures the user is in the specified state and throws an exception if it is not.
     *
     * @param string $state Can be one of these values: 'exist', 'nonexist'
     *
     * @throws SoftException
     *
     * @return void
     */
    public function ensure(string $state): void
    {
        switch ($state) {
            case 'exist':
                if (!$this->exists()) {
                    throw new SoftException('The user does not exist');
                }
                break;
            case 'nonexist':
                if ($this->exists()) {
                    throw new SoftException('The user already exists');
                }
                break;
            default:
                throw new LogicException('Unknown user state');
        }
    }

    /**
     * Returns the user id
     *
     * @return int
     */
    public function id(): int
    {
        if (is_null($this->data['id'])) {
            $this->fetchData();
        }
        return $this->data['id'];
    }

    /**
     * Returns the user name
     *
     * @return string
     */
    public function name(): string
    {
        if (is_null($this->data['name'])) {
            $this->fetchData();
        }
        return $this->data['name'];
    }

    /**
     * Returns the user access level
     *
     * @return int
     */
    public function level(): int
    {
        if (is_null($this->data['level'])) {
            $this->fetchData();
        }
        return $this->data['level'];
    }

    /**
     * Checks if the user is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        if (is_null($this->data['enabled'])) {
            $this->fetchData();
        }
        return $this->data['enabled'];
    }

    /**
     * Sets the list of domains available to the user
     *
     * @param array  $domains Array of domain names
     *
     * @return void
     */
    public function assignDomains(array &$domains): void
    {
        $this->db->getMapper('domain')->updateUserDomains($domains, $this->id());
    }

    /**
     * Returns the sequence number of the session
     *
     * It changes when the user credentials or state are changed.
     *
     * @return int
     */
    public function session(): int
    {
        if (is_null($this->data['session'])) {
            $this->fetchData();
        }
        return $this->data['session'];
    }

    /**
     * Returns the user's data as an array
     *
     * @return array
     */
    public function toArray(): array
    {
        foreach ([ 'id', 'name', 'level', 'enabled' ] as $it) {
            if (is_null($this->data[$it])) {
                $this->fetchData();
                break;
            }
        }
        $res = $this->data;
        unset($res['id']);
        unset($res['session']);
        return $res;
    }

    /**
     * Saves the user's data to the database
     *
     * Updates the user's data in the database if the user exists or insert a new record otherwise.
     * The user id is ignored in the insert mode.
     *
     * @return void
     */
    public function save(): void
    {
        $this->db->getMapper('user')->save($this->data);
        $this->ex_f = true;
    }

    /**
     * Deletes the user from the database
     *
     * @return void
     */
    public function delete(): void
    {
        if (is_null($this->data['id'])) {
            $this->fetchData();
        }
        $this->db->getMapper('user')->delete($this->data);
        $this->ex_f = false;
    }

    /**
     * Verifies the passed password with the hash stored in the database
     *
     * @param string $password Password to validate
     *
     * @return bool
     */
    public function verifyPassword(string $password): bool
    {
        if (empty($password)) {
            return false;
        }
        $hash = null;
        try {
            $hash = $this->db->getMapper('user')->getPasswordHash($this->data);
        } catch (DatabaseNotFoundException $e) {
            $this->ex_f = false;
            return false;
        }
        return password_verify($password, $hash);
    }

    /**
     * Store the passed password to the database as a hash with salt
     *
     * @param string $password Password to store
     *
     * @return void
     */
    public function setPassword(string $password): void
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->db->getMapper('user')->savePasswordHash($this->data, $hash);
    }

    /**
     * Returns a random string bound to the user
     *
     * @return string
     */
    public function verificationString(): string
    {
        if (is_null($this->data['key'])) {
            $this->fetchData();
        }
        if (empty($this->data['key'])) {
            $this->db->getMapper('user')->setUserKey($this->data, Common::randomString(32));
        }
        return $this->data['key'];
    }

    /**
     * Checks if the username value is correct
     *
     * @return void
     */
    private function checkName(): void
    {
        if (empty($this->data['name'])) {
            throw new SoftException('The user name must not be an empty string');
        }
        if ($this->data['name'] === 'admin') {
            throw new SoftException('Incorrect user name');
        }
    }

    /**
     * Fetches the user data from the database by its name
     *
     * @return void
     */
    private function fetchData(): void
    {
        if ($this->ex_f === false) {
            return;
        }

        try {
            $this->db->getMapper('user')->fetch($this->data);
            $this->ex_f = true;
        } catch (DatabaseNotFoundException $e) {
            $this->ex_f = false;
            throw new SoftException('User not found');
        }
    }
}
