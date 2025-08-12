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

namespace Liuch\DmarcSrg\Mail;

use Liuch\DmarcSrg\Exception\SoftException;

abstract class MailMessage
{
    protected $attachment = null;

    abstract public function __construct($data);

    abstract public function overview(): array;

    abstract public function markSeen(): void;

    public function validate()
    {
        $acnt = $this->attachmentCount();
        if ($acnt !== 1) {
            throw new SoftException("Attachment count is not valid ({$acnt})");
        }

        $this->ensureAttachment();

        $bytes = $this->attachment->size();
        if ($bytes === -1) {
            throw new SoftException("Failed to get attached file size. Wrong message format?");
        }
        if ($bytes < 50 || $bytes > 1 * 1024 * 1024) {
            throw new SoftException("Attachment file size is not valid ({$bytes} bytes)");
        }

        $mime_type = $this->attachment->mimeType();
        if (!in_array($mime_type, [ 'application/zip', 'application/gzip', 'application/x-gzip', 'text/xml' ])) {
            throw new SoftException("Attachment file type is not valid ({$mime_type})");
        }
    }

    abstract public function attachment();

    abstract public function move(string $folder): void;

    abstract public function delete(): void;

    abstract protected function attachmentCount(): int;

    abstract protected function ensureAttachment(): void;
}
