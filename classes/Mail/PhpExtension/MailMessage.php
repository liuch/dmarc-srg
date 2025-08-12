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

namespace Liuch\DmarcSrg\Mail\PhpExtension;

use Liuch\DmarcSrg\Core;
use Liuch\DmarcSrg\Mail\MailMessageInterface;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\MailboxException;

class MailMessage extends \Liuch\DmarcSrg\Mail\MailMessage
{
    private $mailbox;
    private $number;
    private $attachments_cnt;

    public function __construct($data)
    {
        $this->mailbox = $data['mailbox'];
        $this->number  = $data['number'];
        $this->attachments_cnt = -1;
    }

    public function overview(): array
    {
        $res = [];
        $list = @imap_fetch_overview($this->mailbox->connection(), strval($this->number), FT_UID);
        if (($ov = $list[0] ?? null)) {
            if (property_exists($ov, 'from')) {
                $res['from'] = $ov->from;
            }
            if (property_exists($ov, 'date')) {
                try {
                    $res['date'] = new \DateTime($ov->date);
                } catch (\Exception $e) {
                }
            }
        } else {
            if ($error_message = imap_last_error()) {
                Core::instance()->logger()->error("imap_fetch_overview failed: {$error_message}");
            }
            MailBox::resetErrorStack();
        }
        return $res;
    }

    public function markSeen(): void
    {
        MailBox::resetErrorStack();
        @imap_setflag_full($this->mailbox->connection(), strval($this->number), '\\Seen', ST_UID);
        if (($error_message = imap_last_error())) {
            MailBox::resetErrorStack();
            Core::instance()->logger()->error("imap_setflag_full failed: {$error_message}");
            throw new MailboxException("Failed to make a message seen: {$error_message}");
        }
    }

    public function attachment()
    {
        $this->ensureAttachment();
        return $this->attachment;
    }

    public function move(string $folder): void
    {
        $this->mailbox->connect();
        $target = MailBox::utf8ToMutf7($this->mailbox->folder()) .
            $this->mailbox->delimiter() . MailBox::utf8ToMutf7($folder);
        try {
            $res = imap_mail_move($this->mailbox->connection(), strval($this->number), $target, CP_UID);
            $this->mailbox->setExpunge();
        } catch (\ErrorException $e) {
            $res = false;
        }
        $error_message = $this->mailbox->logImapError('imap_mail_move');
        if (!$res) {
            throw new MailboxException(
                'Failed to move a message',
                -1,
                new \ErrorException($error_message ?? 'Unknown')
            );
        }
    }

    public function delete(): void
    {
        $this->mailbox->connect();
        $err = null;
        try {
            imap_delete($this->mailbox->connection(), strval($this->number), FT_UID);
            $this->mailbox->setExpunge();
        } catch (\ErrorException $e) {
            $err = $e;
        }
        if (($error_message = $this->mailbox->logImapError('imap_delete'))) {
            $err = new MailboxException('IMAP: Cannot delete a message', -1, $err);
        }
        if ($err) {
            throw $err;
        }
    }

    protected function attachmentCount(): int
    {
        $this->scanAttachments();
        return $this->attachments_cnt;
    }

    protected function ensureAttachment(): void
    {
        $this->scanAttachments();
    }

    private function scanAttachments(): void
    {
        if ($this->attachments_cnt === -1) {
            $structure = imap_fetchstructure($this->mailbox->connection(), $this->number, FT_UID);
            if ($structure === false) {
                throw new MailboxException('FetchStructure failed: ' . imap_last_error());
            }
            $this->attachments_cnt = 0;
            $parts = isset($structure->parts) ? $structure->parts : [ $structure ];

            $allParts = [];
            foreach ($parts as $index => &$part) {
                $msgIndex = $index + 1;
                // when it's an entire attached message: MESSAGE/RFC822
                if (isset($part->parts) && count($part->parts) > 0) {
                    foreach ($part->parts as $subIndex => &$subPart) {
                        $allParts[$msgIndex . '.' . ($subIndex + 1)] = $subPart;
                    }
                    unset($subPart);// Remove the last dangling reference
                    continue;
                }
                $allParts[$msgIndex] = $part;
            }
            unset($part);// Remove the last dangling reference

            foreach ($allParts as $parNbr => &$part) {
                $params = $this->scanAttachmentPart($part, $parNbr);
                if ($params) {
                    ++$this->attachments_cnt;
                    if (!$this->attachment) {
                        $params['mailbox'] = $this->mailbox;
                        $this->attachment = new MailAttachment($params);
                    }
                }
            }
            unset($part);// Remove the last dangling reference
        }
    }

    /**
     * @param string $parNbr
     * @return array|null
     */
    private function scanAttachmentPart(&$part, $parNbr)
    {
        $filename = null;
        if ($part->ifdparameters) {
            $filename = $this->getAttribute($part->dparameters, 'filename');
        }

        if (empty($filename) && $part->ifparameters) {
            $filename = $this->getAttribute($part->parameters, 'name');
        }

        if (empty($filename)) {
            return null;
        }

        return [
            'filename' => imap_utf8($filename),
            'bytes'    => isset($part->bytes) ? $part->bytes : -1,
            'number'   => $parNbr,
            'mnumber'  => $this->number,
            'encoding' => $part->encoding
        ];
    }

    private function getAttribute(&$params, $name)
    {
        // need to check all objects as imap_fetchstructure
        // returns multiple objects with the same attribute name,
        // but first entry contains a truncated value
        $value = null;
        foreach ($params as &$obj) {
            if (strcasecmp($obj->attribute, $name) === 0) {
                $value = $obj->value;
            }
        }
        return $value;
    }
}
