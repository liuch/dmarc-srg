<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2022-2025 Aleksey Andreev (liuch)
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
 * This file contains SummaryReport class
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Report;

use Liuch\DmarcSrg\Common;
use Liuch\DmarcSrg\DateTime;
use Liuch\DmarcSrg\TextTable;
use Liuch\DmarcSrg\Statistics;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\LogicException;

/**
 * This class is for generating summary data for the specified period and domain
 */
class SummaryReport
{
    private const LAST_WEEK  = -1;
    private const LAST_MONTH = -2;
    private const DATE_RANGE = -3;

    private $period  = 0;
    private $offset  = 0;
    private $range   = null;
    private $domain  = null;
    private $overall = [];
    private $stat    = [];
    private $subject = '';

    /**
     * Constructor
     *
     * @param string $period The period for which the report is created
     *                       Must me one of the following values: `lastweek`, `lastmonth`, and `lastndays:N`
     *                       where N is the number of days the report is created for
     * @param int            $offset Range offset
     */
    public function __construct(string $period, int $offset = 0)
    {
        switch ($period) {
            case 'lastweek':
                $period  = self::LAST_WEEK;
                $subject = ' weekly digest';
                break;
            case 'lastmonth':
                $period  = self::LAST_MONTH;
                $subject = ' monthly digest';
                break;
            default:
                $ndays = 0;
                $av = explode(':', $period);
                if (count($av) === 2) {
                    switch ($av[0]) {
                        case 'lastndays':
                            $ndays = intval($av[1]);
                            if ($ndays <= 0) {
                                throw new SoftException('The parameter "days" has an incorrect value');
                            }
                            $period  = $ndays;
                            $subject = sprintf(' %d day%s digest', $ndays, ($ndays > 1 ? 's' : ''));
                            break;
                        case 'range':
                            $range = explode('-', $av[1]);
                            if (count($range) === 2) {
                                foreach ($range as &$r) {
                                    $cnt = 0;
                                    $sd = preg_replace('/^(\d{4})(\d{2})(\d{2})$/', '\1-\2-\3', $r, -1, $cnt);
                                    if (!$cnt) {
                                        throw new SoftException('The parameter "range" has an incorrect value');
                                    }
                                    $r = new DateTime($sd);
                                }
                                unset($r);
                                if ($range[0] > $range[1]) {
                                    throw new SoftException('Incorrect date range');
                                }
                                $period  = self::DATE_RANGE;
                                $subject = ' report ' . Common::rangeToString($range);
                                $range[1]->modify('next day');
                                $this->range = $range;
                            }
                            break;
                    }
                }
                break;
        }
        if (empty($subject)) {
            throw new SoftException('The parameter "period" has an incorrect value');
        }
        if ($offset < 0) {
            throw new SoftException('The parameter "offset" has an incorrect value');
        }
        $this->period  = $period;
        $this->offset  = $offset;
        $this->subject = "DMARC{$subject}";
    }

    /**
     * Binds a domain to the report
     *
     * @param \Liuch\DmarcSrg\Domains\Domain $domain The domain for which the report is created
     *
     * @return self
     */
    public function setDomain($domain)
    {
        $this->domain = $domain;
        $this->stat   = [];
        return $this;
    }

    /**
     * Checks if the report is empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return ($this->getData('summary')['emails']['total'] === 0);
    }

    /**
     * Binds a report section
     *
     * @param mixed $section Report section
     *
     * @return self
     */
    public function bindSection($section)
    {
        if ($section instanceof OverallReport) {
            $this->overall[] = $section;
        }
        return $this;
    }

    /**
     * Returns the report data as an array
     *
     * @return array
     */
    public function toArray(): array
    {
        $range = $this->getData('range');
        return [
            'date_range'    => [ 'begin' => $range[0], 'end' => $range[1] ],
            'summary'       => $this->getData('summary'),
            'sources'       => $this->getData('ips'),
            'organizations' => $this->getData('organizations')
        ];
    }

    /**
     * Returns the subject string. It is used in email messages.
     *
     * @return string
     */
    public function subject(): string
    {
        return $this->subject;
    }

