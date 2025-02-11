<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2020-2024 Aleksey Andreev (liuch)
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

namespace Liuch\DmarcSrg\Report;

use Liuch\DmarcSrg\Core;
use Liuch\DmarcSrg\Domains\Domain;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\DatabaseNotFoundException;

class Report
{
    private $db   = null;
    private $data = null;

    public function __construct($data, $db = null)
    {
        if (is_array($data)) {
            $domain = $data['domain'];
            $data = ReportData::fromArray($data);
        } else {
            $domain = $data->domain;
        }
        if ($domain instanceof Domain) {
            $data->domain = $domain->fqdn();
        }
        $this->data = $data;
        $this->db   = $db ?? Core::instance()->database();
    }

    public static function fromXmlFile($fd)
    {
        $data = ReportData::fromXmlFile($fd);
        if (!$data->isValid()) {
            throw new SoftException('Incorrect or incomplete report data');
        }
        return new Report($data);
    }

    public function fetch()
    {
        $this->prepareData(true);
        try {
            $this->db->getMapper('report')->fetch($this->data);
        } catch (DatabaseNotFoundException $e) {
            throw new SoftException('Report not found');
        }
    }

    public function save(string $real_fname)
    {
        $b_ts = $this->data->date['begin'];
        $e_ts = $this->data->date['end'];
        if (!$b_ts->getTimestamp() || !$e_ts->getTimestamp()
            || strlen($b_ts->format('Y')) !== 4 || strlen($e_ts->format('Y')) !== 4
        ) {
            throw new SoftException('Failed to add an incoming report: wrong date value');
        }
        if ($b_ts > $e_ts) {
            throw new SoftException('Failed to add an incoming report: start date is later than end date');
        }

        $this->db->getMapper('report')->save($this->data);
        return [ 'message' => 'The report is loaded successfully' ];
    }

    public function __get(string $name)
    {
        return $this->data->$name;
    }

    public function toArray(): array
    {
        return $this->data->toArray();
    }

    public function set($name, $value)
    {
        $this->prepareData(false);
        $this->db->getMapper('report')->setProperty($this->data, $name, $value);
        return [ 'message' => 'Ok' ];
    }

    /**
     * Checks and prepares report data for queries
     *
     * @param bool $replace If true, it leaves only the data required for the request
     *
     * @return void
     */
    private function prepareData(bool $replace): void
    {
        $data = [];
        $ne_f = false;
        foreach ([ 'domain', 'date', 'org_name', 'report_id'] as $fld) {
            if (empty($this->data->$fld)) {
                $ne_f = true;
                break;
            }
            if ($replace) {
                $data[$fld] = $this->data->$fld;
            }
        }
        if ($ne_f || empty($this->data->date['begin'])) {
            throw new SoftException('Not enough data to identify the report');
        }
        if ($replace) {
            $this->data = ReportData::fromArray($data);
        }
    }
}
