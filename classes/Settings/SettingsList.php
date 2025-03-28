<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2021-2025 Aleksey Andreev (liuch)
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

use Liuch\DmarcSrg\Core;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\RuntimeException;

/**
 * This class is designed to work with the list of the settings
 */
class SettingsList
{
    public const ORDER_ASCENT  = 0;
    public const ORDER_DESCENT = 1;

    private $core  = null;
    private $order = self::ORDER_ASCENT;

    /**
     * The constructor
     *
     * @param Core|null $core Instance of the Core class
     */
    public function __construct($core = null)
    {
        $this->core = $core ?? Core::instance();
    }

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
        $list   = [];
        $user   = $this->core->getCurrentUser();
        $db_map = $this->core->database()->getMapper('setting')->list($user->id());
        foreach (static::$schema as $name => &$sch_data) {
            if ($sch_data['public'] ?? false) {
                $value = $db_map[$name] ?? $sch_data['default'];
                switch ($sch_data['type']) {
                    case 'select':
                        $list[] = new SettingStringSelect([
                            'name'  => $name,
                            'user'  => $user,
                            'value' => $value
                        ], true, $this->core);
                        break;
                    case 'integer':
                        $list[] = new SettingInteger([
                            'name'  => $name,
                            'user'  => $user,
                            'value' => intval($value)
                        ], true, $this->core);
                        break;
                    case 'string':
                        $list[] = new SettingString([
                            'name'  => $name,
                            'user'  => $user,
                            'value' => $value
                        ], true, $this->core);
                        break;
                }
            }
        }
        unset($sch_data);

        $dir = $this->order == self::ORDER_ASCENT ? 1 : -1;
        usort($list, static function ($a, $b) use ($dir) {
            return ($a->name() <=> $b->name()) * $dir;
        });

        return [
            'list' => $list,
            'more' => false
        ];
    }

    /**
     * Sets the sorting direction for the list
     *
     * @param int $direction The sorting direction. ORDER_ASCENT or ORDER_DESCENT must be used here.
     *
     * @return SettingsList $this
     */
    public function setOrder(int $direction)
    {
        if ($direction !== self::ORDER_DESCENT) {
            $direction = self::ORDER_ASCENT;
        }
        $this->order = $direction;
        return $this;
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
            throw new SoftException('Unknown setting name: ' . $name);
        }
    }

    /**
     * Returns an instance of the Setting class by its name
     *
     * It returns an instance of the Setting class but only if it is marked public.
     *
     * @param string   $name Setting name
     * @param int|null $user User or null for the current user
     *
     * @return Setting
     */
    public static function getSettingByName(string $name, $user = null)
    {
        self::checkName($name);
        if (!(self::$schema[$name]['public'] ?? false)) {
            throw new SoftException('Attempt to access an internal variable');
        }

        $data = [
            'name' => $name,
            'user' => is_null($user) ? Core::instance()->getCurrentUser() : $user
        ];

        switch (self::$schema[$name]['type']) {
            case 'string':
                return new SettingString($data);
            case 'select':
                return new SettingStringSelect($data);
            case 'integer':
                return new SettingInteger($data);
            default:
                throw new RuntimeException('Unknown setting type');
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
            'default' => '30'
        ],
        'status.emails-filter-when-list-filtered' => [
            'type'    => 'select',
            'public'  => true,
            'options' => [ 'yes', 'no' ],
            'default' => 'yes'
        ],
        'report-view.sort-records-by' => [
            'type'    => 'select',
            'public'  => true,
            'options' => [ 'ip,ascent', 'ip,descent', 'message-count,ascent', 'message-count,descent' ],
            'default' => 'message-count,descent'
        ],
        'report-view.filter.initial-value' => [
            'type'    => 'select',
            'public'  => true,
            'options' => [ 'none', 'from-list', 'last-value', 'last-value-tab' ],
            'default' => 'last-value-tab'
        ],
        'log-view.sort-list-by' => [
            'type'    => 'select',
            'public'  => true,
            'options' => [ 'event-time,ascent', 'event-time,descent' ],
            'default' => 'event-time,ascent'
        ],
        'ui.datetime.offset' => [
            'type'    => 'select',
            'public'  => true,
            'options' => [ 'auto', 'utc', 'local' ],
            'default' => 'auto'
        ],
        'ui.ipv4.url' => [
            'type'    => 'string',
            'public'  => true,
            'default' => '!https://who.is/whois-ip/ip-address/{$ip}'
        ],
        'ui.ipv6.url' => [
            'type'    => 'string',
            'public'  => true,
            'default' => '!https://who.is/whois-ip/ip-address/{$ip}'
        ]
    ];
}
