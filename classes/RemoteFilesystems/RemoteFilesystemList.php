<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2023 Aleksey Andreev (liuch)
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
 * This file contains the class RemoteFilesystemList
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\RemoteFilesystems;

use Liuch\DmarcSrg\Core;
use Liuch\DmarcSrg\Exception\LogicException;

/**
 * This class is designed to work with the list of remote filesystems which are listed in the configuration file.
 */
class RemoteFilesystemList
{
    private $list   = null;
    private $silent = false;

    /**
     * Constructor
     *
     * @param bool $silent If true, it skips incorrect items, otherwise it throws an exception
     */
    public function __construct(bool $silent)
    {
        $this->silent = $silent;
    }

    /**
     * Returns a list of remote filesystems for the setting file
     *
     * @return array Array with instances of RemoteFilesystem class
     */
    public function list(): array
    {
        $this->ensureList();
        return $this->list;
    }

    /**
     * Returns an instance of the RemoteFilesystem class by its Id
     *
     * @param int $id Id of the required filesystem
     *
     * @return RemoteFilesystem
     */
    public function filesystem(int $id)
    {
        $this->ensureList();
        if ($id <= 0 || $id > count($this->list)) {
            throw new LogicException('Incorrect filesystem Id');
        }
        return $this->list[$id - 1];
    }

    /**
     * Checks the accessibility of the specified filesystem or all the filesystem from configuration file if $id is 0.
     *
     * @param int $id RemoteFilesystem Id to check
     *
     * @return array Result array with `error_code` and `message` fields. For one filesystem and if there is no error,
     *               a field `status` will be added to the result.
     */
    public function check(int $id): array
    {
        if ($id !== 0) {
            $fs = $this->filesystem($id);
            return $fs->check();
        }

        $this->ensureList();
        $results = [];
        $err_cnt = 0;
        $fs_cnt = count($this->list);
        for ($i = 0; $i < $fs_cnt; ++$i) {
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
            $res['message'] = sprintf('%d of %d filesystems have failed the check', $err_cnt, $fs_cnt);
        }
        $res['results'] = $results;
        return $res;
    }

    /**
     * Creates an array of filesystems from the configuration file if it does not exist
     * for using in other methods of the class.
     *
     * @return void
     */
    private function ensureList(): void
    {
        if (!is_null($this->list)) {
            return;
        }

        $filesystems = Core::instance()->config('remote_filesystems');

        $this->list = [];
        if (is_array($filesystems)) {
            $cnt = count($filesystems);
            if ($cnt > 0) {
                if (isset($filesystems[0])) {
                    $id = 1;
                    foreach ($filesystems as &$fs) {
                        try {
                            $this->list[] = new RemoteFilesystem($id, $fs);
                            ++$id;
                        } catch (LogicException $e) {
                            if (!$this->silent) {
                                throw $e;
                            }
                        }
                    }
                    unset($fs);
                } else {
                    try {
                        $this->list[] = new RemoteFilesystem(1, $filesystems);
                    } catch (LogicException $e) {
                        if (!$this->silent) {
                            throw $e;
                        }
                    }
                }
            }
        }
    }
}
