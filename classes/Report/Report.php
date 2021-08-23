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

use PDO;
use Exception;
use Liuch\DmarcSrg\Common;
use Liuch\DmarcSrg\Domains\Domain;
use Liuch\DmarcSrg\Domains\DomainList;
use Liuch\DmarcSrg\Database\Database;
use Liuch\DmarcSrg\Settings\SettingsList;

class Report
{
    private $data = null;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public static function fromXmlFile($fd)
    {
        return new Report(ReportData::fromXmlFile($fd));
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
            throw new Exception('Not specified report\'s domain or id');
        }
        $this->data = [ 'domain' => $domain, 'report_id' => $report_id, 'records' => [] ];
        $db = Database::connection();
        try {
            $st = $db->prepare('SELECT `reports`.`id`, `begin_time`, `end_time`, `loaded_time`, `org`, `email`, `extra_contact_info`, `error_string`, `policy_adkim`, `policy_aspf`, `policy_p`, `policy_sp`, `policy_pct`, `policy_fo` FROM `reports` INNER JOIN `domains` ON `domains`.`id` = `reports`.`domain_id` WHERE `fqdn` = ? AND `external_id` = ?');
            $st->bindValue(1, $domain, PDO::PARAM_STR);
            $st->bindValue(2, $report_id, PDO::PARAM_STR);
            $st->execute();
            $id = null;
            try {
                $res = $st->fetch(PDO::FETCH_NUM);
                if (!$res) {
                    throw new Exception('The report was not found', -1);
                }
                $id = $res[0];
                $this->data['date'] = [
                    'begin' => strtotime($res[1]),
                    'end'   => strtotime($res[2])
                ];
                $this->data['loaded_time']  = strtotime($res[3]);
                $this->data['org_name']     = $res[4];
                $this->data['email']        = $res[5];
                $this->data['extra_contact_info'] = $res[6];
                $this->data['error_string'] = json_decode($res[7], true);
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
            $st = $db->prepare("SELECT `report_id`, `ip`, `rcount`, `disposition`, `reason`, `dkim_auth` , `spf_auth`, `dkim_align`, `spf_align`, `envelope_to`, `envelope_from`, `header_from` FROM `rptrecords` WHERE `report_id` = ?{$order_str}");
            $st->bindValue(1, $id, PDO::PARAM_INT);
            $st->execute();
            try {
                while ($res = $st->fetch(PDO::FETCH_NUM)) {
                    $this->data['records'][] = [
                        'ip'            => inet_ntop($res[1]),
                        'hostname'      => gethostbyaddr(inet_ntop($res[1])),
                        'count'         => intval($res[2]),
                        'disposition'   => Common::$disposition[$res[3]],
                        'reason'        => json_decode($res[4], true),
                        'dkim_auth'     => json_decode($res[5], true),
                        'spf_auth'      => json_decode($res[6], true),
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
        } catch (Exception $e) {
            if ($e->getCode() !== -1) {
                throw new Exception('Failed to get the report from DB', -1);
            }
            throw $e;
        }
    }

    public function save(string $real_fname)
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $fqdn = $this->data['domain'];
            $domain = new Domain($fqdn);
            if (!$domain->exists()) {
                // The domain is not found.
                // It will automatically added a new domain if there are no domains in the table
                // or will throw an error otherwise.
                if (DomainList::count() !== 0) {
                    throw new Exception('Failed to add an incoming report: unknown domain', -1);
                }

                $domain = new Domain([
                    'fqdn'        => $fqdn,
                    'active'      => true,
                    'description' => 'The domain was added automatically.'
                ]);
                $domain->save();
            } elseif (!$domain->active()) {
                throw new Exception('Failed to add an incoming report: the domain is inactive', -1);
            }

            $st = $db->prepare('INSERT INTO `reports` (`domain_id`, `begin_time`, `end_time`, `loaded_time`, `org`, `external_id`, `email`, `extra_contact_info`, `error_string`, `policy_adkim`, `policy_aspf`, `policy_p`, `policy_sp`, `policy_pct`, `policy_fo`, `seen`) VALUES (?, FROM_UNIXTIME(?), FROM_UNIXTIME(?), NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)');
            $st->bindValue(1, $domain->id(), PDO::PARAM_INT);
            $st->bindValue(2, $this->data['begin_time'], PDO::PARAM_INT);
            $st->bindValue(3, $this->data['end_time'], PDO::PARAM_INT);
            $st->bindValue(4, $this->data['org'], PDO::PARAM_STR);
            $st->bindValue(5, $this->data['external_id'], PDO::PARAM_STR);
            $st->bindValue(6, $this->data['email'], PDO::PARAM_STR);
            $st->bindValue(7, $this->data['extra_contact_info'], PDO::PARAM_STR);
            $st->bindValue(8, Report::jsonOrNull($this->data['error_string']), PDO::PARAM_STR);
            $st->bindValue(9, $this->data['policy_adkim'], PDO::PARAM_STR);
            $st->bindValue(10, $this->data['policy_aspf'], PDO::PARAM_STR);
            $st->bindValue(11, $this->data['policy_p'], PDO::PARAM_STR);
            $st->bindValue(12, $this->data['policy_sp'], PDO::PARAM_STR);
            $st->bindValue(13, $this->data['policy_pct'], PDO::PARAM_STR);
            $st->bindValue(14, $this->data['policy_fo'], PDO::PARAM_STR);
            $st->execute();
            $new_id = $db->lastInsertId();
            $st->closeCursor();

            $st = $db->prepare('INSERT INTO `rptrecords` (`report_id`, `ip`, `rcount`, `disposition`, `reason`, `dkim_auth`, `spf_auth`, `dkim_align`, `spf_align`, `envelope_to`, `envelope_from`, `header_from`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            foreach ($this->data['records'] as &$rec_data) {
                $st->bindValue(1, $new_id, PDO::PARAM_INT);
                $st->bindValue(2, inet_pton($rec_data['ip']), PDO::PARAM_STR);
                $st->bindValue(3, $rec_data['rcount'], PDO::PARAM_INT);
                $st->bindValue(4, array_search($rec_data['disposition'], Common::$disposition), PDO::PARAM_INT);
                $st->bindValue(5, Report::jsonOrNull($rec_data['reason']), PDO::PARAM_STR);
                $st->bindValue(6, Report::jsonOrNull($rec_data['dkim_auth']), PDO::PARAM_STR);
                $st->bindValue(7, Report::jsonOrNull($rec_data['spf_auth']), PDO::PARAM_STR);
                $st->bindValue(8, array_search($rec_data['dkim_align'], Common::$align_res), PDO::PARAM_INT);
                $st->bindValue(9, array_search($rec_data['spf_align'], Common::$align_res), PDO::PARAM_INT);
                $st->bindValue(10, $rec_data['envelope_to'], PDO::PARAM_STR);
                $st->bindValue(11, $rec_data['envelope_from'], PDO::PARAM_STR);
                $st->bindValue(12, $rec_data['header_from'], PDO::PARAM_STR);
                $st->execute();
                $st->closeCursor();
            }
            unset($rec_data);
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            if ($e->getCode() == '23000') {
                throw new Exception('This report has already been loaded', -1);
            } elseif ($e->getCode() == -1) {
                throw $e;
            } else {
                throw new Exception('Failed to insert the report', -1);
            }
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
            throw new Exception('Incorrect parameters', -1);
        }

        $db = Database::connection();
        try {
            $st = $db->prepare('UPDATE `reports` INNER JOIN `domains` ON `reports`.`domain_id` = `domains`.`id` SET `seen` = ? WHERE `fqdn` = ? AND `external_id` = ?');
            $st->bindValue(1, $value, PDO::PARAM_BOOL);
            $st->bindValue(2, $this->data['domain'], PDO::PARAM_STR);
            $st->bindValue(3, $this->data['report_id'], PDO::PARAM_STR);
            $st->execute();
            $st->closeCursor();
        } catch (Exception $e) {
            throw new Exception('Failed to update the DB record', -1);
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
}

