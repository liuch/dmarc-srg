<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2022-2023 Aleksey Andreev (liuch)
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
 * This file contains the class SourceAction
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Sources;

use Liuch\DmarcSrg\Exception\LogicException;

/**
 * It's a class for describing one action of source
 */
class SourceAction
{
    public const ACTION_SEEN   = 1;
    public const ACTION_MOVE   = 2;
    public const ACTION_DELETE = 3;
    public const FLAG_BASENAME = 1;

    private $valid = false;
    private $type  = 0;
    private $param = null;

    /**
     * The constructor
     *
     * @param string $action Action name with parameter separated by colon
     *                       Examples: 'move_to:failed', 'delete'
     *
     * @return void
     */
    private function __construct(string $action)
    {
        if (($delim_offset = mb_strpos($action, ':')) === false) {
            $name  = $action;
            $param = null;
        } else {
            $name  = mb_substr($action, 0, $delim_offset);
            $param = mb_substr($action, $delim_offset + 1);
        }
        switch ($name) {
            case 'mark_seen':
                $this->type = self::ACTION_SEEN;
                if (!empty($param)) {
                    return;
                }
                break;
            case 'move_to':
                $this->type = self::ACTION_MOVE;
                if (empty($param)) {
                    return;
                }
                break;
            case 'delete':
                $this->type = self::ACTION_DELETE;
                if (!empty($param)) {
                    return;
                }
                break;
            default:
                return;
        }
        $this->param = $param;
        $this->valid = true;
    }

    /**
     * The getter
     *
     * @param string $name Property name. Must be one of the following: 'type', 'param'
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        if (in_array($name, [ 'type', 'param' ])) {
            return $this->$name;
        }
        throw new LogicException('Undefined property: ' . $name);
    }

    /**
     * Handles a setting, flags, and returns an array of SourceAction instances
     *
     * @param string|array $setting Setting from the conf.php
     * @param int          $flags   Flags of extra checking the result
     * @param string       $default Action to add if the result array is empty
     *
     * @return array
     */
    public static function fromSetting($setting, int $flags, string $default): array
    {
        if (gettype($setting) !== 'array') {
            $setting = [ $setting ];
        }
        $tmap = [];
        $list = [];
        foreach ($setting as $it) {
            if (gettype($it) === 'string') {
                $sa = new self($it);
                if ($sa->valid && !isset($tmap[$sa->type])) {
                    if (($flags & self::FLAG_BASENAME) && !self::checkBasename($sa)) {
                        continue;
                    }
                    $list[] = $sa;
                    $tmap[$sa->type] = true;
                }
            }
        }
        if (count($list) === 0) {
            $sa = new self($default);
            if ($sa->valid) {
                $list[] = $sa;
            }
        }
        return $list;
    }

    /**
     * Checks if the param is just a directory name without a path
     *
     * @param self $sa
     *
     * @return bool
     */
    private static function checkBasename($sa): bool
    {
        return ($sa->type !== self::ACTION_MOVE || basename($sa->param) === $sa->param);
    }
}
