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
use Liuch\DmarcSrg\Common;
use Liuch\DmarcSrg\DateTime;
use Liuch\DmarcSrg\Domains\Domain;
use Liuch\DmarcSrg\Domains\DomainList;
use Liuch\DmarcSrg\Database\Database;
use Liuch\DmarcSrg\Settings\SettingsList;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\LogicException;
use Liuch\DmarcSrg\Exception\DatabaseFatalException;

class Report
{
    private $data = null;

    private static $allowed_domains = null;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public static function fromXmlFile($fd)
    {
        $data = ReportData::fromXmlFile($fd);
        if (!self::checkData($data)) {
            throw new SoftException('Incorrect or incomplete report data');
        }
        return new Report($data);
    }

    public static function jsonOrNull($data)
    {
        if (is_null($data)) {
            return null;
        }
        return json_encode($data);
    }

    public function fetch()
    {
        $domain = $this->data['domain'];
        $report_id = $this->data['report_id'];
        if (empty($domain) || empty($report_id)) {
            throw new SoftException('Not specified report\'s domain or id');
        }
        $this->data = [ 'domain' => $domain, 'report_id' => $report_id, 'records' => [] ];
        $db = Database::connection();
        try {
            $st = $db->prepare(
                'SELECT `rp`.`id`, `begin_time`, `end_time`, `loaded_time`, `org`, `email`, `extra_contact_info`,'
                . ' `error_string`, `policy_adkim`, `policy_aspf`, `policy_p`, `policy_sp`, `policy_pct`, `policy_fo`'
                . ' FROM `' . Database::tablePrefix('reports') . '` AS `rp`'
                . ' INNER JOIN `' . Database::tablePrefix('domains') . '` AS `dom` ON `dom`.`id` = `rp`.`domain_id`'
                . ' WHERE `fqdn` = ? AND `external_id` = ?'
            );
            $st->bindValue(1, $domain, \PDO::PARAM_STR);
            $st->bindValue(2, $report_id, \PDO::PARAM_STR);
            $st->execute();
            $id = null;
            try {
                $res = $st->fetch(\PDO::FETCH_NUM);
                if (!$res) {
                    throw new SoftException('The report was not found');
                }
                $id = $res[0];
                $this->data['date'] = [
                    'begin' => new DateTime($res[1]),
                    'end'   => new DateTime($res[2])
                ];
                $this->data['loaded_time']  = new DateTime($res[3]);
                $this->data['org_name']     = $res[4];
                $this->data['email']        = $res[5];
                $this->data['extra_contact_info'] = $res[6];
                $this->data['error_string'] = json_decode($res[7] ?? '', true);
                $this->data['policy']       = [
                    'adkim' => $res[8],
                    'aspf'  => $res[9],
                    'p'     => $res[10],
                    'sp'    => $res[11],
                    'pct'   => $res[12],
                    'fo'    => $res[13]
                ];
            } finally {
                $st->closeCursor();
            }
            $order_str = $this->sqlOrder();
            $st = $db->prepare(
                'SELECT `report_id`, `ip`, `rcount`, `disposition`, `reason`, `dkim_auth` , `spf_auth`, `dkim_align`,'
                . ' `spf_align`, `envelope_to`, `envelope_from`, `header_from`'
                . ' FROM `' . Database::tablePrefix('rptrecords') . '` WHERE `report_id` = ?' . $order_str
            );
            $st->bindValue(1, $id, \PDO::PARAM_INT);
            $st->execute();
            try {
                while ($res = $st->fetch(\PDO::FETCH_NUM)) {
                    $this->data['records'][] = [
                        'ip'            => inet_ntop($res[1]),
                        'count'         => intval($res[2]),
                        'disposition'   => Common::$disposition[$res[3]],
                        'reason'        => json_decode($res[4] ?? '', true),
                        'dkim_auth'     => json_decode($res[5] ?? '', true),
                        'spf_auth'      => json_decode($res[6] ?? '', true),
                        'dkim_align'    => Common::$align_res[$res[7]],
                        'spf_align'     => Common::$align_res[$res[8]],
                        'envelope_to'   => $res[9],
                        'envelope_from' => $res[10],
                        'header_from'   => $res[11]
                    ];
                }
            } finally {
                $st->closeCursor();
            }
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to get the report from DB', -1, $e);
        }
    }

