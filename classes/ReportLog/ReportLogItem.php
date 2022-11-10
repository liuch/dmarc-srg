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

namespace Liuch\DmarcSrg\ReportLog;

use Liuch\DmarcSrg\Core;
use Liuch\DmarcSrg\Sources\Source;
use Liuch\DmarcSrg\Exception\LogicException;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\DatabaseNotFoundException;

class ReportLogItem
{
    private $db   = null;
    private $data = [
        'id'          => null,
        'domain'      => null,
        'external_id' => null,
        'event_time'  => null,
        'filename'    => null,
        'source'      => 0,
        'success'     => false,
        'message'     => null
    ];

    private function __construct($source, $filename, $db)
    {
        if (!is_null($source)) {
            if (gettype($source) !== 'integer' || $source <= 0) {
                throw new LogicException('Invalid parameter passed');
            }
        }
        $this->data['source'] = $source;
        $this->data['filename'] = gettype($filename) == 'string' ? $filename : null;
        $this->db = $db ?? Core::instance()->database();
    }

    public static function success(int $source, $report, $filename, $message, $db = null)
    {
        $li = new ReportLogItem($source, $filename, $db);
        $li->data['success'] = true;
        $rdata = $report->get();
        $li->data['domain'] = $rdata['domain'];
        $li->data['external_id'] = $rdata['external_id'];
        $li->data['message'] = $message;
        return $li;
    }

    public static function failed(int $source, $report, $filename, $message, $db = null)
    {
        $li = new ReportLogItem($source, $filename, $db);
        $li->data['success'] = false;
        if (!is_null($report)) {
            $rdata = $report->get();
            $li->data['domain'] = $rdata['domain'];
            $li->data['external_id'] = $rdata['external_id'];
        } else {
            $li->data['domain'] = null;
            $li->data['external_id'] = null;
        }
        $li->data['message'] = $message;
        return $li;
    }

    /**
     * Returns an instance of ReportLogItem with the passed Id
     *
     * @param int $id an Id of item to return
     *
     * @return ReportLogItem an instance of ReportLogItem with the specified Id.
     */
    public static function byId(int $id)
    {
        $li = new ReportLogItem(null, null, null);
        $li->data['id'] = $id;
        try {
            $li->db->getMapper('report-log')->fetch($li->data);
        } catch (DatabaseNotFoundException $e) {
            throw new SoftException('The log item is not found');
        }
        return $li;
    }

    /**
     * Converts an integer source value to a string representation
     *
     * Returns a string with the source name or an empty string if the integer value is incorrect.
     *
     * @param int $source - an integer value to convert
     *
     * @return string A string value of the passed source
     */
    public static function sourceToString(int $source): string
    {
        switch ($source) {
            case Source::SOURCE_UPLOADED_FILE:
                return 'uploaded_file';
            case Source::SOURCE_MAILBOX:
                return 'email';
            case Source::SOURCE_DIRECTORY:
                return 'directory';
        }
        return '';
    }

    /**
     * Returns an array with log item data
     *
     * @return array Log item data
     */
    public function toArray(): array
    {
        $res = $this->data;
        $res['source'] = static::sourceToString($this->data['source']);
        return $res;
    }

    /**
     * Saves the report log item to the database
     *
     * @return void
     */
    public function save(): void
    {
        $this->db->getMapper('report-log')->save($this->data);
    }
}
