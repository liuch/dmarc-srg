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
use Liuch\DmarcSrg\ReportFile\ReportFile;

class MailAttachment
{
    private $conn;
    private $filename;
    private $bytes;
    private $number;
    private $mnumber;

    public function __construct($conn, $params)
    {
        $this->conn     = $conn;
        $this->filename = $params['filename'];
        $this->bytes    = $params['bytes'];
        $this->number   = $params['number'];
        $this->mnumber  = $params['mnumber'];
        $this->encoding = $params['encoding'];
        $this->stream    = null;
        $this->mime_type = null;
    }

    public function __destruct()
    {
        if (!is_null($this->stream) && get_resource_type($this->stream) == 'stream') {
            fclose($this->stream);
        }
    }

    public function mimeType()
    {
        return is_null($this->mime_type) ?
            ($this->mime_type = ReportFile::getMimeType($this->filename, $this->datastream())) :
            $this->mime_type;
    }

    public function size()
    {
        return $this->bytes;
    }

    public function filename()
    {
        return $this->filename;
    }

    public function extension()
    {
        return pathinfo($this->filename, PATHINFO_EXTENSION);
    }

    public function datastream()
    {
        if (is_null($this->stream)) {
            $this->stream = fopen('php://temp', 'r+');
            fwrite($this->stream, $this->tostring());
        }
        rewind($this->stream);
        return $this->stream;
    }

    private function fetchBody()
    {
        return imap_fetchbody($this->conn, $this->mnumber, strval($this->number), FT_PEEK);
    }

    private function tostring()
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
        throw new Exception('Encoding failed: Unknown encoding', -1);
    }
}
