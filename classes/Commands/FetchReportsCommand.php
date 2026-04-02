<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2026 Aleksey Andreev (liuch)
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
 *
 * =========================
 *
 * This file contains FetchReportsCommand class
 *
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Commands;

use Liuch\DmarcSrg\Core;
use Liuch\DmarcSrg\Requests\Request;
use Liuch\DmarcSrg\Report\FetchReportsHandler;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\ValidationException;

/**
 * This class is designed to handle the commands required to fetch incoming DMARC reports
 */
final class FetchReportsCommand extends Command
{
    /**
     * Runs fetching incoming reports from multiple sources
     *
     * @param Request $request
     *
     * @return int Error code or 0 if successful
     */
    protected function doExecute(Request $request): int
    {
        try {
            $type = $request->getProperty('type');
            if (!$type) {
                $data = $request->getData();
                $type = $data['type'] ?? null;
                $ids  = $data['ids'] ?? null;
            } else {
                $ids  = $request->getProperty('ids');
            }

            if (gettype($type) !== 'string') {
                throw new ValidationException('The `type` parameter is invalid');
            }

            if (!is_null($ids)) {
                $idsType = gettype($ids);
                if ($idsType === 'string') {
                    $ids = explode(',', $ids);
                } elseif ($idsType !== 'array') {
                    throw new ValidationException('The `ids` parameter is invalid');
                }
            }

            $handler = new FetchReportsHandler(Core::instance());
            $handler->handle($type, $ids);
            $request->setData($handler->getSummaryResult());
        } catch (SoftException $e) {
            $request->setMessage($e->getMessage());
            return $e->getCode() ?: -1;
        } catch (ValidationException $e) {
            $request->setMessage($e->getMessage());
            return $e->getCode() ?: -1;
        }
        return 0;
    }
}
