<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2020-2026 Aleksey Andreev (liuch)
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
use Liuch\DmarcSrg\Requests\HttpRequest;
use Liuch\DmarcSrg\Report\ReportFetcher;
use Liuch\DmarcSrg\Views\JsonViewComponent;
use Liuch\DmarcSrg\Directories\DirectoryList;
use Liuch\DmarcSrg\Sources\UploadedFilesSource;
use Liuch\DmarcSrg\Exception\RuntimeException;
use Liuch\DmarcSrg\Exception\ForbiddenException;
use Liuch\DmarcSrg\Exception\ValidationException;
use Liuch\DmarcSrg\Commands\FetchReportsCommand;
use Liuch\DmarcSrg\RemoteFilesystems\RemoteFilesystemList;

require realpath(__DIR__ . '/..') . '/init.php';

$request = new HttpRequest();

if ($request->getMethod() === 'GET') {
    $core = Core::instance();
    $auth = $core->auth();

    try {
        if ($request->hasProperty('token')) {
            $auth->isTokenValid('fetcher', $request->getProperty('token'));

            if (!$request->emptyProperty('type')) {
                if ($core->checkAccessFrequency('fetcher', 5*60)) {
                    $command = new FetchReportsCommand();
                    $command->execute($request);
                } else {
                    throw new ValidationException('Too many requests');
                }
            } else {
                $request->setErrorCode(-1);
                $request->setMessage('The `type` parameter must be specified');
            }

            $view = new JsonViewComponent();
            $view->render($request);

            return;
        }

        if (!Core::isJson()) {
            $core->sendHtml();
            return;
        }

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
                [ 'directories', (new DirectoryList())->list() ]
            ];
            try {
                $core->checkDependencies('flyfs');
                $dmap[] = [ 'remotefs', (new RemoteFilesystemList(true))->list() ];
            } catch (RuntimeException $e) {
            }
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
    } catch (ValidationException $e) {
        $errorCode = $e->getCode();
        Core::sendJson([
            'error_code' => $errorCode ? $errorCode : -1,
            'message'    => $e->getMessage()
        ]);
    }
    return;
}

if ($request->getMethod() === 'POST') {
    try {
        $core = Core::instance();
        if ($request->hasJsonData()) {
            $core->auth()->isAllowed(User::LEVEL_ADMIN);

            $data = $request->getData();
            if (isset($data['cmd'])) {
                if (str_starts_with($data['cmd'], 'load-')) {
                    if (isset($data['ids']) && gettype($data['ids']) === 'array' && count($data['ids']) > 0) {
                        $data['type'] = substr($data['cmd'], 5);
                        $request->setData($data);

                        $command = new FetchReportsCommand();
                        $command->execute($request);

                        $view = new JsonViewComponent();
                        $view->render($request);

                        return;
                    }
                }
            }
        } elseif (isset($_FILES['report_file']) && isset($_POST['cmd']) && $_POST['cmd'] === 'upload-report') {
            $core->auth()->isAllowed(User::LEVEL_MANAGER);
            $core->checkDependencies('xml,zip');

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
