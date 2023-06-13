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

namespace Liuch\DmarcSrg\Report;

use Liuch\DmarcSrg\Core;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\DatabaseNotFoundException;

class Report
{
    private $db   = null;
    private $data = null;

    public function __construct($data, $db = null)
    {
        $this->data = $data;
        $this->db   = $db ?? Core::instance()->database();
    }

    public static function fromXmlFile($fd)
    {
        $data = ReportData::fromXmlFile($fd);
        if (!self::checkData($data)) {
            throw new SoftException('Incorrect or incomplete report data');
        }
        return new Report($data);
    }

    public function fetch()
    {
        $domain = $this->data['domain'];
        $report_id = $this->data['report_id'];
        if (empty($domain) || empty($report_id)) {
            throw new SoftException('Not specified report\'s domain or id');
        }
        $this->data = [ 'domain' => $domain, 'report_id' => $report_id ];
        try {
            $this->db->getMapper('report')->fetch($this->data);
        } catch (DatabaseNotFoundException $e) {
            throw new SoftException('The report is not found');
        }
    }

    public function save(string $real_fname)
    {
        $b_ts = $this->data['begin_time'];
        $e_ts = $this->data['end_time'];
        if (!$b_ts->getTimestamp() || !$e_ts->getTimestamp() || $b_ts > $e_ts) {
            throw new SoftException('Failed to add an incoming report: wrong date range');
        }

        $this->db->getMapper('report')->save($this->data);
        return [ 'message' => 'The report is loaded successfully' ];
    }

    public function get()
    {
        return $this->data;
    }

    public function set($name, $value)
    {
        if (empty($this->data['domain']) || empty($this->data['report_id'])) {
            throw new SoftException('Not specified report\'s domain or id');
        }

        $this->db->getMapper('report')->setProperty($this->data, $name, $value);
        return [ 'message' => 'Ok' ];
    }

    /**
     * Checks report data for correctness and completeness
     *
     * @param array $data Report data
     *
     * @return bool
     */
    private static function checkData(array $data): bool
    {
        static $fields = [
            'domain'             => [ 'required' => true,  'type' => 'string' ],
            'begin_time'         => [ 'required' => true,  'type' => 'object' ],
            'end_time'           => [ 'required' => true,  'type' => 'object' ],
            'org'                => [ 'required' => true,  'type' => 'string' ],
            'external_id'        => [ 'required' => true,  'type' => 'string' ],
            'email'              => [ 'required' => false, 'type' => 'string' ],
            'extra_contact_info' => [ 'required' => false, 'type' => 'string' ],
            'error_string'       => [ 'required' => false, 'type' => 'array'  ],
            'policy_adkim'       => [ 'required' => false, 'type' => 'string' ],
            'policy_aspf'        => [ 'required' => false, 'type' => 'string' ],
            'policy_p'           => [ 'required' => false, 'type' => 'string' ],
            'policy_sp'          => [ 'required' => false, 'type' => 'string' ],
            'policy_np'          => [ 'required' => false, 'type' => 'string' ],
            'policy_pct'         => [ 'required' => false, 'type' => 'string' ],
            'policy_fo'          => [ 'required' => false, 'type' => 'string' ],
            'records'            => [ 'required' => true,  'type' => 'array'  ]
        ];
        if (!self::checkRow($data, $fields) || count($data['records']) === 0) {
            return false;
        }

        static $rfields = [
            'ip'            => [ 'required' => true,  'type' => 'string'  ],
            'rcount'        => [ 'required' => true,  'type' => 'integer' ],
            'disposition'   => [ 'required' => true,  'type' => 'string'  ],
            'reason'        => [ 'required' => false, 'type' => 'array'   ],
            'dkim_auth'     => [ 'required' => false, 'type' => 'array'   ],
            'spf_auth'      => [ 'required' => false, 'type' => 'array'   ],
            'dkim_align'    => [ 'required' => true,  'type' => 'string'  ],
            'spf_align'     => [ 'required' => true,  'type' => 'string'  ],
            'envelope_to'   => [ 'required' => false, 'type' => 'string'  ],
            'envelope_from' => [ 'required' => false, 'type' => 'string'  ],
            'header_from'   => [ 'required' => false, 'type' => 'string'  ]
        ];
        foreach ($data['records'] as &$rec) {
            if (gettype($rec) !== 'array' || !self::checkRow($rec, $rfields)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks one row of report data
     *
     * @param array $row Data row
     * @param array $def Row definition
     *
     * @return bool
     */
    private static function checkRow(array &$row, array &$def): bool
    {
        foreach ($def as $key => &$dd) {
            if (isset($row[$key])) {
                if (gettype($row[$key]) !== $dd['type']) {
                    return false;
                }
            } elseif ($dd['required']) {
                return false;
            }
        }
        return true;
    }
}
