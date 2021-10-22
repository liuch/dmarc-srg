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
 * This file contains the class SettingsList
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
 * This class is designed to work with the list of the settings
 */
class SettingsList
{
    /**
     * Returns a list of the settings
     *
     * It returns a list of the settings that are marked public.
     * The value is taken from the database, if any, or the default.
     *
     * @return array Array with instances of Setting class
     */
    public function getList(): array
    {
        $list = [];
        $fmap = [];

        $st = Database::connection()->query(
            'SELECT `key`, `value` FROM `' . Database::tablePrefix('system') . '` ORDER BY `key`'
        );
        while ($row = $st->fetch(PDO::FETCH_NUM)) {
            $name = $row[0];
            if (isset(static::$schema[$name])) {
                $sch_data = &static::$schema[$name];
                if (!empty($sch_data['public'])) {
                    $value = $row[1];
                    switch ($sch_data['type']) {
                        case 'select':
                            $list[] = new SettingStringSelect([
                                'name'  => $name,
                                'value' => $value
                            ], true);
                            $fmap[$name] = true;
                            break;
                        case 'integer':
                            $list[] = new SettingInteger([
                                'name'  => $name,
                                'value' => intval($value)
                            ], true);
                            $fmap[$name] = true;
                            break;
                    }
                }
            }
        }
        $st->closeCursor();
        unset($sch_data);

        foreach (static::$schema as $sch_name => &$sch_data) {
            if (!isset($fmap[$sch_name]) && !empty($sch_data['public'])) {
                $sch_def = $sch_data['default'];
                switch ($sch_data['type']) {
                    case 'select':
                        $list[] = new SettingStringSelect([
                            'name'  => $sch_name,
                            'value' => $sch_def
                        ]);
                        break;
                    case 'integer':
                        $list[] = new SettingInteger([
                            'name'  => $sch_name,
                            'value' => $sch_def
                        ]);
                        break;
                }
            }
        }
        unset($sch_data);

        usort($list, static function ($a, $b) {
            $an = $a->name();
            $bn = $b->name();
            if ($an === $bn) {
                return 0;
            }
            return ($an < $bn) ? -1 : 1;
        });

        return [
            'list' => $list,
            'more' => false
        ];
    }

    /**
     * Throws an exception if there is no setting with name $name
     *
     * @param string $name Setting name to check
     *
     * @return void
     */
    public static function checkName($name): void
    {
        if (!isset(self::$schema[$name])) {
            throw new Exception('Unknown setting name: ' . $name, -1);
        }
    }

    /**
     * Returns an instance of the Setting class by its name
     *
     * It returns an instance of the Setting class but only if it is marked public.
     *
     * @param string $name Setting name
     *
     * @return Setting
     */
    public static function getSettingByName(string $name)
    {
        self::checkName($name);
        if (empty(self::$schema[$name]['public'])) {
            throw new Exception('Attempt to access an internal variable', -1);
        }

        switch (self::$schema[$name]['type']) {
            case 'string':
                return new SettingString($name);
            case 'select':
                return new SettingStringSelect($name);
            case 'integer':
                return new SettingInteger($name);
        }
    }

    /**
     * List of the possible setting items that must be returned in getList method, their types and other data
     */
    public static $schema = [
        'version' => [
            'type'    => 'string',
            'default' => ''
        ],
        'status.emails-for-last-n-days' => [
            'type'    => 'integer',
            'public'  => true,
            'minimum' => 1,
            'maximum' => 365,
            'default' => 30
        ],
        'report-view.sort-records-by'   => [
            'type'    => 'select',
            'public'  => true,
            'options' => [ 'ip,ascent', 'ip,descent', 'message-count,ascent', 'message-count,descent' ],
            'default' => 'message-count,descent'
        ]
    ];
}
