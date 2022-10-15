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

use Liuch\DmarcSrg\DateTime;
use Liuch\DmarcSrg\Database\Database;
use Liuch\DmarcSrg\Report\ReportList;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\LogicException;
use Liuch\DmarcSrg\Exception\DatabaseFatalException;

/**
 * It's a class for accessing to stored domains data
 *
 * This class is designed for storing and manipulating domain data.
 * All queries to the datatabase are made in lazy mode.
 */
class Domain
{
    private $id   = null;
    private $fqdn = null;
    private $actv = null;
    private $desc = null;
    private $c_tm = null;
    private $u_tm = null;
    private $ex_f = null;

    /**
     * It's a constructor of the class
     *
     * Some examples of using:
     * (new Domain(1))->fqdn(); - will return the fully qualified domain name for the domain with id = 1
     * (new Domain('example.com'))->description(); - will return the description for the domain example.com
     * (new Domain([ 'fqdn' => 'example.com', 'description' => 'an expample domain' ])->save(); - will add
     * this domain to the database if it does not exist in it.
     *
     * @param int|string|array $data Some domain data to identify it
     *                               int value is treated as domain id
     *                               string value is treated as a FQDN
     *                               array has these fields: `id`, `fqdn`, `active`, `description`
     *                               and usually uses for creating a new domain item.
     *                               Note: The values of the fields `created_time` and `updated_time`
     *                               will be ignored while saving to the database.
     *
     * @return void
     */
    public function __construct($data)
    {
        switch (gettype($data)) {
            case 'integer':
                $this->id = $data;
                return;
            case 'string':
                $this->fqdn = $data;
                $this->checkFqdn();
                return;
            case 'array':
                if (isset($data['id'])) {
                    if (gettype($data['id']) !== 'integer') {
                        break;
                    }
                    $this->id = $data['id'];
                }
                if (isset($data['fqdn'])) {
                    if (gettype($data['fqdn']) !== 'string') {
                        break;
                    }
                    $this->fqdn = $data['fqdn'];
                    $this->checkFqdn();
                }
                if (isset($data['active'])) {
                    if (gettype($data['active']) !== 'boolean') {
                        break;
                    }
                    $this->actv = $data['active'];
                } else {
                    $this->actv = false;
                }
                if (isset($data['description'])) {
                    if (gettype($data['description']) !== 'string') {
                        break;
                    }
                    $this->desc = $data['description'];
                }
                if (isset($data['created_time'])) {
                    if (gettype($data['created_time']) !== 'object') {
                        break;
                    }
                    $this->c_tm = $data['created_time'];
                }
                if (isset($data['updated_time'])) {
                    if (gettype($data['updated_time']) !== 'object') {
                        break;
                    }
                    $this->u_tm = $data['updated_time'];
                }
                if (!is_null($this->id) || !is_null($this->fqdn)) {
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
            $st = Database::connection()->prepare(
                'SELECT `id` FROM `' . Database::tablePrefix('domains') . '` WHERE ' . $this->sqlCondition()
            );
            $this->sqlBindValue($st, 1);
            $st->execute();
            $res = $st->fetch(\PDO::FETCH_NUM);
            $st->closeCursor();
            if ($res) {
                $this->id = intval($res[0]);
                $this->ex_f = true;
            } else {
                $this->ex_f = false;
            }
        }
        return $this->ex_f;
    }

    /**
     * Returns the domain id
     *
     * @return int The domain id
     */
    public function id(): int
    {
        if (is_null($this->id)) {
            $this->fetchData();
        }
        return $this->id;
    }

    /**
     * Returns the domain's FQDN
     *
     * @return string FQDN for the domain
     */
    public function fqdn(): string
    {
        if (is_null($this->fqdn)) {
            $this->fetchData();
        }
        return $this->fqdn;
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
        if (is_null($this->actv)) {
            $this->fetchData();
        }
        return $this->actv;
    }

    /**
     * Returns the domain's description
     *
     * @return string|null The description of the domain if it exists or null otherwise
     */
    public function description()
    {
        if (is_null($this->id) || is_null($this->fqdn)) {
            $this->fetchData();
        }
        return $this->desc;
    }

    /**
     * Returns an array with domain data
     *
     * @return array Domain data
     */
    public function toArray(): array
    {
        if (is_null($this->id) || is_null($this->fqdn)) {
            $this->fetchData();
        }
        return [
            'fqdn'         => $this->fqdn,
            'active'       => $this->actv,
            'description'  => $this->desc,
            'created_time' => $this->c_tm,
            'updated_time' => $this->u_tm
        ];
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
        $db = Database::connection();
        $this->u_tm = new DateTime();
        if ($this->exists()) {
            try {
                $st = $db->prepare(
                    'UPDATE `' . Database::tablePrefix('domains')
                    . '` SET `active` = ?, `description` = ?, `updated_time` = ? WHERE `id` = ?'
                );
                $st->bindValue(1, $this->actv, \PDO::PARAM_BOOL);
                $st->bindValue(2, $this->desc, \PDO::PARAM_STR);
                $st->bindValue(3, $this->u_tm->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
                $st->bindValue(4, $this->id, \PDO::PARAM_INT);
                $st->execute();
                $st->closeCursor();
            } catch (\PDOException $e) {
                throw new DababaseException('Failed to update the domain data', -1, $e);
            }
        } else {
            try {
                $actv = $this->actv ?? false;
                $this->c_tm = $this->u_tm;
                if (is_null($this->desc)) {
                    $sql1 = '';
                    $sql2 = '';
                } else {
                    $sql1 = ', `description`';
                    $sql2 = ', ?';
                }
                $st = $db->prepare(
                    'INSERT INTO `' . Database::tablePrefix('domains')
                    . '` (`fqdn`, `active`' . $sql1 . ', `created_time`, `updated_time`)'
                    . ' VALUES (?, ?' . $sql2 . ', ?, ?)'
                );
                $idx = 0;
                $st->bindValue(++$idx, $this->fqdn, \PDO::PARAM_STR);
                $st->bindValue(++$idx, $actv, \PDO::PARAM_BOOL);
                if (!is_null($this->desc)) {
                    $st->bindValue(++$idx, $this->desc, \PDO::PARAM_STR);
                }
                $st->bindValue(++$idx, $this->c_tm->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
                $st->bindValue(++$idx, $this->u_tm->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
                $st->execute();
                $st->closeCursor();
                $this->id = intval($db->lastInsertId());
                $this->ex_f = true;
                $this->actv = $actv;
            } catch (\PDOException $e) {
                throw new DatabaseFatalException('Failed to insert the domain data', -1, $e);
            }
        }
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
        if (is_null($this->id)) {
            $this->fetchData();
        }

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $rlist = new ReportList(1);
            $rlist->setFilter([ 'domain' => $this ]);
            $rcnt = $rlist->count();
            if ($rcnt > 0) {
                switch ($rcnt) {
                    case 1:
                        $s1 = 'is';
                        $s2 = '';
                        break;
                    default:
                        $s1 = 'are';
                        $s2 = 's';
                        break;
                }
                throw new SoftException(
                    "Failed to delete: there {$s1} {$rcnt} incoming report{$s2} for this domain"
                );
            }

            $st = $db->prepare('DELETE FROM `' . Database::tablePrefix('domains') . '` WHERE `id` = ?');
            $st->bindValue(1, $this->id, \PDO::PARAM_INT);
            $st->execute();
            $st->closeCursor();

            $db->commit();
            $this->ex_f = false;
        } catch (\PDOException $e) {
            $db->rollBack();
            throw new DatabaseFatalException('Failed to delete the domain', -1, $e);
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Removes the trailing dot from the domain name and checks it for an empty value.
     *
     * @return void
     */
    private function checkFqdn(): void
    {
        $this->fqdn = trim($this->fqdn);
        if (substr($this->fqdn, -1) === '.') {
            $this->fqdn = trim(substr($this->fqdn, 0, -1));
        }
        if ($this->fqdn === '') {
            throw new SoftException('The domain name must not be an empty string');
        }
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
            $st = Database::connection()->prepare(
                'SELECT `id`, `fqdn`, `active`, `description`, `created_time`, `updated_time` FROM `'
                . Database::tablePrefix('domains') . '` WHERE ' . $this->sqlCondition()
            );
            $this->sqlBindValue($st, 1);
            $st->execute();
            $res = $st->fetch(\PDO::FETCH_NUM);
            if (!$res) {
                $this->ex_f = false;
                throw new SoftException('There is no such domain');
            }
            $this->id   = $res[0];
            $this->fqdn = $res[1];
            $this->actv = boolval($res[2]);
            $this->desc = $res[3];
            $this->c_tm = new DateTime($res[4]);
            $this->u_tm = new DateTime($res[5]);
            $st->closeCursor();
            $this->ex_f = true;
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to fetch the domain data', -1, $e);
        }
    }

    /**
     * Returns a condition string for a WHERE statement based on existing domain data
     *
     * @return string Condition string
     */
    private function sqlCondition(): string
    {
        if (!is_null($this->id)) {
            return '`id` = ?';
        }
        return '`fqdn` = ?';
    }

    /**
     * Binds values for SQL queries based on existing domain data
     *
     * @param PDOStatement $st  PDO Statement to bind to
     * @param ind          $pos The start position for binding
     *
     * @return void
     */
    private function sqlBindValue($st, int $pos): void
    {
        if (!is_null($this->id)) {
            $st->bindValue($pos, $this->id, \PDO::PARAM_INT);
        } else {
            $st->bindValue($pos, $this->fqdn, \PDO::PARAM_STR);
        }
    }
}