    /**
     * Returns the report as an array of text strings
     *
     * @return array
     */
    public function text(): array
    {
        $rdata = $this->reportData();

        $res = [ '# Domain: ' . $this->domain->fqdn() ];
        $res[] = ' Range: ' . $rdata['range'];
        $res[] = '';

        $res[] = '## Summary';
        $total = $rdata['summary']['total'];
        $res[] = sprintf(' Total: %d', $total);
        $res[] = sprintf(' Fully aligned: %s', Common::num2percent($rdata['summary']['f_aligned'], $total, true));
        $res[] = sprintf(' Partial aligned: %s', Common::num2percent($rdata['summary']['p_aligned'], $total, true));
        $res[] = sprintf(' Not aligned: %s', Common::num2percent($rdata['summary']['n_aligned'], $total, true));
        $res[] = sprintf(' Quarantined: %s', Common::num2percent($rdata['summary']['quarantined'], $total, true));
        $res[] = sprintf(' Rejected: %s', Common::num2percent($rdata['summary']['rejected'], $total, true));
        $res[] = '';

        if (count($rdata['sources']) > 0) {
            $res[] = '## Sources';
            $table = new TextTable([ '', 'Total', 'SPF only', 'DKIM only', 'Not aligned', 'Quar+Rej' ]);
            $table->setMinColumnsWidth([ 15, 5, 8, 9, 11, 8 ])->setBorders('', '', '')
                ->setColumnAlignment(4, 'right')->setColumnAlignment(5, 'right');
            foreach ($rdata['sources'] as &$it) {
                $total = $it['emails'];
                $f_aln = $it['dkim_spf_aligned'];
                $d_aln = $it['dkim_aligned'];
                $s_aln = $it['spf_aligned'];
                $n_aln = $total - $f_aln - $d_aln - $s_aln;
                $q_dis = $it['quarantined'];
                $r_dis = $it['rejected'];
                if ($q_dis || $r_dis) {
                    $s_dis = Common::num2percent($q_dis + $r_dis, $total, false) . "({$q_dis}+{$r_dis})";
                } else {
                    $s_dis = '0';
                }
                $table->appendRow([
                    $it['ip'], $total, $s_aln, $d_aln, Common::num2percent($n_aln, $total, true), $s_dis
                ]);
            }
            unset($it);
            $res = array_merge($res, $table->toArray());
            $res[] = '';
        }

        if (count($rdata['organizations']) > 0) {
            $res[] = '## Reporting organizations';
            $table = new TextTable([ '', 'Reports', 'Emails', 'SPF only', 'DKIM only', 'Not aligned', 'Quar+Rej' ]);
            $table->setMinColumnsWidth([ 15, 7, 6, 8, 9, 11, 8 ])->setBorders('', '', '')
                ->setColumnAlignment(5, 'right')->setColumnAlignment(6, 'right');
            foreach ($rdata['organizations'] as &$it) {
                $total = $it['emails'];
                $f_aln = $it['dkim_spf_aligned'];
                $d_aln = $it['dkim_aligned'];
                $s_aln = $it['spf_aligned'];
                $n_aln = $total - $f_aln - $d_aln - $s_aln;
                $q_dis = $it['quarantined'];
                $r_dis = $it['rejected'];
                if ($q_dis || $r_dis) {
                    $s_dis = Common::num2percent($q_dis + $r_dis, $total, false) . "({$q_dis}+{$r_dis})";
                } else {
                    $s_dis = '0';
                }
                $table->appendRow([
                    $it['name'], $it['reports'], $total, $s_aln, $d_aln,
                    Common::num2percent($n_aln, $total, true), $s_dis
                ]);
            }
            unset($it);
            $res = array_merge($res, $table->toArray());
            $res[] = '';
        }

        return $res;
    }

