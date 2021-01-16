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

use PDO;
use Exception;
use Liuch\DmarcSrg\Database\Database;

class ReportLogItem
{
    public const SOURCE_UPLOADED_FILE = 1;
    public const SOURCE_EMAIL = 2;
    private const SOURCE_LAST_ = 3;

    private $id = null;
    private $domain = null;
    private $external_id = null;
    private $event_time = null;
    private $filename = null;
    private $source = 0;
    private $success = false;
    private $message = null;

    private function __construct(int $source, $filename)
    {
        if ($source <= 0 || $source >= self::SOURCE_LAST_) {
            throw new Exception('Invalid parameter passed', -1);
        }
        $this->filename = gettype($filename) == "string" ? $filename : null;
        $this->source = $source;
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

    public function save()
    {
        $db = Database::connection();
        $st = null;
        if (is_null($this->id)) {
            $st = $db->prepare('INSERT INTO `reportlog` (`domain`, `external_id`, `event_time`, `filename`, `source`, `success`, `message`) VALUES (?, ?, FROM_UNIXTIME(?), ?, ?, ?, ?)');
        } else {
            $st = $db->prepare('UPDATE `reportlog` SET `domain` = ?, `external_id` = ?, `event_time` = FROM_UNIXTIME(?), `filename` = ?, `source` = ?, `success` = ?, `message` = ? WHERE `id` = ?');
        }
        $st->bindValue(1, $this->domain, PDO::PARAM_STR);
        $st->bindValue(2, $this->external_id, PDO::PARAM_STR);
        $st->bindValue(3, !is_null($this->event_time) ? $this->event_time : time(), PDO::PARAM_INT);
        $st->bindValue(4, $this->filename, PDO::PARAM_STR);
        $st->bindValue(5, $this->source, PDO::PARAM_INT);
        $st->bindValue(6, $this->success, PDO::PARAM_BOOL);
        $st->bindValue(7, $this->message, PDO::PARAM_STR);
        if (!is_null($this->id)) {
            $st->bindValue(8, $this->id, PDO::PARAM_INT);
        }
        $st->execute();
        if (is_null($this->id)) {
            $this->id = $db->lastInsertId();
        }
        $st->closeCursor();
    }
}

