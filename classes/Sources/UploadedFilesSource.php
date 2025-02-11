<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2021-2023 Aleksey Andreev (liuch)
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
 * This file contains the class UploadedFilesSource
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Sources;

use Liuch\DmarcSrg\ReportFile\ReportFile;
use Liuch\DmarcSrg\Exception\SoftException;

/**
 * This class is designed to process report files from uploaded files.
 */
class UploadedFilesSource extends Source
{
    /** @var int */
    private $index = 0;

    /**
     * Returns an instance of the ReportFile class for the current file.
     *
     * @return ReportFile
     */
    public function current(): object
    {
        if ($this->data['error'][$this->index] !== UPLOAD_ERR_OK) {
            throw new SoftException('Failed to upload the report file');
        }

        $realfname = $this->data['name'][$this->index];
        $tempfname = $this->data['tmp_name'][$this->index];
        if (!is_uploaded_file($tempfname)) {
            throw new SoftException('Possible file upload attack');
        }

        return ReportFile::fromFile($tempfname, $realfname, false);
    }

    /**
     * Returns the index of the currect file.
     *
     * @return int
     */
    public function key(): int
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
        $this->index = 0;
    }

    /**
     * Checks if the current postion is valid
     *
     * @return bool
     */
    public function valid(): bool
    {
        return isset($this->data['name'][$this->index]);
    }

    /**
     * Returns type of the source.
     *
     * @return int
     */
    public function type(): int
    {
        return Source::SOURCE_UPLOADED_FILE;
    }
}