    /**
     * Returns the report as an array of html strings
     *
     * @return array
     */
    public function html(): array
    {
        $h2a = 'style="margin:15px 0 5px;"';
        $t2a = 'style="border-collapse:collapse;border-spacing:0;"';
        $c1a = 'style="font-style:italic;"';
        $d1s = 'padding-left:1em;';
        $d2s = 'min-width:4em;';
        $d3s = 'border:1px solid #888;';
        $d4s = 'text-align:right;';
        $d5s = 'padding:.3em;';
        $rs2 = 'rowspan="2"';
        $cs2 = 'colspan="2"';

        $get_color = function (string $name, int $num) {
            $cn = '';
            if ($num > 0) {
                switch ($name) {
                    case 'red':
                        $cn = 'f00';
                        break;
                    case 'green':
                        $cn = '080';
                        break;
                }
            }
            return empty($cn) ? '' : "color:#{$cn};";
        };

        $rdata = $this->reportData();
        $res = [];
        $res[] = "<h2 {$h2a}>Domain: " . htmlspecialchars($this->domain->fqdn()) . '</h2>';
        $res[] = '<p style="margin:0;">Range: ' . htmlspecialchars($rdata['range']) . '</p>';

        $res[] = "<h3 {$h2a}>Summary</h3>";
        $res[] = '<table>';
        $total = $rdata['summary']['total'];
        $res[] = " <tr><td>Total: </td><td style=\"{$d1s}\">{$total}</td></tr>";
        foreach ([
            [ 'Fully aligned', $rdata['summary']['f_aligned'], 'green' ],
            [ 'Partial aligned', $rdata['summary']['p_aligned'], '' ],
            [ 'Not aligned', $rdata['summary']['n_aligned'], 'red' ],
            [ 'Quarantined', $rdata['summary']['quarantined'], 'red' ],
            [ 'Rejected', $rdata['summary']['rejected'], 'red' ]
        ] as &$rd) {
            $color = $get_color($rd[2], $rd[1]);
            $s_data = Common::num2percent($rd[1], $total, true);
            $res[] = " <tr><td>{$rd[0]}: </td><td style=\"{$d1s}{$color}\">{$s_data}</td></tr>";
        }
        unset($rd);
        $res[] = '</table>';

        $s_cnt = count($rdata['sources']);
        if ($s_cnt > 0) {
            $res[] = "<h3 {$h2a}>Sources</h3>";
            $res[] = "<table {$t2a}>";
            $res[] = " <caption {$c1a}>Total records: {$s_cnt}</caption>";
            $res[] = ' <thead>';
            $style = "style=\"{$d3s}{$d5s}\"";
            $res[] = "  <tr><th {$rs2} {$style}>IP address</th><th {$rs2} {$style}>Email volume</th>" .
                     "<th {$cs2} {$style}>Partial aligned</th><th {$rs2} {$style}>Not aligned</th>" .
                     "<th {$cs2} {$style}>Disposition</th></tr>";
            $style = "style=\"{$d2s}{$d3s}{$d5s}\"";
            $res[] = "  <tr><th {$style}>SPF only</th><th {$style}>DKIM only</th>" .
                     "<th {$style}>quar+rej</th><th {$style}>fail rate</th></tr>";
            $res[] = ' </thead>';
            $res[] = ' <tbody>';
            $style = "style=\"{$d3s}{$d5s}";
            foreach ($rdata['sources'] as &$row) {
                $ip    = htmlspecialchars($row['ip']);
                $total = $row['emails'];
                $f_aln = $row['dkim_spf_aligned'];
                $d_aln = $row['dkim_aligned'];
                $s_aln = $row['spf_aligned'];
                $n_aln = $total - $f_aln - $d_aln - $s_aln;
                $q_dis = $row['quarantined'];
                $r_dis = $row['rejected'];
                $s_dis = ($q_dis || $r_dis) ? "{$q_dis}+{$r_dis}" : '0';
                $res[] = "  <tr><td {$style}\">{$ip}</td><td {$style}{$d4s}\">{$total}</td>" .
                         "<td {$style}{$d4s}\">{$s_aln}</td><td {$style}{$d4s}\">{$d_aln}</td>" .
                         "<td {$style}{$d4s}{$get_color('red', $n_aln)}\">{$n_aln}</td>" .
                         "<td {$style}{$d4s}{$get_color('red', $q_dis + $r_dis)}\">{$s_dis}</td>" .
                         "<td {$style}{$d4s}\">" . Common::num2percent($q_dis + $r_dis, $total, false) .
                         '</td></tr>';
            }
            unset($row);
            $res[] = ' </tbody>';
            $res[] = '</table>';
        }

        $o_cnt = count($rdata['organizations']);
        if ($o_cnt) {
            $res[] = "<h3 {$h2a}>Organizations</h3>";
            $res[] = "<table {$t2a}>";
            $res[] = " <caption {$c1a}>Total records: {$o_cnt}</caption>";
            $res[] = ' <thead>';
            $style = "style=\"{$d3s}{$d5s}\"";
            $res[] = "  <tr><th {$rs2} {$style}>Name</th><th {$cs2} {$style}>Volume</th>" .
                     "<th {$cs2} {$style}>Partial aligned</th><th {$rs2} {$style}>Not aligned</th>" .
                     "<th {$cs2} {$style}>Disposition</th></tr>";
            $res[] = "  <tr><th {$style}>reports</th><th {$style}>emails</th>" .
                     "<th {$style}>SPF only</th><th {$style}>DKIM only</th>" .
                     "<th {$style}>quar+rej</th><th {$style}>fail rate</th></tr>";
            $res[] = ' </thead>';
            $res[] = ' <tbody>';
            $style = "style=\"{$d3s}{$d5s}";
            foreach ($rdata['organizations'] as &$row) {
                $name   = htmlspecialchars(trim($row['name']));
                $total  = $row['emails'];
                $f_aln  = $row['dkim_spf_aligned'];
                $d_aln  = $row['dkim_aligned'];
                $s_aln  = $row['spf_aligned'];
                $n_aln  = $total - $f_aln - $d_aln - $s_aln;
                $q_dis  = $row['quarantined'];
                $r_dis  = $row['rejected'];
                $s_dis  = ($q_dis || $r_dis) ? "{$q_dis}+{$r_dis}" : '0';
                $res[] = "  <tr><td {$style}\">{$name}</td>" .
                         "<td {$style}{$d4s}\">{$row['reports']}</td><td {$style}{$d4s}\">{$total}</td>" .
                         "<td {$style}{$d4s}\">{$s_aln}</td><td {$style}{$d4s}\">{$d_aln}</td>" .
                         "<td {$style}{$d4s}{$get_color('red', $n_aln)}\">{$n_aln}</td>" .
                         "<td {$style}{$d4s}{$get_color('red', $q_dis + $r_dis)}\">{$s_dis}</td>" .
                         "<td {$style}{$d4s}\">" . Common::num2percent($q_dis + $r_dis, $total, false) .
                         '</td></tr>';
            }
            unset($row);
            $res[] = ' </tbody>';
            $res[] = '</table>';
        }

        return $res;
    }

