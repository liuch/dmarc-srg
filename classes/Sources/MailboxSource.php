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

use Liuch\DmarcSrg\ReportFile\ReportFile;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\RuntimeException;

/**
 * This class is designed to process report files from an mail box.
 */
class MailboxSource extends Source
{
    private $list   = null;
    private $index  = 0;
    private $msg    = null;
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
            0,
            'mark_seen'
        );
        $this->params['when_failed'] = SourceAction::fromSetting(
            $params['when_failed'] ?? [],
            0,
            'move_to:failed'
        );
    }

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
        } catch (SoftException $e) {
            throw new SoftException('Incorrect message: ' . $e->getMessage(), $e->getCode());
        } catch (RuntimeException $e) {
            throw new RuntimeException('Incorrect message', -1, $e);
        }
        $att = $this->msg->attachment();
        return ReportFile::fromStream($att->datastream(), $att->filename(), $att->mimeType());
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
        $this->msg   = null;
        $this->list  = $this->data->sort(SORTDATE, 'UNSEEN', false);
        $this->index = 0;
        if (is_null($this->params)) {
            $this->setParams([]);
        }
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
     * Processes the accepted email messages according to the settings
     *
     * @return void
     */
    public function accepted(): void
    {
        if ($this->msg) {
            $this->processMessageActions($this->params['when_done']);
        }
    }

    /**
     * Processes the rejected email messages according to the settings
     *
     * @return void
     */
    public function rejected(): void
    {
        $this->processMessageActions($this->params['when_failed']);
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
     * @return \Liuch\DmarcSrg\Mail\MailMessage|null
     */
    public function mailMessage()
    {
        return $this->msg;
    }

    /**
     * Processes the current report message according to settings
     *
     * @param array $actions List of actions to apply to the message
     *
     * @return void
     */
    private function processMessageActions(array &$actions): void
    {
        foreach ($actions as $sa) {
            switch ($sa->type) {
                case SourceAction::ACTION_SEEN:
                    $this->markMessageSeen();
                    break;
                case SourceAction::ACTION_MOVE:
                    $this->moveMessage($sa->param);
                    break;
                case SourceAction::ACTION_DELETE:
                    $this->deleteMessage();
                    break;
            }
        }
    }

    /**
     * Marks the current report message as seen
     *
     * @return void
     */
    public function markMessageSeen(): void
    {
        $this->msg->setSeen();
    }

    /**
     * Moves the current report message
     *
     * @param string $mbox_name Child mailbox name where to move the current message to.
     *                          If the target mailbox does not exists, it will be created.
     *
     * @return void
     */
    private function moveMessage(string $mbox_name): void
    {
        $this->data->ensureMailbox($mbox_name);
        $this->data->moveMessage($this->list[$this->index], $mbox_name);
    }

    /**
     * Deletes the current report message
     *
     * @return void
     */
    private function deleteMessage(): void
    {
        $this->data->deleteMessage($this->list[$this->index]);
    }
}
