<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2025 Aleksey Andreev (liuch)
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
 * This file contains the class Host
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Hosts;

use Liuch\DmarcSrg\Core;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\LogicException;
use Liuch\DmarcSrg\Exception\RuntimeException;

/**
 * This class is designed for storing and manipulating hosts data and utilites.
 */
class Host
{
    private $db   = null;
    private $rip  = null;
    private $rdns = null;
    private $data = [
        'ip' => null
    ];

    /**
     * The constructor
     *
     * @param string                                      $ip IP address
     * @param \Liuch\DmarcSrg\Database\DatabaseController $db The database controller
     *
     * @return void
     */
    public function __construct(string $ip, $db = null)
    {
        $this->db = $db ?? Core::instance()->database();
        if (empty($ip)) {
            throw new LogicException('Incorrect host data');
        }
        if (!filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_NO_PRIV_RANGE
        )) {
            throw new SoftException('Incorrect IP address');
        }
        $this->data['ip'] = $ip;
    }

    /**
     * Returns brief information
     *
     * @param array &$fields List of fields
     *
     * @return array
     */
    public function information(array &$fields): array
    {
        $res = [];
        $sts = null;
        $usr = Core::instance()->getCurrentUser();
        foreach ($fields as $fld) {
            switch ($fld) {
                case 'main.rdns':
                    $res[] = [ $fld, $this->rdnsName() ];
                    break;
                case 'main.rip':
                    $res[] = [ $fld, $this->checkReverseIp() ];
                    break;
                default:
                    if (!$sts && substr($fld, 0, 6) === 'stats.') {
                        $sts = $this->statistics($usr);
                    }
                    break;
            }
        }
        if ($sts) {
            foreach ([ 'reports', 'messages', 'last_report' ] as $fld) {
                $lfld = 'stats.' . $fld;
                if (in_array($lfld, $fields)) {
                    $res[] = [ $lfld, $sts[$fld] ];
                }
            }
        }
        return $res;
    }

    /**
     * Returns the reverse DNS hostname or an empty string if lookup fails
     *
     * @return string
     */
    private function rdnsName(): string
    {
        if (is_null($this->rdns)) {
            $rdns = gethostbyaddr($this->data['ip']);
            if ($rdns === false) {
                throw new RuntimeException('Failed to get the reverse DNS hostname');
            }
            if ($rdns === $this->data['ip']) {
                $rdns = '';
            }
            $this->rdns = $rdns;
        }
        return $this->rdns;
    }

    /**
     * Checks if the IP address resolved from the reverse DNS hostname matches the source IP address
     *
     * @return bool
     */
    private function checkReverseIP(): bool
    {
        if (!is_null($this->rip)) {
            return $this->rip;
        }

        $rname = $this->rdnsName();
        if (empty($rname)) {
            return false;
        }

        if (filter_var($this->data['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $type = DNS_A;
        } else {
            $type = DNS_AAAA;
        }
        $ip = inet_ntop(inet_pton($this->data['ip']));
        $ip_res = dns_get_record($rname, $type);
        if ($ip_res === false) {
            throw new RuntimeException('Failed to resolve IP address');
        }
        $this->rip = false;
        foreach ($ip_res as &$rec) {
            $r = ($type === DNS_A) ? $rec['ip'] : $rec['ipv6'];
            if ($ip === $r) {
                $this->rip = true;
                break;
            }
        }
        unset($rec);
        return $this->rip;
    }

    /*
     * Returns an array with statistics of the host
     *
     * @param \Liuch\DmarcSrg\Users\User $user User for whom to get statistic
     *
     * @return array
     */
    private function statistics($user): array
    {
        return $this->db->getMapper('host')->statistics($this->data, $user->id());
    }
}
