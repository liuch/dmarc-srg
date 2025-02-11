<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2021 Aleksey Andreev (liuch)
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
 * This file contains implementation of the class SettingInteger
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Settings;

/**
 * It's a class for accessing to settings item data
 *
 * This class contains the implementation of the setting for integer values.
 */
class SettingInteger extends Setting
{
    /**
     * Returns the type of the setting
     *
     * @return int Type of the setting
     */
    public function type(): int
    {
        return Setting::TYPE_INTEGER;
    }

    /**
     * Checks if the value is correct
     *
     * @return bool True if the value is correct or false otherwise
     */
    protected function checkValue(): bool
    {
        if (gettype($this->value) === 'integer') {
            $sch = &SettingsList::$schema[$this->name];
            if (!isset($sch['minimum']) || $this->value >= $sch['minimum']) {
                if (!isset($sch['maximum']) || $this->value <= $sch['maximum']) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Converts a string to the value
     *
     * @param string $s String for conversion
     *
     * @return void
     */
    protected function stringToValue(string $s): void
    {
        $this->value = intval($s);
    }

    /**
     * Returns a string representation of the value
     *
     * @return string The string value
     */
    protected function valueToString(): string
    {
        return strval($this->value);
    }

    /**
     * Returns an array with setting data
     *
     * @return array Setting data
     */
    public function toArray(): array
    {
        $res = parent::toArray();
        $sch = &SettingsList::$schema[$this->name];
        if (isset($sch['minimum'])) {
            $res['minimum'] = $sch['minimum'];
        }
        if (isset($sch['maximum'])) {
            $res['maximum'] = $sch['maximum'];
        }
        return $res;
    }
}
