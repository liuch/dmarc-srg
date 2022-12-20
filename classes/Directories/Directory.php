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
 * This file contains the class Directory
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Directories;

use Liuch\DmarcSrg\ErrorHandler;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\LogicException;
use Liuch\DmarcSrg\Exception\RuntimeException;

/**
 * This class is designed to work with the report directories which are listed in the configuration file.
 */
class Directory
{
    private $id       = null;
    private $name     = null;
    private $location = null;

    /**
     * It's the constructor of the class
     *
     * @param int   $id   Id of the directory. In fact, it is a serial number in the configuration file.
     * @param array $data An array with the following fields:
     *                    `location` (string) - Location of the directory in the file system.
     *                    `name` (string)     - Name of the directory. It is optional.
     *
     * @return void
     */
    public function __construct(int $id, array $data)
    {
        if (isset($data['name']) && gettype($data['name']) !== 'string') {
            throw new LogicException('Directory name must be either null or a string value');
        }
        if (!isset($data['location']) || gettype($data['location']) !== 'string') {
            throw new LogicException('Directory location must be a string value');
        }
        if (empty($data['location'])) {
            throw new LogicException('Directory location must not be an empty string');
        }

        $this->id       = $id;
        $this->name     = $data['name'] ?? null;
        $this->location = $data['location'];
        if (empty($this->name)) {
            $this->name = 'Directory ' . $this->id;
        }
        if (substr($this->location, -1) !== '/') {
            $this->location .= '/';
        }
    }

    /**
     * Returns an array with directory configuration data.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id'       => $this->id,
            'name'     => $this->name,
            'location' => $this->location
        ];
    }

    /**
     * Checks the existence and accessibility of the directory. Returns the result as an array.
     *
     * @return array
     */
    public function check(): array
    {
        try {
            self::checkPath($this->location, true);
            self::checkPath($this->location . 'failed/', false);
        } catch (RuntimeException $e) {
            return ErrorHandler::exceptionResult($e);
        }

        return [
            'error_code' => 0,
            'message'    => 'Successfully',
            'status'     => [
                'files'  => $this->count()
            ]
        ];
    }

    /**
     * Returns the total number of files in the directory.
     *
     * @return int
     */
    public function count(): int
    {
        $cnt = 0;
        try {
            $fs = new \FilesystemIterator($this->location);
        } catch (\Exception $e) {
                throw new RuntimeException("Error accessing directory {$this->location}", -1, $e);
        }
        foreach ($fs as $entry) {
            if ($entry->isFile()) {
                ++$cnt;
            }
        }
        return $cnt;
    }

    /**
     * Checks accessibility of a directory by its path. Throws an exception in case of any error.
     *
     * @param string $path      Path to the directory to check.
     * @param bool   $existence If true, the absence of the directory causes an error.
     *
     * @return void
     */
    private static function checkPath(string $path, bool $existence): void
    {
        if (!file_exists($path)) {
            if ($existence) {
                throw new SoftException($path . ' directory does not exist!');
            }
            return;
        }
        if (!is_dir($path)) {
            throw new SoftException($path . ' is not a directory!');
        }
        if (!is_readable($path)) {
            throw new SoftException($path . ' directory is not readable!');
        }
        if (!is_writable($path)) {
            throw new SoftException($path . ' directory is not writable!');
        }
    }
}
