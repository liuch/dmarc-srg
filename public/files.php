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

use Liuch\DmarcSrg\Users\User;
use Liuch\DmarcSrg\Mail\MailBoxes;
use Liuch\DmarcSrg\Report\ReportFetcher;
use Liuch\DmarcSrg\Sources\MailboxSource;
use Liuch\DmarcSrg\Sources\DirectorySource;
use Liuch\DmarcSrg\Sources\UploadedFilesSource;
use Liuch\DmarcSrg\Sources\RemoteFilesystemSource;
use Liuch\DmarcSrg\Directories\DirectoryList;
use Liuch\DmarcSrg\Exception\AuthException;
use Liuch\DmarcSrg\Exception\RuntimeException;
use Liuch\DmarcSrg\Exception\ForbiddenException;
use Liuch\DmarcSrg\RemoteFilesystems\RemoteFilesystemList;

require realpath(__DIR__ . '/..') . '/init.php';

if (Core::method() == 'GET') {
    if (!Core::isJson()) {
        Core::instance()->sendHtml();
        return;
    }

    try {
        $auth = Core::instance()->auth();

        $auth->isAllowed(User::LEVEL_MANAGER);
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

        try {
            $auth->isAllowed(User::LEVEL_ADMIN);
            $res['mailboxes'] = array_map(function ($mb) {
                return [
                    'id'      => $mb['id'],
                    'name'    => $mb['name'],
                    'host'    => $mb['host'],
                    'mailbox' => $mb['mailbox']
                ];
            }, (new MailBoxes())->list());
            $dmap = [
                [ 'directories', (new DirectoryList())->list() ],
                [ 'remotefs', (new RemoteFilesystemList(true))->list() ]
            ];
            foreach ($dmap as $it) {
                $dirs = [];
                foreach ($it[1] as $dir) {
                    $da = $dir->toArray();
                    try {
                        $da['files'] = $dir->count();
                    } catch (RuntimeException $e) {
                        $da['error'] = true;
                    }
                    $dirs[] = $da;
                }
                $res[$it[0]] = $dirs;
            }
        } catch (ForbiddenException $e) {
        }

        Core::sendJson($res);
    } catch (RuntimeException $e) {
        Core::sendJson(ErrorHandler::exceptionResult($e));
    }
    return;
}

if (Core::method() == 'POST') {
    try {
        $data = Core::getJsonData();
        if ($data) {
            Core::instance()->auth()->isAllowed(User::LEVEL_ADMIN);

            if (isset($data['cmd'])) {
                $cmd_id = array_search($data['cmd'], [ 'load-mailbox', 'load-directory', 'load-remotefs' ]);
                if ($cmd_id !== false) {
                    if (isset($data['ids']) && gettype($data['ids']) === 'array' && count($data['ids']) > 0) {
                        $done = [];
                        $slst = [];
                        switch ($cmd_id) {
                            case 0:
                                $list = new MailBoxes();
                                break;
                            case 1:
                                $list = new DirectoryList();
                                break;
                            case 2:
                                $list = new RemoteFilesystemList(true);
                                break;
                            default:
                                $list = [];
                        }
                        foreach ($data['ids'] as $id) {
                            $dir_id = gettype($id) === 'integer' ? $id : -1;
                            if (!in_array($id, $done, true)) {
                                $done[] = $id;
                                if ($cmd_id === 0) {
                                    $slst[] = new MailboxSource($list->mailbox($dir_id));
                                } elseif ($cmd_id === 1) {
                                    $slst[] = new DirectorySource($list->directory($dir_id));
                                } elseif ($cmd_id === 2) {
                                    $slst[] = new RemoteFilesystemSource($list->filesystem($dir_id));
                                }
                            }
                        }
                        if (count($slst) > 0) {
                            $results = [];
                            foreach ($slst as $sou) {
                                $sres = (new ReportFetcher($sou))->fetch();
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
            Core::instance()->auth()->isAllowed(User::LEVEL_MANAGER);

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
