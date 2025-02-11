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
 * This file contains implementation of the class SettingString
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
 * This class contains the implementation of the setting for string values.
 */
class SettingString extends Setting
{
    /**
     * Returns the type of the setting
     *
     * @return int Type of the setting
     */
    public function type(): int
    {
        return Setting::TYPE_STRING;
    }

    /**
     * Checks if the value is correct
     *
     * @return bool True if the value is correct or false otherwise
     */
    protected function checkValue(): bool
    {
        return (gettype($this->value) === 'string');
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
        $this->value = $s;
    }

    /**
     * Returns a string representation of the value
     *
     * @return string The string value
     */
    protected function valueToString(): string
    {
        return $this->value;
    }
}
