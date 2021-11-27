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

use Liuch\DmarcSrg\Report\Report;
use Liuch\DmarcSrg\Report\ReportFetcher;
use Liuch\DmarcSrg\ReportFile\ReportFile;
use Liuch\DmarcSrg\Sources\DirectorySource;
use Liuch\DmarcSrg\ReportLog\ReportLogItem;
use Liuch\DmarcSrg\Directories\DirectoryList;

require 'init.php';

if (Core::method() == 'GET') {
    if (!Core::isJson()) {
        Core::sendHtml();
        return;
    }

    try {
        Core::auth()->isAllowed();
        $res = [];
        $up_max = ini_get('max_file_uploads');
        if ($up_max) {
            $res['upload_max_file_count'] = intval($up_max);
        }
        $up_size = ini_get('upload_max_filesize');
        if ($up_size) {
            if (!empty($up_size)) {
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
        }
        $dirs = [];
        foreach ((new DirectoryList())->list() as $dir) {
            $da = $dir->toArray();
            try {
                $files = $dir->count();
            } catch (\Exception $e) {
                $files = -1;
            }
            $da['files'] = $files;
            $dirs[] = $da;
        }
        $res['directories'] = $dirs;
        Core::sendJson($res);
    } catch (\Exception $e) {
        Core::sendJson(
            [
                'error_code' => $e->getCode(),
                'message'    => $e->getMessage()
            ]
        );
    }
    return;
}

if (Core::method() == 'POST') {
    try {
        Core::auth()->isAllowed();
    } catch (\Exception $e) {
        Core::sendJson(
            [
                'error_code' => $e->getCode(),
                'message'    => $e->getMessage()
            ]
        );
        return;
    }

    $data = Core::getJsonData();
    if ($data) {
        if (isset($data['cmd'])) {
            if ($data['cmd'] === 'load-directory') {
                if (isset($data['ids']) && gettype($data['ids']) === 'array' && count($data['ids']) > 0) {
                    try {
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
                    } catch (\Exception $e) {
                        $err_code = $e->getCode();
                        if ($err_code === 0) {
                            $err_code = -1;
                        }
                        Core::sendJson([
                            'error_code' => $err_code,
                            'message'    => $e->getMessage()
                        ]);
                        return;
                    }
                }
            }
        }
    } elseif (isset($_FILES['report_file']) && isset($_POST['cmd']) && $_POST['cmd'] === 'upload-report') {
        $results = [];
        $files   = &$_FILES['report_file'];
        for ($i = 0; $i < count($files['name']); ++$i) {
            $log = null;
            $rep = null;
            try {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                    throw new \Exception(
                        'Failed to upload a report file',
                        $files['error'][$i]
                    );
                }

                $realfname = $files['name'][$i];
                $tempfname  = $files['tmp_name'][$i];
                if (!is_uploaded_file($tempfname)) {
                    throw new \Exception('Possible file upload attack', -11);
                }

                $rf = ReportFile::fromFile($tempfname, $realfname, false);
                $rep = Report::fromXmlFile($rf->datastream());
                $results[] = $rep->save($realfname);

                $log = ReportLogItem::success(
                    ReportLogItem::SOURCE_UPLOADED_FILE,
                    $rep,
                    $realfname,
                    null
                );
            } catch (\Exception $e) {
                $results[] = [
                    'error_code' => $e->getCode(),
                    'message'    => $e->getMessage()
                ];
                $log = ReportLogItem::failed(
                    ReportLogItem::SOURCE_UPLOADED_FILE,
                    $rep,
                    $realfname,
                    $e->getMessage()
                );
            } finally {
                if ($log) {
                    $log->save();
                }
                unset($rep);
                unset($rf);
            }
        }
        unset($files);
        Core::sendJson(ReportFetcher::makeSummaryResult($results));
        return;
    }
}

Core::sendBad();
