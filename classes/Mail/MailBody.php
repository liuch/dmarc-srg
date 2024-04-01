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

/**
 * The class is designed to easily create multipart/alternative message bodies.
 */
class MailBody
{
    private $boundary = null;

    /**
     * Plain text message body as an array of strings
     *
     * @var null|array
     */
    public $text = null;

    /**
     * HTML text message body as an array of strings
     *
     * @var null|array
     */
    public $html = null;

    /**
     * Return Content-Type header value for the whole message
     *
     * @return string
     */
    public function contentType(): string
    {
        if ($this->boundary()) {
            $ctype = 'multipart/alternative; boundary="' . $this->boundary() . '"';
        } else {
            if (!is_null($this->html)) {
                $ctype = 'text/html';
            } else {
                $ctype = 'text/plain';
            }
            $ctype .= '; charset=utf-8';
        }
        return $ctype;
    }

    /**
     * Returns all the message parts with required headers as an array of strings
     *
     * @return array
     */
    public function content(): array
    {
        $content = [];
        if ($this->text) {
            $this->addBodyPart('text', $this->text, $content);
        }
        if ($this->html) {
            $this->addBodyPart('html', $this->html, $content);
        }
        return $content;
    }

    /**
     * Generates a boundary string of the message. If the body has only one part of the content
     * it returns null
     *
     * @return string|null
     */
    private function boundary()
    {
        if (!$this->boundary) {
            if ($this->text && $this->html) {
                $this->boundary = '==========' . sha1(uniqid()) . '=====';
            }
        }
        return $this->boundary;
    }

    /**
     * Adds the specified part of the content to the array passed as the third parameter
     * with the required headers.
     *
     * @param string $type    Type of the content to add
     * @param array  $part    Part of the content to add
     * @param array  $content Where the data with headers should be added
     *
     * @return void
     */
    private function addBodyPart(string $type, array &$part, array &$content): void
    {
        if ($this->boundary()) {
            $content[] = '--' . $this->boundary();
            switch ($type) {
                case 'text':
                default:
                    $ctype = 'text/plain';
                    break;
                case 'html':
                    $ctype = 'text/html';
                    break;
            }
            $content[] = 'Content-Type: ' . $ctype . '; charset=utf-8';
            $content[] = 'Content-Transfer-Encoding: 7bit';
            $content[] = '';
        }
        foreach ($part as $row) {
            $content[] = $row;
        }
        unset($part);
    }
}