    /**
     * Returns the report data in CSV format
     *
     * @return string
     */
    public function csv(): string
    {
        $rdata = $this->reportData();

        $res = [];
        $res[] = 'Domain: ' . $this->domain->fqdn();

        $res[] = 'Range: ' . $rdata['range'];
        $res[] = '';

        $res[] = 'Summary';
        $total = $rdata['summary']['total'];
        $res[] = sprintf('Total: %d', $total);
        $res[] = sprintf('Fully aligned: %s', Common::num2percent($rdata['summary']['f_aligned'], $total, true));
        $res[] = sprintf('Partial aligned: %s', Common::num2percent($rdata['summary']['p_aligned'], $total, true));
        $res[] = sprintf('Not aligned: %s', Common::num2percent($rdata['summary']['n_aligned'], $total, true));
        $res[] = sprintf('Quarantined: %s', Common::num2percent($rdata['summary']['quarantined'], $total, true));
        $res[] = sprintf('Rejected: %s', Common::num2percent($rdata['summary']['rejected'], $total, true));
        $res[] = '';

        if (count($rdata['sources']) > 0) {
            $res[] = 'Sources';
            $res[] = [ '', 'Total', 'SPF only', 'DKIM only', 'Not aligned', 'Quar+Rej' ];
            foreach ($rdata['sources'] as &$it) {
                $total = $it['emails'];
                $f_aln = $it['dkim_spf_aligned'];
                $d_aln = $it['dkim_aligned'];
                $s_aln = $it['spf_aligned'];
                $n_aln = $total - $f_aln - $d_aln - $s_aln;
                $q_dis = $it['quarantined'];
                $r_dis = $it['rejected'];
                if ($q_dis || $r_dis) {
                    $s_dis = Common::num2percent($q_dis + $r_dis, $total, false) . "({$q_dis}+{$r_dis})";
                } else {
                    $s_dis = '0';
                }
                $res[] = [ $it['ip'], $total, $s_aln, $d_aln, Common::num2percent($n_aln, $total, true), $s_dis ];
            }
            unset($it);
            $res[] = '';
        }

        if (count($rdata['organizations']) > 0) {
            $res[] = 'Organizations';
            $res[] = [ '', 'Reports', 'Emails', 'SPF only', 'DKIM only', 'Not aligned', 'Quar+Rej' ];
            foreach ($rdata['organizations'] as &$it) {
                $total = $it['emails'];
                $f_aln = $it['dkim_spf_aligned'];
                $d_aln = $it['dkim_aligned'];
                $s_aln = $it['spf_aligned'];
                $n_aln = $total - $f_aln - $d_aln - $s_aln;
                $q_dis = $it['quarantined'];
                $r_dis = $it['rejected'];
                if ($q_dis || $r_dis) {
                    $s_dis = Common::num2percent($q_dis + $r_dis, $total, false) . "({$q_dis}+{$r_dis})";
                } else {
                    $s_dis = '0';
                }
                $res[] = [
                    trim($it['name']), $it['reports'], $total, $s_aln, $d_aln,
                    Common::num2percent($n_aln, $total, true), $s_dis
                ];
            }
            unset($it);
            $res[] = '';
        }
        return Common::arrayToCSV($res);
    }

