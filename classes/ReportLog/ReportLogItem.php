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

use Liuch\DmarcSrg\DateTime;
use Liuch\DmarcSrg\Sources\Source;
use Liuch\DmarcSrg\Database\Database;

class ReportLogItem
{
    private $id = null;
    private $domain = null;
    private $external_id = null;
    private $event_time = null;
    private $filename = null;
    private $source = 0;
    private $success = false;
    private $message = null;

    private function __construct($source, $filename)
    {
        if (!is_null($source)) {
            if (gettype($source) !== 'integer' || $source <= 0) {
                throw new \Exception('Invalid parameter passed', -1);
            }
        }
        $this->source = $source;
        $this->filename = gettype($filename) == 'string' ? $filename : null;
    }

    public static function success(int $source, $report, $filename, $message)
    {
        $li = new ReportLogItem($source, $filename);
        $li->success = true;
        $rdata = $report->get();
        $li->domain = $rdata['domain'];
        $li->external_id = $rdata['external_id'];
        $li->message = $message;
        return $li;
    }

    public static function failed(int $source, $report, $filename, $message)
    {
        $li = new ReportLogItem($source, $filename);
        $li->success = false;
        if (!is_null($report)) {
            $rdata = $report->get();
            $li->domain = $rdata['domain'];
            $li->external_id = $rdata['external_id'];
        } else {
            $li->domain = null;
            $li->external_id = null;
        }
        $li->message = $message;
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
        $li = new ReportLogItem(null, null);
        $li->id = $id;

        $db = Database::connection();
        $st = $db->prepare(
            'SELECT `domain`, `external_id`, `event_time`, `filename`, `source`, `success`, `message` FROM `'
            . Database::tablePrefix('reportlog') . '` WHERE `id` = ?'
        );
        $st->bindValue(1, $id, \PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(\PDO::FETCH_NUM);
        if (!$row) {
            throw new \Exception('The log item is not found', -1);
        }
        $li->domain      = $row[0];
        $li->external_id = $row[1];
        $li->event_time  = new DateTime($row[2]);
        $li->filename    = $row[3];
        $li->source      = intval($row[4]);
        $li->success     = boolval($row[5]);
        $li->message     = $row[6];
        $st->closeCursor();
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
        return [
            'id'         => $this->id,
            'domain'     => $this->domain,
            'report_id'  => $this->external_id,
            'event_time' => $this->event_time,
            'filename'   => $this->filename,
            'source'     => static::sourceToString($this->source),
            'success'    => $this->success,
            'message'    => $this->message
        ];
    }

    public function save()
    {
        $db = Database::connection();
        $st = null;
        if (is_null($this->id)) {
            $st = $db->prepare(
                'INSERT INTO `' . Database::tablePrefix('reportlog')
                . '` (`domain`, `external_id`, `event_time`, `filename`, `source`, `success`, `message`)'
                . ' VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
        } else {
            $st = $db->prepare(
                'UPDATE `' . Database::tablePrefix('reportlog')
                . '` SET `domain` = ?, `external_id` = ?, `event_time` = ?, `filename` = ?,'
                . ' `source` = ?, `success` = ?, `message` = ? WHERE `id` = ?'
            );
            $st->bindValue(8, $this->id, \PDO::PARAM_INT);
        }
        $ts = $this->event_time;
        if (!$ts) {
            $ts = new DateTime();
        }
        $st->bindValue(1, $this->domain, \PDO::PARAM_STR);
        $st->bindValue(2, $this->external_id, \PDO::PARAM_STR);
        $st->bindValue(3, $ts->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
        $st->bindValue(4, $this->filename, \PDO::PARAM_STR);
        $st->bindValue(5, $this->source, \PDO::PARAM_INT);
        $st->bindValue(6, $this->success, \PDO::PARAM_BOOL);
        $st->bindValue(7, $this->message, \PDO::PARAM_STR);
        $st->execute();
        if (is_null($this->id)) {
            $this->id = intval($db->lastInsertId());
        }
        $st->closeCursor();
        $this->event_time = $ts;
    }
}
