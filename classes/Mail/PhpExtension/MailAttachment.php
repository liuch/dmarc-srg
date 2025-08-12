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

use Liuch\DmarcSrg\Mail\MailAttachmentInterface;
use Liuch\DmarcSrg\ReportFile\ReportFile;
use Liuch\DmarcSrg\Exception\SoftException;

class MailAttachment extends \Liuch\DmarcSrg\Mail\MailAttachment
{
    private $mailbox;
    private $filename;
    private $bytes;
    private $number;
    private $mnumber;
    private $encoding;
    private $stream;
    private $mime_type;

    public function __construct($data)
    {
        $this->mailbox  = $data['mailbox'];
        $this->filename = $data['filename'];
        $this->bytes    = $data['bytes'];
        $this->number   = $data['number'];
        $this->mnumber  = $data['mnumber'];
        $this->encoding = $data['encoding'];
        $this->stream    = null;
        $this->mime_type = null;
    }

    public function __destruct()
    {
        if (!is_null($this->stream) && get_resource_type($this->stream) == 'stream') {
            fclose($this->stream);
        }
    }

    public function mimeType(): string
    {
        if (is_null($this->mime_type)) {
            $this->mime_type = ReportFile::getMimeType($this->filename, $this->datastream());
        }
        return $this->mime_type;
    }

    public function size(): int
    {
        return $this->bytes;
    }

    public function filename()
    {
        return $this->filename;
    }

    public function datastream()
    {
        if (is_null($this->stream)) {
            $this->stream = fopen('php://temp', 'r+');
            fwrite($this->stream, $this->toString());
        }
        if (stream_get_meta_data($this->stream)['seekable'] ?? false) {
            rewind($this->stream);
        }
        return $this->stream;
    }

    private function fetchBody()
    {
        return imap_fetchbody($this->mailbox->connection(), $this->mnumber, strval($this->number), FT_PEEK | FT_UID);
    }

    private function toString()
    {
        switch ($this->encoding) {
            case ENC7BIT:
            case ENC8BIT:
            case ENCBINARY:
                return $this->fetchBody();
            case ENCBASE64:
                return base64_decode($this->fetchBody());
            case ENCQUOTEDPRINTABLE:
                return imap_qprint($this->fetchBody());
        }
        throw new SoftException('Encoding failed: Unknown encoding');
    }
}
