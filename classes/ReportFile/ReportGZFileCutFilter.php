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

namespace Liuch\DmarcSrg\ReportFile;

use php_user_filter;

class ReportGZFileCutFilter extends php_user_filter
{
    private $head        = true;
    private $header_data = '';
    private $tail_data   = '';

    public function filter($in, $out, &$consumed, $closing): int
    {
        $b_cnt = 0;
        while ($bucket = stream_bucket_make_writeable($in)) {
            $consumed += $bucket->datalen;
            $data = null;
            if ($this->head) {
                $data = $this->skipGzHeader($bucket->data);
            } else {
                $data = $bucket->data;
            }
            $data = $this->cutGzTail($data);
            if (strlen($data) > 0) {
                $bucket->data = $data;
                stream_bucket_append($out, $bucket);
                $b_cnt += 1;
            }
        }
        return ($b_cnt > 0) ? PSFS_PASS_ON : PSFS_FEED_ME;
    }

    private function skipGzHeader($data)
    {
        // https://tools.ietf.org/html/rfc1952
        $this->header_data .= $data;
        $len = strlen($this->header_data);
        if ($len < 10) { // minimal gz header
            return '';
        }

        $pos = 10;
        $flags = ord($this->header_data[3]);
        if ($flags & 4) { // FLG.FEXTRA
            $pos += (ord($this->header_data[$pos + 1]) | (ord($this->header_data[$pos + 2]) << 8)) + 2;
            if ($pos > $len) {
                return '';
            }
        }
        if ($flags & 8) { // FLG.FNAME
            $pos = $this->skipZeroTerminatedString($this->header_data, $len, $pos);
            if ($pos > $len) {
                return '';
            }
        }
        if ($flags & 16) { // FLG.FCOMMENT
            $pos = $this->skipZeroTerminatedString($this->header_data, $len, $pos);
            if ($pos > $len) {
                return '';
            }
        }
        if ($flags & 2) { // FLG.FHCRC
            $pos += 2;
            if ($pos > $len) {
                return '';
            }
        }
        $res = substr($this->header_data, $pos);
        $this->head = false;
        $this->header_data = '';
        return $res;
    }

    private function cutGzTail($data)
    {
        $res = $this->tail_data . $data;
        $this->tail_data = substr($res, -8);
        if (strlen($res) <= 8) {
            return '';
        }
        return substr($res, 0, -8);
    }

    private function skipZeroTerminatedString($str, $len, $pos)
    {
        for ($i = $pos; $i < $len; ++$i) {
            if ($str[$i] === "\0") {
                return $i + 1;
            }
        }
        return $len + 1;
    }
}
