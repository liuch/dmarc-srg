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

namespace Liuch\DmarcSrg;

use PDO;
use Exception;
use Liuch\DmarcSrg\Database\Database;

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
    private $desc = null;
    private $ex_f = null;

    /**
     * It's a constructor of the class
     *
     * Some examples of using:
     * (new Domain(1))->fqdn(); - will return the fully qualified domain name for the domain with id = 1
     * (new Domain('example.com'))->description(); - will return the description for then domain example.com
     * (new Domain([ 'fqdn' => 'example.com', 'description' => 'an expample domain' ])->save(); - will add
     * this domain to the database if it does not exist in it.
     *
     * @param int|string|array $data Some domain data to identify it
     *                               int value is treated as domain id
     *                               string value is treated as a FQDN
     *                               array has these fields: `id`, `fqdn`, `description`
     *                               and usually uses for creating a new domain item.
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
                    $this->id = $data;
                }
                if (isset($data['fqdn'])) {
                    if (gettype($data['fqdn']) !== 'string') {
                        break;
                    }
                    $this->fqdn = $data['fqdn'];
                    $this->checkFqdn();
                }
                if (isset($data['description'])) {
                    if (gettype($data['description']) !== 'string') {
                        break;
                    }
                    $this->desc = $data['description'];
                }
                if (!is_null($this->id) || !is_null($this->fqdn)) {
                    return;
                }
        }
        throw new Exception('Wrong domain data', -1);
    }

    /**
     * Returns total number of domains in the database
     *
     * @return int Total number of domains
     */
    public static function count(): int
    {
        $st = Database::connection()->query('SELECT COUNT(*) FROM `domains`', PDO::FETCH_NUM);
        $res = intval($st->fetchColumn(0));
        $st->closeCursor();
        return $res;
    }

    /**
     * Returns true if the domain exists in the database or false otherwise
     *
     * @return bool Whether the domain exists
     */
    public function exists(): bool
    {
        if (is_null($this->ex_f)) {
            $st = Database::connection()->prepare('SELECT `id` FROM `domains` WHERE ' . $this->sqlCondition());
            $this->sqlBindValue($st, 1);
            $st->execute();
            $res = $st->fetch(PDO::FETCH_NUM);
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
        if ($this->exists()) {
            $st = $db->prepare('UPDATE `domains` SET `description` = ? WHERE `id` = ?');
            $st->bindValue(1, $this->desc, PDO::PARAM_STR);
            $st->bindValue(2, $this->id, PDO::PARAM_INT);
            $st->execute();
            $st->closeCursor();
        } else {
            if (is_null($this->desc)) {
                $sql1 = '';
                $sql2 = '';
            } else {
                $sql1 = ', `description`';
                $sql2 = ', ?';
            }
            $st = $db->prepare('INSERT INTO `domains` (`fqdn`' . $sql1 . ') VALUES (?' . $sql2 . ')');
            $st->bindValue(1, $this->fqdn, PDO::PARAM_STR);
            if (!is_null($this->desc)) {
                $st->bindValue(2, $this->desc, PDO::PARAM_STR);
            }
            $st->execute();
            $st->closeCursor();
            $this->id = $db->lastInsertId();
            $this->ex_f = true;
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
            throw new Exception('The domain is empty', -1);
        }
    }

    /**
     * Fetches the domain data from the database by its id or name
     *
     * @return void
     */
    private function fetchData(): void
    {
        $st = Database::connection()->prepare('SELECT `id`, `fqdn`, `description` FROM `domains` WHERE ' . $this->sqlCondition());
        $this->sqlBindValue($st, 1);
        $st->execute();
        $res = $st->fetch(PDO::FETCH_NUM);
        if (!$res) {
            throw new Exception('There is no such domain', -1);
        }
        $this->id   = $res[0];
        $this->fqdn = $res[1];
        $this->desc = $res[2];
        $st->closeCursor();
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
            $st->bindValue($pos, $this->id, PDO::PARAM_INT);
        } else {
            $st->bindValue($pos, $this->fqdn, PDO::PARAM_STR);
        }
    }
}

