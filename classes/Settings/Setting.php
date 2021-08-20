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
 * This file contains implementation of the class Setting
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Settings;

use PDO;
use Exception;
use Liuch\DmarcSrg\Database\Database;

/**
 * It's a class for accessing to settings item data
 *
 * This class is designed for storing and manipulating one item of settings data.
 * All queries to the datatabase are made in lazy mode.
 */
abstract class Setting
{
    public const TYPE_STRING        = 1;
    public const TYPE_INTEGER       = 2;
    public const TYPE_STRING_SELECT = 3;

    protected $name    = null;
    protected $value   = null;
    protected $wignore = false;

    /**
     * Returns the type of the setting
     *
     * @return int Type of the setting
     */
    abstract public function type(): int;

    /**
     * Checks if the value is correct
     *
     * @return bool True if the value is correct or false otherwise
     */
    abstract protected function checkValue(): bool;

    /**
     * Converts a string to the value
     *
     * @param string $s String for conversion
     *
     * @return void
     */
    abstract protected function stringToValue(string $s): void;

    /**
     * Returns a string representation of the value
     *
     * @return string The string value
     */
    abstract protected function valueToString(): string;

    /**
     * It's a constructor of the class
     *
     * Some examples of using:
     * (new Setting('some.setting'))->value(); - will return the value of the setting 'some.setting'.
     * (new Setting([ 'name' => 'some.setting', 'value' => 'some string value' ])->save(); - will add
     * this setting to the database if it does not exist in it or update the value of the setting.
     *
     * @param string|array $data    Some setting data to identify it
     *                              string value is treated as a name
     *                              array has these fields: `name`, `value`
     *                              and usually uses for creating a new setting item.
     * @param boolean      $wignore If true the wrong value is reset to the default
     *                              or it throws an exception otherwise.
     *
     * @return void
     */
    public function __construct($data, bool $wignore = false)
    {
        $this->wignore = $wignore;
        switch (gettype($data)) {
            case 'string':
                $this->name = $data;
                SettingsList::checkName($this->name);
                return;
            case 'array':
                if (!isset($data['name']) || gettype($data['name']) !== 'string') {
                    break;
                }
                $this->name = $data['name'];
                SettingsList::checkName($this->name);
                if (isset($data['value'])) {
                    $this->value = $data['value'];
                    if (!$this->checkValue()) {
                        if (!$wignore) {
                            break;
                        }
                        $this->resetToDefault();
                    }
                }
                return;
        }
        throw new Exception('Wrong setting data', -1);
    }

    /**
     * Returns the name of the setting
     *
     * @return string The name of the setting
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Returns the value of the setting
     *
     * @return mixed The value of the setting
     */
    public function value()
    {
        if (is_null($this->value)) {
            $this->fetchData();
        }
        return $this->value;
    }

    /**
     * Assigns the passed value to the setting
     *
     * @param mixed Value to assign
     *
     * @return void
     */
    public function setValue($value): void
    {
        $this->value = $value;
        if (!$this->checkValue()) {
            if (!$this->wignore) {
                throw new Exception('Wrong setting value', -1);
            }
            $this->resetToDefault();
        }
    }

    /**
     * Returns an array with setting data
     *
     * @return array Setting data
     */
    public function toArray(): array
    {
        if (is_null($this->value)) {
            $this->fetchData();
        }
        switch ($this->type()) {
            case self::TYPE_STRING:
                $type = 'string';
                break;
            case self::TYPE_INTEGER:
                $type = 'integer';
                break;
            case self::TYPE_STRING_SELECT:
                $type = 'select';
                break;
        }
        return [
            'type'  => $type,
            'name'  => $this->name,
            'value' => $this->value
        ];
    }

    /**
     * Saves the setting to the database
     *
     * Updates the value of the setting in the database if the setting exists there or insert a new record otherwise.
     *
     * @return void
     */
    public function save(): void
    {
        $db = Database::connection();
        $st = null;
        $db->beginTransaction();
        try {
            $st = $db->prepare('SELECT COUNT(*) FROM `system` WHERE `key` = ?');
            $st->bindValue(1, $this->name, PDO::PARAM_STR);
            $st->execute();
            $res = $st->fetch(PDO::FETCH_NUM);
            $st->closeCursor();
            $st = null;
            if (intval($res[0]) == 0) {
                $st = $db->prepare('INSERT INTO `system` (`value`, `key`) VALUES (?, ?)');
            } else {
                $st = $db->prepare('UPDATE `system` SET `value` = ? WHERE `key` = ?');
            }
            switch ($this->type()) {
                case self::TYPE_INTEGER:
                    $st->bindValue(1, $this->value, PDO::PARAM_INT);
                    break;
                default:
                    $st->bindValue(1, $this->value, PDO::PARAM_STR);
                    break;
            }
            $st->bindValue(2, $this->name, PDO::PARAM_STR);
            $st->execute();
            $db->commit();
        } catch (Exeption $e) {
            $db->rollBack();
            throw new Exception('Failed to update a setting', -1);
        } finally {
            if ($st) {
                $st->closeCursor();
            }
        }
    }

    /**
     * Fetches the setting data from the database by its name
     *
     * @return void
     */
    private function fetchData(): void
    {
        $st = Database::connection()->prepare('SELECT `value` FROM `system` WHERE `key` = ?');
        $st->bindValue(1, $this->name, PDO::PARAM_STR);
        $st->execute();
        $res = $st->fetch(PDO::FETCH_NUM);
        if ($res) {
            $this->stringToValue($res[0]);
            $st->closeCursor();
            if (!$this->checkValue()) {
                $this->resetToDefault();
            }
        } else {
            $this->resetToDefault();
        }
    }

    /**
     * Resets the setting value to the default value
     *
     * @return void
     */
    private function resetToDefault(): void
    {
        $this->value = SettingsList::$schema[$this->name]['default'];
    }
}

