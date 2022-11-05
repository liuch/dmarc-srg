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
 * This file contains the class DirectoryList
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Directories;

use Liuch\DmarcSrg\Core;
use Liuch\DmarcSrg\Exception\LogicException;

/**
 * This class is designed to work with the list of report directories which are listed in the configuration file.
 */
class DirectoryList
{
    private $list = null;

    /**
     * Returns a list of directories for the setting file
     *
     * @return array Array with instances of Directory class
     */
    public function list(): array
    {
        $this->ensureList();
        return $this->list;
    }

    /**
     * Returns an instance of the Directory class by its Id
     *
     * @param int $id Id of the required directory
     *
     * @return Directory
     */
    public function directory(int $id)
    {
        $this->ensureList();
        if ($id <= 0 || $id > count($this->list)) {
            throw new LogicException('Incorrect directory Id');
        }
        return $this->list[$id - 1];
    }

    /**
     * Checks the accessibility of the specified directory or all the directories from configuration file if $id is 0.
     *
     * @param int $id Directory Id to check
     *
     * @return array Result array with `error_code` and `message` fields. For one directory and if there is no error,
     *               a field `status` will be added to the result.
     */
    public function check(int $id): array
    {
        if ($id !== 0) {
            $dir = $this->directory($id);
            return $dir->check();
        }

        $this->ensureList();
        $results = [];
        $err_cnt = 0;
        $dir_cnt = count($this->list);
        for ($i = 0; $i < $dir_cnt; ++$i) {
            $r = $this->list[$i]->check();
            if ($r['error_code'] !== 0) {
                ++$err_cnt;
            }
            $results[] = $r;
        }
        $res = [];
        if ($err_cnt === 0) {
            $res['error_code'] = 0;
            $res['message'] = 'Successfully';
        } else {
            $res['error_code'] = -1;
            $res['message'] = sprintf('%d of %d directories have failed the check', $err_cnt, $dir_cnt);
        }
        $res['results'] = $results;
        return $res;
    }

    /**
     * Creates an array of directories from the configuration file if it does not exist
     * for using in other methods of the class.
     *
     * @return void
     */
    private function ensureList(): void
    {
        if (!is_null($this->list)) {
            return;
        }

        $directories = Core::instance()->config('directories');

        $this->list = [];
        if (is_array($directories)) {
            $cnt = count($directories);
            if ($cnt > 0) {
                if (isset($directories[0])) {
                    $id = 1;
                    for ($i = 0; $i < $cnt; ++$i) {
                        try {
                            $this->list[] = new Directory($id, $directories[$i]);
                            ++$id;
                        } catch (LogicException $d) {
                            // Just ignore this directory setting.
                        }
                    }
                } else {
                    try {
                        $this->list[] = new Directory(1, $directories);
                    } catch (LogicException $e) {
                        // Just ignore this directory setting.
                    }
                }
            }
        }
    }
}
