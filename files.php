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

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\ErrorHandler;
use Liuch\DmarcSrg\Report\ReportFetcher;
use Liuch\DmarcSrg\Sources\DirectorySource;
use Liuch\DmarcSrg\Sources\UploadedFilesSource;
use Liuch\DmarcSrg\Directories\DirectoryList;
use Liuch\DmarcSrg\Exception\RuntimeException;

require 'init.php';

if (Core::method() == 'GET') {
    if (!Core::isJson()) {
        Core::instance()->sendHtml();
        return;
    }

    try {
        Core::instance()->auth()->isAllowed();

        $res = [];
        $up_max = ini_get('max_file_uploads');
        if ($up_max) {
            $res['upload_max_file_count'] = intval($up_max);
        }
        $up_size = ini_get('upload_max_filesize');
        if ($up_size) {
            $ch = strtolower($up_size[strlen($up_size) - 1]);
            $up_size = intval($up_size);
            switch ($ch) {
                case 'g':
                    $up_size *= 1024;
                    // no break
                case 'm':
                    $up_size *= 1024;
                    // no break
                case 'k':
                    $up_size *= 1024;
                    // no break
            }
            $res['upload_max_file_size'] = $up_size;
        }
        $dirs = [];
        foreach ((new DirectoryList())->list() as $dir) {
            $da = $dir->toArray();
            try {
                $files = $dir->count();
            } catch (RuntimeException $e) {
                $files = -1;
            }
            $da['files'] = $files;
            $dirs[] = $da;
        }
        $res['directories'] = $dirs;
        Core::sendJson($res);
    } catch (RuntimeException $e) {
        Core::sendJson(ErrorHandler::exceptionResult($e));
    }
    return;
}

if (Core::method() == 'POST') {
    try {
        Core::instance()->auth()->isAllowed();

        $data = Core::getJsonData();
        if ($data) {
            if (isset($data['cmd'])) {
                if ($data['cmd'] === 'load-directory') {
                    if (isset($data['ids']) && gettype($data['ids']) === 'array' && count($data['ids']) > 0) {
                        $done = [];
                        $dirs = [];
                        $list = new DirectoryList();
                        foreach ($data['ids'] as $id) {
                            $dir_id = gettype($id) === 'integer' ? $id : -1;
                            if (!in_array($id, $done, true)) {
                                $done[] = $id;
                                $dirs[] = $list->directory($dir_id);
                            }
                        }
                        if (count($dirs) > 0) {
                            $results = [];
                            foreach ($dirs as $dir) {
                                $sres = (new ReportFetcher(new DirectorySource($dir)))->fetch();
                                foreach ($sres as &$r) {
                                    $results[] = $r;
                                }
                                unset($r);
                            }
                            Core::sendJson(ReportFetcher::makeSummaryResult($results));
                            return;
                        }
                    }
                }
            }
        } elseif (isset($_FILES['report_file']) && isset($_POST['cmd']) && $_POST['cmd'] === 'upload-report') {
            $results = (new ReportFetcher(new UploadedFilesSource($_FILES['report_file'])))->fetch();
            Core::sendJson(ReportFetcher::makeSummaryResult($results));
            return;
        }
    } catch (RuntimeException $e) {
        Core::sendJson(ErrorHandler::exceptionResult($e));
        return;
    }
}

Core::sendBad();
