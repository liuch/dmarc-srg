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

    private function __construct($filename, $type, $fd)
    {
        $this->filename = $filename;
        $this->type = $type;
        switch ($this->type) {
            case 'gz':
                $this->fd = $fd;
                break;
            case 'zip':
                if ($fd) {
                    $tmpfname = tempnam(sys_get_temp_dir(), 'dmarc_');
                    if ($tmpfname === false) {
                        throw new \Exception('Failed to create a temporary file', -1);
                    }
                    rewind($fd);
                    if (file_put_contents($tmpfname, $fd) === false) {
                        throw new \Exception('Failed to copy data to a temporary file', -1);
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
            if ($this->type === 'gz' && !$this->filepath) {
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

    public static function fromFile($filepath, $filename = null, $remove = false)
    {
        if (!is_file($filepath)) {
            throw new \Exception('ReportFile: it is not a file', -1);
        }
        $fname = $filename ? basename($filename) : basename($filepath);
        $type = pathinfo($fname, PATHINFO_EXTENSION);
        $rf = new ReportFile($fname, $type, null);
        $rf->remove = $remove;
        $rf->filepath = $filepath;
        return $rf;
    }

    public static function fromStream($fd, $filename)
    {
        $type = pathinfo($filename, PATHINFO_EXTENSION);
        $rf = new ReportFile($filename, $type, $fd);
        return $rf;
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
                case 'zip':
                    $this->zip = new \ZipArchive();
                    $this->zip->open($this->filepath);
                    if ($this->zip->count() !== 1) {
                        throw new \Exception('The archive must have only one file in it', -1);
                    }
                    $zfn = $this->zip->getNameIndex(0);
                    if ($zfn !== pathinfo($zfn, PATHINFO_BASENAME)) {
                        throw new \Exception('There must not be any directories in the archive', -1);
                    }
                    $fd = $this->zip->getStream($zfn);
                    break;
                default:
                    // gzopen() can be used to read a file which is not in gzip format;
                    // in this case gzread() will directly read from the file without decompression.
                    $fd = @gzopen($this->filepath, 'r');
                    break;
            }
            if (!$fd) {
                throw new \Exception('Failed to open a report file', -1);
            }
            $this->fd = $fd;
        }
        if ($this->type === 'gz' && !$this->filepath) {
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
