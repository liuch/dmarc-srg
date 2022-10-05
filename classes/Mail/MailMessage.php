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
 */

namespace Liuch\DmarcSrg\Mail;

use Exception;

class MailMessage
{
    private $conn;
    private $number;
    private $structure;
    private $attachment;
    private $attachments_cnt;

    public function __construct($conn, $number)
    {
        $this->conn = $conn;
        $this->number = $number;
        $this->structure = null;
        $this->attachment = null;
        $this->attachments_cnt = -1;
    }

    public function overview()
    {
        $res = @imap_fetch_overview($this->conn, strval($this->number));
        if (!isset($res[0])) {
            return false;
        }
        return $res[0];
    }

    public function setSeen()
    {
        if (!@imap_setflag_full($this->conn, strval($this->number), '\\Seen')) {
            throw new Exception('Failed to make a message seen: ' . imap_last_error(), -1);
        }
    }

    public function isCorrect()
    {
        $this->ensureAttachment();
        if ($this->attachments_cnt === 1) {
            $bytes = $this->attachment->size();
            if ($bytes >= 50 && $bytes <= 1 * 1024 * 1024) {
                $ext = $this->attachment->extension();
                if ($ext === 'zip' || $ext === 'gz' || $ext === 'xml') {
                    return true;
                }
            }
        }
        return false;
    }

    public function attachment()
    {
        return $this->attachment;
    }

    private function ensureAttachment()
    {
        if ($this->attachments_cnt === -1) {
            $structure = imap_fetchstructure($this->conn, $this->number);
            if ($structure === false) {
                throw new Exception('FetchStructure failed: ' . imap_last_error(), -1);
            }
            $this->attachments_cnt = 0;
            $parts = isset($structure->parts) ? $structure->parts : [ $structure ];
            foreach ($parts as $index => &$part) {
                $att_part = $this->scanAttachmentPart($part, $index + 1);
                if ($att_part) {
                    ++$this->attachments_cnt;
                    if (!$this->attachment) {
                        $this->attachment = new MailAttachment($this->conn, $att_part);
                    }
                }
            }
            unset($part);
        }
    }

    private function scanAttachmentPart(&$part, $number)
    {
        $filename = null;
        if ($part->ifdparameters) {
            $filename = $this->getAttribute($part->dparameters, 'filename');
        }

        // ugly hack to use ifparameters if dparameter is truncated
        if ($part->ifparameters) {
            $if_filename = $this->getAttribute($part->parameters, 'name');
            if (!$filename || strlen($filename) < strlen($if_filename)) {
                $filename = $if_filename;
            }
        }

        if (!$filename) {
            return null;
        }

        return [
            'filename' => imap_utf8($filename),
            'bytes'    => $part->bytes,
            'number'   => $number,
            'mnumber'  => $this->number,
            'encoding' => $part->encoding
        ];
    }

    private function getAttribute($params, $name)
    {
        foreach ($params as &$obj) {
            if (strcasecmp($obj->attribute, $name) === 0) {
                return $obj->value;
            }
        }
        return null;
    }
}