    /**
     * Caches and returns statistics for the specified section
     *
     * @param string $section Section name
     *
     * @return array
     */
    private function getData(string $section): array
    {
        if (!$this->domain) {
            throw new LogicException('No one domain was specified');
        }

        $instance = $this->stat['instance'] ?? null;
        if (!$instance) {
            switch ($this->period) {
                case self::LAST_WEEK:
                    $instance = Statistics::lastWeek($this->domain, $this->offset);
                    break;
                case self::LAST_MONTH:
                    $instance = Statistics::lastMonth($this->domain, $this->offset);
                    break;
                case self::DATE_RANGE:
                    $instance = Statistics::fromTo($this->domain, $this->range[0], $this->range[1]);
                    break;
                default:
                    $instance = Statistics::lastNDays($this->domain, $this->period, $this->offset);
                    break;
            }
            $this->stat['instance'] = $instance;
            foreach ($this->overall as $o) {
                if ($o instanceof OverallReport) {
                    $o->appendData(
                        array_merge([ 'fqdn'  => $this->domain->fqdn() ], $this->getData('summary')['emails'])
                    );
                }
            }
        }
        switch ($section) {
            case 'range':
            case 'summary':
            case 'ips':
            case 'organizations':
                $res = $this->stat[$section] ?? null;
                break;
            default:
                throw new LogicException('Unknown section name');
        }
        if (!$res) {
            $res = $instance->$section();
            $this->stat[$section] = &$res;
        }
        return $res;
    }

    /**
     * Returns prepared data for the report
     *
     * @return array
     */
    private function reportData(): array
    {
        $rdata = [];
        $rdata['range'] = Common::rangeToString($this->getData('range'));

        $summ      = $this->getData('summary');
        $total     = $summ['emails']['total'];
        $f_aligned = $summ['emails']['dkim_spf_aligned'];
        $p_aligned = $summ['emails']['dkim_aligned'] + $summ['emails']['spf_aligned'];
        $n_aligned = $total - $f_aligned - $p_aligned;
        $rdata['summary'] = [
            'total'         => $total,
            'organizations' => $summ['organizations']
        ];

        $rdata['summary']['f_aligned']   = $f_aligned;
        $rdata['summary']['p_aligned']   = $p_aligned;
        $rdata['summary']['n_aligned']   = $n_aligned;
        $rdata['summary']['rejected']    = $summ['emails']['rejected'];
        $rdata['summary']['quarantined'] = $summ['emails']['quarantined'];

        $rdata['sources'] = $this->getData('ips');

        $rdata['organizations'] = $this->getData('organizations');

        return $rdata;
    }
}
