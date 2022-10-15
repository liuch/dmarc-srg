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

use Liuch\DmarcSrg\Exception\SoftException;

class ReportFile
{
    private $fd = null;
    private $zip = null;
    private $type = null;
    private $remove = false;
    private $filename = null;
    private $filepath = null;
    private $gzcutfilter = null;
    private $gzinflatefilter = null;
    private static $filters = [];
    private static $ext_mime_map = [
        'xml' => 'text/xml',
        'gz' => 'application/gzip',
        'zip' => 'application/zip'
    ];

    private function __construct($filename, $type = null, $fd = null, $remove = false, $filepath = null)
    {
        $this->filename = $filename;
        $this->type = $type ?? self::getMimeType($filename, $fd, $filepath);
        $this->remove = $remove;
        $this->filepath = $filepath;

        switch ($this->type) {
            case 'application/gzip':
                $this->fd = $fd;
                break;
            case 'application/zip':
                if ($fd) {
                    $tmpfname = tempnam(sys_get_temp_dir(), 'dmarc_');
                    if ($tmpfname === false) {
                        throw new SoftException('Failed to create a temporary file');
                    }
                    rewind($fd);
                    if (file_put_contents($tmpfname, $fd) === false) {
                        throw new SoftException('Failed to copy data to a temporary file');
                    }
                    $this->filepath = $tmpfname;
                    $this->remove = true;
                }
                break;
        }
    }

    public function __destruct()
    {
        if ($this->fd) {
            if ($this->type === 'application/gzip' && !$this->filepath) {
                $this->enableGzFilter(false);
            }
            gzclose($this->fd);
        }
        if ($this->zip) {
            $this->zip->close();
        }
        if ($this->remove && $this->filepath) {
            unlink($this->filepath);
        }
    }

    public static function getMimeType($filename, $fd = null, $filepath = null)
    {
        if (function_exists('mime_content_type')) {
            if ($fd && ($res = mime_content_type($fd))) {
                return $res;
            }
            if ($filepath && ($res = mime_content_type($filepath))) {
                return $res;
            }
        }

        $ext = pathinfo(basename($filename), PATHINFO_EXTENSION);
        return self::$ext_mime_map[$ext] ?? 'application/octet-stream';
    }

    public static function fromFile($filepath, $filename = null, $remove = false)
    {
        if (!is_file($filepath)) {
            throw new SoftException('ReportFile: it is not a file');
        }

        return new ReportFile(
            $filename ? basename($filename) : basename($filepath),
            null,
            null,
            $remove,
            $filepath
        );
    }

    public static function fromStream($fd, $filename, $type)
    {
        return new ReportFile($filename, $type, $fd);
    }

    public function filename()
    {
        return $this->filename;
    }

    public function datastream()
    {
        if (!$this->fd) {
            $fd = null;
            switch ($this->type) {
                case 'application/zip':
                    $this->zip = new \ZipArchive();
                    $this->zip->open($this->filepath);
                    if ($this->zip->count() !== 1) {
                        throw new SoftException('The archive must have only one file in it');
                    }
                    $zfn = $this->zip->getNameIndex(0);
                    if ($zfn !== pathinfo($zfn, PATHINFO_BASENAME)) {
                        throw new SoftException('There must not be any directories in the archive');
                    }
                    $fd = $this->zip->getStream($zfn);
                    break;
                default:
                    // gzopen() can be used to read a file which is not in gzip format;
                    // in this case gzread() will directly read from the file without decompression.
                    $fd = gzopen($this->filepath, 'r');
                    break;
            }
            if (!$fd) {
                throw new SoftException('Failed to open a report file');
            }
            $this->fd = $fd;
        }
        if ($this->type === 'application/gzip' && !$this->filepath) {
            ReportFile::ensureRegisterFilter(
                'report_gzfile_cut_filter',
                'Liuch\DmarcSrg\ReportFile\ReportGZFileCutFilter'
            );
            $this->enableGzFilter(true);
        }
        return $this->fd;
    }

    private static function ensureRegisterFilter($filtername, $classname)
    {
        if (!isset(ReportFile::$filters[$filtername])) {
            stream_filter_register($filtername, $classname);
            ReportFile::$filters[$filtername] = true;
        }
    }

    private function enableGzFilter($enable)
    {
        if ($enable) {
            if (!$this->gzcutfilter) {
                $this->gzcutfilter = stream_filter_append($this->fd, 'report_gzfile_cut_filter', STREAM_FILTER_READ);
                $this->gzinflatefilter = stream_filter_append($this->fd, 'zlib.inflate', STREAM_FILTER_READ);
            }
        } else {
            if ($this->gzcutfilter) {
                stream_filter_remove($this->gzinflatefilter);
                stream_filter_remove($this->gzcutfilter);
            }
        }
    }
}
