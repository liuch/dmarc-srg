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
 * This file contains the class RemoteFilesystemSource
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Sources;

use Liuch\DmarcSrg\Core;
use Liuch\DmarcSrg\ReportFile\ReportFile;
use Liuch\DmarcSrg\Exception\RuntimeException;

/**
 * This class is designed to process report files from remote filesystems.
 */
class RemoteFilesystemSource extends Source
{
    private $index    = 0;
    private $f_attr   = null;
    private $params   = null;
    private $iterator = null;

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
     * Returns an instance of the ReportFile class for the current item.
     *
     * @return ReportFile
     */
    public function current(): object
    {
        if (!$this->f_attr) {
            $this->f_attr = $this->iterator->current();
        }
        $path = $this->f_attr->path();
        return ReportFile::fromStream(
            $this->data->readStream($path),
            basename($path),
            $this->f_attr->mimeType()
        );
    }

    /**
     * Returns the index of the current item.
     *
     * @return int
     */
    public function key(): int
    {
        return $this->index;
    }

    /**
     * Moves forward to the next item.
     *
     * @return void
     */
    public function next(): void
    {
        $this->f_attr = null;
        $this->iterator->next();
        ++$this->index;
    }

    /**
     * Rewinds the position to the first item.
     *
     * @return void
     */
    public function rewind(): void
    {
        $this->iterator = $this->data->listFiles()->getIterator();
        $this->index = 0;
    }

    /**
     * Checks if the current postion is valid
     *
     * @return bool
     */
    public function valid(): bool
    {
        return $this->iterator->valid();
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
        return Source::SOURCE_REMOTE_FILESYSTEM;
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
        $path = $this->f_attr->path();
        try {
            $this->data->delete($path);
        } catch (\ErrorException $e) {
            $error_message = "Error deleting file from remote filesystem: {$path}";
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
        $sou_path = $this->f_attr->path();
        $des_path = dirname($sou_path) . '/' . $dir_name . '/' . basename($sou_path);
        try {
            $this->data->move($sou_path, $des_path);
        } catch (\ErrorException $e) {
            $error_message = "Error moving file within remote filesystem: {$sou_path} to {$des_path}";
            $this->logError($error_message);
            throw new RuntimeException($error_message, -1, $e);
        }
    }
}
