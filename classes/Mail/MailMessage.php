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
    private $attachment;
    private $attachments_cnt;

    public function __construct($conn, $number)
    {
        $this->conn = $conn;
        $this->number = $number;
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

    public function validate()
    {
        $this->ensureAttachment();
        if ($this->attachments_cnt !== 1) {
            throw new Exception('Attachment count is not valid (' . $this->attachments_cnt . ')');
        }

        $bytes = $this->attachment->size();
        if ($bytes < 50 || $bytes > 1 * 1024 * 1024) {
            throw new Exception('Attachment filesize is not valid (' . $bytes . ' bytes)');
        }

        $mime_type = $this->attachment->mimeType();
        if (!in_array($mime_type, ['application/zip', 'application/gzip', 'text/xml'])) {
            throw new Exception('Attachment file type is not valid (' . $mime_type . ')');
        }
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

        if (empty($filename) && $part->ifparameters) {
            $filename = $this->getAttribute($part->parameters, 'name');
        }

        if (empty($filename)) {
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