    public function save(string $real_fname)
    {
        $b_ts = $this->data['begin_time'];
        $e_ts = $this->data['end_time'];
        if (!$b_ts->getTimestamp() || !$e_ts->getTimestamp() || $b_ts > $e_ts) {
            throw new SoftException('Failed to add an incoming report: wrong date range');
        }

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $fqdn = strtolower($this->data['domain']);
            $domain = new Domain($fqdn);
            if (!$domain->exists()) {
                // The domain is not found. Let's try to add it automatically.
                $domain = self::insertDomain($fqdn);
            } elseif (!$domain->active()) {
                throw new SoftException('Failed to add an incoming report: the domain is inactive');
            }

            $ct = new DateTime();
            $st = $db->prepare(
                'INSERT INTO `' . Database::tablePrefix('reports')
                . '` (`domain_id`, `begin_time`, `end_time`, `loaded_time`, `org`, `external_id`, `email`,'
                . ' `extra_contact_info`, `error_string`, `policy_adkim`, `policy_aspf`, `policy_p`,'
                . ' `policy_sp`, `policy_pct`, `policy_fo`, `seen`)'
                . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)'
            );
            $st->bindValue(1, $domain->id(), \PDO::PARAM_INT);
            $st->bindValue(2, $this->data['begin_time']->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
            $st->bindValue(3, $this->data['end_time']->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
            $st->bindValue(4, $ct->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
            $st->bindValue(5, $this->data['org'], \PDO::PARAM_STR);
            $st->bindValue(6, $this->data['external_id'], \PDO::PARAM_STR);
            $st->bindValue(7, $this->data['email'], \PDO::PARAM_STR);
            $st->bindValue(8, $this->data['extra_contact_info'], \PDO::PARAM_STR);
            $st->bindValue(9, Report::jsonOrNull($this->data['error_string']), \PDO::PARAM_STR);
            $st->bindValue(10, $this->data['policy_adkim'], \PDO::PARAM_STR);
            $st->bindValue(11, $this->data['policy_aspf'], \PDO::PARAM_STR);
            $st->bindValue(12, $this->data['policy_p'], \PDO::PARAM_STR);
            $st->bindValue(13, $this->data['policy_sp'], \PDO::PARAM_STR);
            $st->bindValue(14, $this->data['policy_pct'], \PDO::PARAM_STR);
            $st->bindValue(15, $this->data['policy_fo'], \PDO::PARAM_STR);
            $st->execute();
            $new_id = intval($db->lastInsertId());
            $st->closeCursor();

            $st = $db->prepare(
                'INSERT INTO `' . Database::tablePrefix('rptrecords')
                . '` (`report_id`, `ip`, `rcount`, `disposition`, `reason`, `dkim_auth`, `spf_auth`, `dkim_align`,'
                . ' `spf_align`, `envelope_to`, `envelope_from`, `header_from`)'
                . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($this->data['records'] as &$rec_data) {
                $st->bindValue(1, $new_id, \PDO::PARAM_INT);
                $st->bindValue(2, inet_pton($rec_data['ip']), \PDO::PARAM_STR);
                $st->bindValue(3, $rec_data['rcount'], \PDO::PARAM_INT);
                $st->bindValue(4, array_search($rec_data['disposition'], Common::$disposition), \PDO::PARAM_INT);
                $st->bindValue(5, Report::jsonOrNull($rec_data['reason']), \PDO::PARAM_STR);
                $st->bindValue(6, Report::jsonOrNull($rec_data['dkim_auth']), \PDO::PARAM_STR);
                $st->bindValue(7, Report::jsonOrNull($rec_data['spf_auth']), \PDO::PARAM_STR);
                $st->bindValue(8, array_search($rec_data['dkim_align'], Common::$align_res), \PDO::PARAM_INT);
                $st->bindValue(9, array_search($rec_data['spf_align'], Common::$align_res), \PDO::PARAM_INT);
                $st->bindValue(10, $rec_data['envelope_to'], \PDO::PARAM_STR);
                $st->bindValue(11, $rec_data['envelope_from'], \PDO::PARAM_STR);
                $st->bindValue(12, $rec_data['header_from'], \PDO::PARAM_STR);
                $st->execute();
                $st->closeCursor();
            }
            unset($rec_data);
            $db->commit();
            $this->data['loaded_time']  = $ct;
        } catch (\PDOException $e) {
            $db->rollBack();
            if ($e->getCode() == '23000') {
                throw new SoftException('This report has already been loaded');
            }
            throw new DatabaseFatalException('Failed to insert the report', -1, $e);
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
        return [ 'message' => 'The report is loaded successfully' ];
    }

    public function get()
    {
        return $this->data;
    }

    public function set($name, $value)
    {
        if ($name !== 'seen' && gettype($value) !== 'boolean') {
            throw new LogicException('Incorrect parameters');
        }

        $db = Database::connection();
        try {
            $st = $db->prepare(
                'UPDATE `' . Database::tablePrefix('reports') . '` AS `rp`'
                . ' INNER JOIN `' . Database::tablePrefix('domains') . '` AS `dom`'
                . ' ON `rp`.`domain_id` = `dom`.`id` SET `seen` = ? WHERE `fqdn` = ? AND `external_id` = ?'
            );
            $st->bindValue(1, $value, \PDO::PARAM_BOOL);
            $st->bindValue(2, $this->data['domain'], \PDO::PARAM_STR);
            $st->bindValue(3, $this->data['report_id'], \PDO::PARAM_STR);
            $st->execute();
            $st->closeCursor();
        } catch (\PDOException $e) {
            throw new DatabaseFatalException('Failed to update the DB record', -1, $e);
        }
        return [ 'message' => 'Ok' ];
    }

    /**
     * Returns `ORDER BY ...` part of the SQL query for report records
     *
     * @return string
     */
    private function sqlOrder(): string
    {
        $o_set = explode(',', SettingsList::getSettingByName('report-view.sort-records-by')->value());
        switch ($o_set[0]) {
            case 'ip':
                $fname = 'ip';
                break;
            case 'message-count':
            default:
                $fname = 'rcount';
                break;
        }
        $dir = $o_set[1] === 'descent' ? 'DESC' : 'ASC';

        return " ORDER BY `{$fname}` {$dir}";
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

    /**
     * Checks if the domain exists and adds it to the database if necessary
     *
     * It automatically adds the domain if there are no domains in the database
     * or if the domain match the reqular expression `allowed_domains` from the configuration file.
     * Otherwise, an exception will be thrown.
     *
     * @param string $fqdn Domain name
     *
     * @return Domain Instance of the class Domain
     */
    private static function insertDomain(string $fqdn)
    {
        if (DomainList::count() !== 0) {
            if (is_null(self::$allowed_domains)) {
                global $fetcher;
                if (!empty($fetcher['allowed_domains'])) {
                    self::$allowed_domains = '<' . $fetcher['allowed_domains'] . '>i';
                }
            }
            try {
                $add = !empty(self::$allowed_domains) && preg_match(self::$allowed_domains, $fqdn) === 1;
            } catch (\ErrorException $e) {
                $add = false;
                Core::instance()->logger()->warning(
                    'The allow_domains parameter in the settings has an incorrect regular expression value.'
                );
            }
            if (!$add) {
                throw new SoftException('Failed to add an incoming report: unknown domain: ' . $fqdn);
            }
        }

        $domain = new Domain([
            'fqdn'        => $fqdn,
            'active'      => true,
            'description' => 'The domain was added automatically.'
        ]);
        $domain->save();

        return $domain;
    }
}
