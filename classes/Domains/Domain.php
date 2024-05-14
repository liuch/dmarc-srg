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
 * This file contains the class Domain
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Domains;

use Liuch\DmarcSrg\Core;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\LogicException;
use Liuch\DmarcSrg\Exception\DatabaseNotFoundException;

/**
 * It's a class for accessing to stored domains data
 *
 * This class is designed for storing and manipulating domain data.
 * All queries to the database are made in lazy mode.
 */
class Domain
{
    private $db   = null;
    private $ex_f = null;
    private $data = [
            'id'           => null,
            'fqdn'         => null,
            'active'       => null,
            'description'  => null,
            'created_time' => null,
            'updated_time' => null
    ];

    /**
     * It's a constructor of the class
     *
     * Some examples of using:
     * (new Domain(1))->fqdn(); - will return the fully qualified domain name for the domain with id = 1
     * (new Domain('example.com'))->description(); - will return the description for the domain example.com
     * (new Domain([ 'fqdn' => 'example.com', 'description' => 'an expample domain' ])->save(); - will add
     * this domain to the database if it does not exist in it.
     *
     * @param int|string|array                            $data Some domain data to identify it
     *                                                          int value is treated as domain id
     *                                                          string value is treated as a FQDN
     *                                                          array has these fields: `id`, `fqdn`, `active`, `description`
     *                                                          and usually uses for creating a new domain item.
     *                                                          Note: The values of the fields `created_time` and `updated_time`
     *                                                          will be ignored while saving to the database.
     * @param \Liuch\DmarcSrg\Database\DatabaseController $db   The database controller
     *
     * @return void
     */
    public function __construct($data, $db = null)
    {
        $this->db = $db ?? Core::instance()->database();
        switch (gettype($data)) {
            case 'integer':
                $this->data['id'] = $data;
                return;
            case 'string':
                $this->data['fqdn'] = strtolower($data);
                $this->checkFqdn();
                return;
            case 'array':
                if (isset($data['id'])) {
                    if (gettype($data['id']) !== 'integer') {
                        break;
                    }
                    $this->data['id'] = $data['id'];
                }
                if (isset($data['fqdn'])) {
                    if (gettype($data['fqdn']) !== 'string') {
                        break;
                    }
                    $this->data['fqdn'] = strtolower($data['fqdn']);
                    $this->checkFqdn();
                }
                if (isset($data['active'])) {
                    if (gettype($data['active']) !== 'boolean') {
                        break;
                    }
                    $this->data['active'] = $data['active'];
                } else {
                    $this->data['active'] = false;
                }
                if (isset($data['description'])) {
                    if (gettype($data['description']) !== 'string') {
                        break;
                    }
                    $this->data['description'] = $data['description'];
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
                if (!is_null($this->data['id']) || !is_null($this->data['fqdn'])) {
                    return;
                }
        }
        throw new LogicException('Wrong domain data');
    }

    /**
     * Returns true if the domain exists in the database or false otherwise
     *
     * @return bool Whether the domain exists
     */
    public function exists(): bool
    {
        if (is_null($this->ex_f)) {
            $this->ex_f = $this->db->getMapper('domain')->exists($this->data);
        }
        return $this->ex_f;
    }

    /**
     * Ensures the domain is in the specified state and throws an exception if it is not.
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
                    $this->unknownDomain();
                }
                break;
            case 'nonexist':
                if ($this->exists()) {
                    throw new SoftException('The domain already exists');
                }
                break;
            default:
                throw new LogicException('Unknown domain state');
        }
    }

    /**
     * Returns the domain id
     *
     * @return int The domain id
     */
    public function id(): int
    {
        if (is_null($this->data['id'])) {
            $this->fetchData();
        }
        return $this->data['id'];
    }

    /**
     * Returns the domain's FQDN
     *
     * @return string FQDN for the domain
     */
    public function fqdn(): string
    {
        if (is_null($this->data['fqdn'])) {
            $this->fetchData();
        }
        return $this->data['fqdn'];
    }

    /**
     * Whether the domain is active or not
     *
     * When the domain is inactive, all incoming reports for it are ignored
     * but the domain will still be included in summary reports.
     *
     * @return bool
     */
    public function active(): bool
    {
        if (is_null($this->data['active'])) {
            $this->fetchData();
        }
        return $this->data['active'];
    }

    /**
     * Returns the domain's description
     *
     * @return string|null The description of the domain if it exists or null otherwise
     */
    public function description()
    {
        if (is_null($this->data['id']) || is_null($this->data['fqdn'])) {
            $this->fetchData();
        }
        return $this->data['description'];
    }

    /**
     * Returns an array with domain data
     *
     * @return array Domain data
     */
    public function toArray(): array
    {
        foreach ([ 'id', 'fqdn', 'active' ] as $it) {
            if (is_null($this->data[$it])) {
                $this->fetchData();
                break;
            }
        }
        $res = $this->data;
        unset($res['id']);
        return $res;
    }

    /**
     * Returns true if the domain exists in the database and is assigned to the user
     *
     * @param \Liuch\DmarcSrg\Users\User $user      User to check
     * @param bool                       $exception Throw an exception instead of returning false
     *
     * @return bool
     */
    public function isAssigned($user, bool $exception = false): bool
    {
        if ($this->ex_f !== false) {
            $user_id = $user->id();
            if ($user_id === 0) {
                // It is the admin
                if ($this->exists()) {
                    return true;
                }
            } elseif ($this->db->getMapper('domain')->isAssigned($this->data, $user_id)) {
                $this->ex_f = true;
                return true;
            }
        }
        if ($exception) {
            $this->unknownDomain();
        }
        return false;
    }

    /**
     * Assigns the domain to a user
     *
     * @param \Liuch\DmarcSrg\Users\User $user The user to whom the domain should be assigned
     *
     * @return void
     */
    public function assignUser($user): void
    {
        $user_id = $user->id();
        if ($user_id !== 0) {
            $this->db->getMapper('domain')->assignUser($this->data, $user_id);
        }
    }

    /**
     * Unassigns the domain from a user
     *
     * @param \Liuch\DmarcSrg\Users\User $user The user from whom the domain should be unassigned
     *
     * @return void
     */
    public function unassignUser($user): void
    {
        $user_id = $user->id();
        if ($user_id !== 0) {
            $this->db->getMapper('domain')->unassignUser($this->data, $user_id);
        }
    }

    /**
     * Saves the domain to the database
     *
     * Updates the domain's description in the database if the domain exists or insert a new record otherwise.
     * The domain id is ignored in the insert mode.
     *
     * @return void
     */
    public function save(): void
    {
        $this->db->getMapper('domain')->save($this->data);
        $this->ex_f = true;
    }

    /**
     * Deletes the domain from the database
     *
     * Deletes the domain if there are no reports for this domain in the database.
     * If you want to stop handling reports for this domain, just make it inactive.
     *
     * @return void
     */
    public function delete(): void
    {
        if (is_null($this->data['id'])) {
            $this->fetchData();
        }
        $this->db->getMapper('domain')->delete($this->data['id']);
        $this->ex_f = false;
    }

    /**
     * Verifies domain ownership
     *
     * @throws SoftException
     *
     * @param string $key    Verification key
     * @param string $method Verification method
     *
     * return void
     */
    public function verifyOwnership(string $key, string $method): void
    {
        $passed = false;
        switch ($method) {
            case 'dns':
                $dr = dns_get_record($this->data['fqdn'], DNS_TXT);
                if ($dr) {
                    $str = 'dmarcsrg-verification=' . $key;
                    foreach ($dr as &$rec) {
                        if (trim($rec['txt']) === $str) {
                            $passed = true;
                            break;
                        }
                    }
                    unset($rec);
                }
                break;
        }
        if (!$passed) {
            throw new SoftException('Domain verification failed');
        }
    }

    /**
     * Removes the trailing dot from the domain name and checks it for an empty value.
     *
     * @return void
     */
    private function checkFqdn(): void
    {
        $fqdn = trim($this->data['fqdn']);
        if (substr($fqdn, -1) === '.') {
            $fqdn = trim(substr($fqdn, 0, -1));
        }
        if ($fqdn === '') {
            throw new SoftException('The domain name must not be an empty string');
        }
        $this->data['fqdn'] = $fqdn;
    }

    /**
     * Fetches the domain data from the database by its id or name
     *
     * @return void
     */
    private function fetchData(): void
    {
        if ($this->ex_f === false) {
            return;
        }

        try {
            $this->db->getMapper('domain')->fetch($this->data);
            $this->ex_f = true;
        } catch (DatabaseNotFoundException $e) {
            $this->ex_f = false;
            $this->unknownDomain();
        }
    }

    /**
     * Throws an exception that the domain doesn't exist
     *
     * @throws SoftException
     *
     * @return void
     */
    private function unknownDomain(): void
    {
        $name = '';
        if (!empty($this->data['fqdn'])) {
            $name = ': ' . $this->data['fqdn'];
        }
        throw new SoftException('Unknown domain' . $name);
    }
}
