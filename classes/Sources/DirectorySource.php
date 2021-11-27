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
 * This file contains the class DirectorySource
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Sources;

use Liuch\DmarcSrg\ReportFile\ReportFile;
use Liuch\DmarcSrg\ReportLog\ReportLogItem;

/**
 * This class is designed to process report files from local server directories.
 */
class DirectorySource extends Source
{
    private $list  = null;
    private $index = 0;

    /**
     * Returns an instance of the ReportFile class for the current file.
     */
    public function current()
    {
        return ReportFile::fromFile($this->list[$this->index]);
    }

    /**
     * Returns the index of the currect file.
     *
     * @return int
     */
    public function key()
    {
        return $this->index;
    }

    /**
     * Moves forward to the next file.
     *
     * @return void
     */
    public function next(): void
    {
        ++$this->index;
    }

    /**
     * Rewinds the position to the first file.
     *
     * @return void
     */
    public function rewind(): void
    {
        if (is_null($this->list)) {
            $this->path = $this->data->toArray()['location'];
            if (!is_dir($this->path)) {
                throw new \Exception("The {$this->path} directory does not exist!", -1);
            }
            try {
                $fs = new \FilesystemIterator($this->path);
            } catch (\Exception $e) {
                throw new \Exception('Error accessing directory ' . $this->path, -1, $e);
            }
            $this->list = [];
            foreach ($fs as $entry) {
                if ($entry->isFile()) {
                    $this->list[] = $entry->getPathname();
                }
            }
        }
        $this->index = 0;
    }

    /**
     * Checks if the current postion is valid
     *
     * @return bool
     */
    public function valid(): bool
    {
        return isset($this->list[$this->index]);
    }

    /**
     * Removes the accepted report file.
     *
     * @return void
     */
    public function accepted(): void
    {
        if (!@unlink($this->list[$this->index])) {
            throw new \Exception('Error deleting file from directory ' . $this->path, -1);
        }
    }

    /**
     *  Moves the rejected report file to the `failed` directory.
     *
     * @return void
     */
    public function rejected(): void
    {
        $fdir = $this->path . 'failed';
        if (!is_dir($fdir)) {
            if (!@mkdir($fdir)) {
                throw new \Exception('Error creating directory ' . $fdir . '/', -1);
            }
            chmod($fdir, 0700);
        }
        $file = $this->list[$this->index];
        if (!@rename($file, $fdir . '/' . basename($file))) {
            throw new \Exception('Error moving file to directory ' . $fdir . '/');
        }
    }

    /**
     * Returns type of the source.
     *
     * @return int
     */
    public function type(): int
    {
        return Source::SOURCE_DIRECTORY;
    }
}
