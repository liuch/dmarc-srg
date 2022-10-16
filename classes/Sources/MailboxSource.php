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
 * This file contains the class MailboxSource
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Sources;

use Exception;
use Liuch\DmarcSrg\ReportFile\ReportFile;

/**
 * This class is designed to process report files from an mail box.
 */
class MailboxSource extends Source
{
    private $list  = null;
    private $index = 0;
    private $msg   = null;

    /**
     * Returns an instance of the ReportFile class for the current email message.
     *
     * @return ReportFile
     */
    public function current(): object
    {
        $this->msg = $this->data->message($this->list[$this->index]);
        try {
            $this->msg->validate();
        } catch(Exception $e) {
            throw new \Exception('Incorrect message: ' . $e->getMessage(), -1);
        }
        $att = $this->msg->attachment();
        return ReportFile::fromStream($att->datastream(), $att->filename(), $att->mime_type());
    }

    /**
     * Returns the index of the currect email message.
     *
     * @return int
     */
    public function key(): int
    {
        return $this->index;
    }

    /**
     * Moves forward to the next email message
     *
     * @return void
     */
    public function next(): void
    {
        $this->msg = null;
        ++$this->index;
    }

    /**
     * Gets a list of unread messages and rewinds the position to the first email message.
     *
     * @return void
     */
    public function rewind(): void
    {
        $this->list  = $this->data->sort(SORTDATE, 'UNSEEN', false);
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
     * Marks the accepted email message with flag Seen.
     *
     * @return void
     */
    public function accepted(): void
    {
        if ($this->msg) {
            $this->msg->setSeen();
        }
    }

    /**
     * Moves the rejected email message to folder `failed`.
     * If the folder does not exits, it will be created.
     *
     * @return void
     */
    public function rejected(): void
    {
        $this->data->ensureMailbox('failed');
        $this->data->moveMessage($this->list[$this->index], 'failed');
    }

    /**
     * Returns type of the source.
     *
     * @return int
     */
    public function type(): int
    {
        return Source::SOURCE_MAILBOX;
    }

    /**
     * Returns the current email message.
     *
     * @return MailMessage|null
     */
    public function mailMessage()
    {
        return $this->msg;
    }
}
