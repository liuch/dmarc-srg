<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2020-2024 Aleksey Andreev (liuch)
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
 */

namespace Liuch\DmarcSrg\Mail\ImapEngine;

use Liuch\DmarcSrg\Core;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\MailboxException;

use DirectoryTree\ImapEngine\Mailbox as IMailbox;
use DirectoryTree\ImapEngine\Enums\ImapFetchIdentifier;
use DirectoryTree\ImapEngine\Exceptions\Exception as IException;

class MailMessage extends \Liuch\DmarcSrg\Mail\MailMessage
{
    private $message;
    private $mailbox;

    public function __construct($data)
    {
        $this->message = $data['message'];
        $this->mailbox = $data['mailbox'];
    }

    public function overview(): array
    {
        $res = [];
        if (($from = $this->message->from())) {
            $res['from'] = $from->name();
        }
        if (($date = $this->message->date())) {
            $res['date'] = $date;
        }
        return $res;
    }

    public function markSeen(): void
    {
        try {
            $this->message->markSeen();
        } catch (IException $e) {
            Core::instance()->logger()->error("IMAP error: {$e->getMessage()}");
            throw new MailboxException('IMAP: Cannot mark a message', -1, $e);
        }
    }

    public function attachment()
    {
        $this->ensureAttachment();
        return $this->attachment;
    }

    public function move(string $target_folder): void
    {
        try {
            $current_folder = $this->message->folder();
            $target_path = $current_folder->path() . $current_folder->delimiter() . $target_folder;
            $this->message->move($target_path, false);
            $this->mailbox->setExpunge();
        } catch (IException $e) {
            Core::instance()->logger()->error("IMAP error: {$e->getMessage()}");
            throw new MailboxException('IMAP: Cannot move a message', -1, $e);
        }
    }

    public function delete(): void
    {
        try {
            $this->message->delete(false);
            $this->mailbox->setExpunge();
        } catch (IException $e) {
            Core::instance()->logger()->error("IMAP error: {$e->getMessage()}");
            throw new MailboxException('IMAP: Cannot delete a message', -1, $e);
        }
    }

    protected function attachmentCount(): int
    {
        $this->ensureBody();
        return $this->message->attachmentCount();
    }

    protected function ensureAttachment(): void
    {
        $this->ensureBody();
        $alist = $this->message->attachments();
        if (!isset($alist[0])) {
            throw new SoftException('The message has no attachment');
        }
        $this->attachment = new MailAttachment($alist[0]);
    }

    private function ensureBody(): void
    {
        if (!$this->message->hasBody()) {
            $msg = $this->message->folder()->messages()
                        ->withHeaders()->withBody()
                        ->find($this->message->uid(), ImapFetchIdentifier::Uid);
            if (!$msg) {
                throw new SoftException('The message not found');
            }
            $this->message = $msg;
        }
    }
}
