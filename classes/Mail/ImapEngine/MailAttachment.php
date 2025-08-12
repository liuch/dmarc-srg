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

use Liuch\DmarcSrg\ReportFile\ReportFile;

class MailAttachment extends \Liuch\DmarcSrg\Mail\MailAttachment
{
    private $bytes      = -1;
    private $stream     = null;
    private $mime_type  = null;
    private $attachment;

    public function __construct($data)
    {
        $this->attachment = $data;
    }

    public function __destruct()
    {
        if (!is_null($this->stream) && get_resource_type($this->stream) === 'stream') {
            fclose($this->stream);
        }
    }

    public function mimeType(): string
    {
        if (is_null($this->mime_type)) {
            $this->mime_type = $this->attachment->contentType();
            if ($this->mime_type === 'application/octet-stream') {
                $this->mime_type = ReportFile::getMimeType($this->attachment->filename(), $this->datastream());
            }
        }
        return $this->mime_type;
    }

    public function size(): int
    {
        if ($this->bytes === -1) {
            $bytes = $this->attachment->contentStream()->getSize();
            if (is_null($bytes)) {
                $this->makeTemporaryFile();
                $stat = fstat($this->stream);
                $bytes = $stat['size'] ?? -1;
            }
            $this->bytes = $bytes;
        }
        return $this->bytes;
    }

    public function filename()
    {
        return $this->attachment->filename();
    }

    public function datastream()
    {
        if (is_null($this->stream)) {
            $this->makeTemporaryFile();
        }
        if (stream_get_meta_data($this->stream)['seekable'] ?? false) {
            rewind($this->stream);
        }
        return $this->stream;
    }

    private function makeTemporaryFile()
    {
        $this->stream = fopen('php://temp', 'r+');
        fwrite($this->stream, $this->attachment->contentStream());
    }
}
