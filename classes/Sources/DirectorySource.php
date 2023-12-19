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

use Liuch\DmarcSrg\Core;
use Liuch\DmarcSrg\ReportFile\ReportFile;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\RuntimeException;

/**
 * This class is designed to process report files from local server directories.
 */
class DirectorySource extends Source
{
    private $path   = null;
    private $list   = null;
    private $index  = 0;
    private $params = null;

    /**
     * Sets parameters that difine the behavior of the source
     *
     * @param $params Key-value array
     *                'when_done'   => one or more rules to be executed after successful report processing
     *                                 (array|string)
     *                'when_failed' => one or more rules to be executed after report processing fails
     *                                 (array|string)
     *
     * @return void
     */
    public function setParams(array $params): void
    {
        $this->params = [];
        $this->params['when_done'] = SourceAction::fromSetting(
            $params['when_done'] ?? [],
            SourceAction::FLAG_BASENAME,
            'delete'
        );
        $this->params['when_failed'] = SourceAction::fromSetting(
            $params['when_failed'] ?? [],
            SourceAction::FLAG_BASENAME,
            'move_to:failed'
        );
    }

    /**
     * Returns an instance of the ReportFile class for the current file.
     *
     * @return ReportFile
     */
    public function current(): object
    {
        return ReportFile::fromFile($this->list[$this->index]);
    }

    /**
     * Returns the index of the current file.
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
        if (is_null($this->list)) {
            $this->path = $this->data->toArray()['location'];
            if (!is_dir($this->path)) {
                throw new SoftException("The {$this->path} directory does not exist!");
            }
            try {
                $fs = new \FilesystemIterator($this->path);
            } catch (\Exception $e) {
                throw new RuntimeException("Error accessing directory {$this->path}", -1, $e);
            }
            $this->list = [];
            foreach ($fs as $entry) {
                if ($entry->isFile()) {
                    $this->list[] = $entry->getPathname();
                }
            }
        }
        if (is_null($this->params)) {
            $this->setParams([]);
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
     * Processes the accepted report file according to the settings
     *
     * @return void
     */
    public function accepted(): void
    {
        $this->processReportFileActions($this->params['when_done']);
    }

    /**
     * Processes the rejected report file according to the settings
     *
     * @return void
     */
    public function rejected(): void
    {
        $this->processReportFileActions($this->params['when_failed']);
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

    /**
     * Logs an error message
     *
     * @param string $message
     */
    private function logError(string $message): void
    {
        Core::instance()->logger()->error($message);
    }

    /**
     * Processes the current report file according to settings
     *
     * @param array $actions List of actions to apply to the file
     *
     * @return void
     */
    private function processReportFileActions(array &$actions): void
    {
        foreach ($actions as $sa) {
            switch ($sa->type) {
                case SourceAction::ACTION_DELETE:
                    $this->deleteReportFile();
                    break;
                case SourceAction::ACTION_MOVE:
                    $this->moveReportFile($sa->param);
                    break;
            }
        }
    }

    /**
     * Deletes the current report file
     *
     * @return void
     */
    private function deleteReportFile(): void
    {
        try {
            unlink($this->list[$this->index]);
        } catch (\ErrorException $e) {
            $error_message = "Error deleting file from directory {$this->path}";
            $this->logError($error_message);
            throw new RuntimeException($error_message, -1, $e);
        }
    }

    /**
     * Moves the current report file
     *
     * @param string $dir_name Directory name where to move the report file to
     *
     * @return void
     */
    private function moveReportFile(string $dir_name): void
    {
        $fdir = $this->path . $dir_name;
        if (!is_dir($fdir)) {
            try {
                mkdir($fdir);
            } catch (\ErrorException $e) {
                $e = new RuntimeException("Error creating directory {$fdir}/", -1, $e);
                $this->logError(strval($e));
                throw $e;
            }
            try {
                chmod($fdir, 0700);
            } catch (\ErrorException $e) {
                $this->logError(strval($e));
            }
        }
        $file = $this->list[$this->index];
        try {
            rename($file, $fdir . '/' . basename($file));
        } catch (\ErrorException $e) {
            $e = new RuntimeException("Error moving file to directory {$fdir}/", -1, $e);
            $this->logError(strval($e));
            throw $e;
        }
    }
}
