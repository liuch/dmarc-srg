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

use Exception;
use Liuch\DmarcSrg\Report\Report;
use Liuch\DmarcSrg\ReportFile\ReportFile;
use Liuch\DmarcSrg\ReportLog\ReportLogItem;

require 'init.php';

if (Core::method() == 'GET') {
    if (Core::isJson()) {
        try {
            Core::auth()->isAllowed();
            throw new Exception('Under construction', -1);
        } catch (Exception $e) {
            Core::sendJson(
                [
                    'error_code' => $e->getCode(),
                    'message'    => $e->getMessage()
                ]
            );
        }
    } else {
        Core::sendHtml();
    }
    return;
}

if (Core::method() == 'POST') {
    if (isset($_FILES['report_file']) && isset($_POST['cmd']) && $_POST['cmd'] === 'upload-report') {
        try {
            Core::auth()->isAllowed();
        } catch (Exception $e) {
            Core::sendJson(
                [
                    'error_code' => $e->getCode(),
                    'message'    => $e->getMessage()
                ]
            );
            return;
        }
        $results = [];
        $files   = &$_FILES['report_file'];
        for ($i = 0; $i < count($files['name']); ++$i) {
            $log = null;
            $rep = null;
            try {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                    throw new Exception(
                        'Failed to upload a report file',
                        $files['error'][$i]
                    );
                }

                $realfname = $files['name'][$i];
                $tempfname  = $files['tmp_name'][$i];
                if (!is_uploaded_file($tempfname)) {
                    throw new Exception('Possible file upload attack', -11);
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
            } catch (Exception $e) {
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
                $log->save();
                unset($rep);
                unset($rf);
            }
        }
        unset($files);
        $rcnt = count($results);
        $res = null;
        if ($rcnt == 1) {
            $res = $results[0];
        } else {
            $res = [];
            $lcnt = $rcnt;
            for ($i = 0; $i < $rcnt; ++$i) {
                if (isset($results[$i]['error_code']) && $results[$i]['error_code'] !== 0) {
                    $lcnt -= 1;
                }
            }
            if ($lcnt == $rcnt) {
                $res['error_code'] = 0;
                $res['message'] = strval($rcnt) . ' report files have been loaded successfully';
            } else {
                $res['error_code'] = -1;
                if ($lcnt > 0) {
                    $res['message'] = "Only {$lcnt} of the {$rcnt} report files have been loaded";
                } else {
                    $res['message'] = 'None of the report file has been loaded';
                }
            }
            $res['results'] = $results;
        }
        Core::sendJson($res);
        return;
    }
}

Core::sendBad();

