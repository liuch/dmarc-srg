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

    private $period  = 0;
    private $domain  = null;
    private $stat    = null;
    private $subject = '';

    /**
     * Constructor
     *
     * @param string $period The period for which the report is created
     *                       Must me one of the following values: `lastweek`, `lastmonth`, and `lastndays:N`
     *                       where N is the number of days the report is created for
     */
    public function __construct(string $period)
    {
        switch ($period) {
            case 'lastweek':
                $period  = self::LAST_WEEK;
                $subject = ' weekly';
                break;
            case 'lastmonth':
                $period  = self::LAST_MONTH;
                $subject = ' monthly';
                break;
            default:
                $ndays = 0;
                $av = explode(':', $period);
                if (count($av) === 2 && $av[0] === 'lastndays') {
                    $ndays = intval($av[1]);
                    if ($ndays <= 0) {
                        throw new SoftException('The parameter "days" has an incorrect value');
                    }
                    $subject = sprintf(' %d day%s', $ndays, ($ndays > 1 ? 's' : ''));
                }
                $period = $ndays;
                break;
        }
        if (empty($subject)) {
            throw new SoftException('The parameter "period" has an incorrect value');
        }
        $this->period  = $period;
        $this->subject = "DMARC{$subject} digest";
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
        $this->stat   = null;
        return $this;
    }

    /**
     * Returns the report data as an array
     *
     * @return array
     */
    public function toArray(): array
    {
        $this->ensureData();

        $res = [];
        $stat = $this->stat;
        $range = $stat->range();
        $res['date_range'] = [ 'begin' => $range[0], 'end' => $range[1] ];
        $res['summary'] = $stat->summary();
        $res['sources'] = $stat->ips();
        $res['organizations'] = $stat->organizations();
        return $res;
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

        $res = [];
        $res[] = '# Domain: ' . $this->domain->fqdn();

        $res[] = ' Range: ' . $rdata['range'];
        $res[] = '';

        $res[] = '## Summary';
        $total = $rdata['summary']['total'];
        $res[] = sprintf(' Total: %d', $total);
        $res[] = sprintf(' DKIM or SPF aligned: %s', self::num2percent($rdata['summary']['aligned'], $total));
        $res[] = sprintf(' Not aligned: %s', self::num2percent($rdata['summary']['n_aligned'], $total));
        $res[] = sprintf(' Organizations: %d', $rdata['summary']['organizations']);
        $res[] = '';

        if (count($rdata['sources']) > 0) {
            $res[] = '## Sources';
            $res[] = sprintf(
                ' %-25s %13s %13s %13s',
                '',
                'Total',
                'SPF aligned',
                'DKIM aligned'
            );
            foreach ($rdata['sources'] as &$it) {
                $total    = $it['emails'];
                $spf_a    = $it['spf_aligned'];
                $dkim_a   = $it['dkim_aligned'];
                $spf_str  = self::num2percent($spf_a, $total);
                $dkim_str = self::num2percent($dkim_a, $total);
                $res[] = sprintf(
                    ' %-25s %13d %13s %13s',
                    $it['ip'],
                    $total,
                    $spf_str,
                    $dkim_str
                );
            }
            unset($it);
            $res[] = '';
        }

        if (count($rdata['organizations']) > 0) {
            $res[] = '## Organizations';

            $org_len = 15;
            foreach ($rdata['organizations'] as &$org) {
                $org_len = max($org_len, mb_strlen($org['name']));
            }
            unset($org);

            $org_len = min($org_len, 55);
            $res[] = sprintf(" %-{$org_len}s %8s %8s", '', 'emails', 'reports');
            $frm_str = " %-{$org_len}s %8d %8d";
            foreach ($rdata['organizations'] as &$org) {
                $res[] = sprintf(
                    $frm_str,
                    trim($org['name']),
                    $org['emails'],
                    $org['reports']
                );
            }
            unset($org);
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

        $add_red = function (int $num) {
            return $num > 0 ? 'color:#f00;' : '';
        };
        $add_green = function (int $num) {
            return $num > 0 ? 'color:#080;' : '';
        };

        $rdata = $this->reportData();
        $res = [];
        $res[] = "<h2 {$h2a}>Domain: " . htmlspecialchars($this->domain->fqdn()) . '</h2>';
        $res[] = '<p style="margin:0;">Range: ' . htmlspecialchars($rdata['range']) . '</p>';

        $res[] = "<h3 {$h2a}>Summary</h3>";
        $res[] = '<table>';
        $total = $rdata['summary']['total'];
        $a_cnt = $rdata['summary']['aligned'];
        $n_cnt = $rdata['summary']['n_aligned'];
        $res[] = " <tr><td>Total: </td><td style=\"{$d1s}\">" . $total . '</td></tr>';
        $color = $add_green($a_cnt);
        $res[] = " <tr><td>DKIM or SPF aligned: </td><td style=\"{$d1s}{$color}\">{$a_cnt}</td></tr>";
        $color = $add_red($n_cnt);
        $res[] = " <tr><td>Not aligned: </td><td style=\"{$d1s}{$color}\">{$n_cnt}</td></tr>";
        $res[] = " <tr><td>Organizations: </td><td style=\"{$d1s}\">" .
                 $rdata['summary']['organizations'] .
                 '</td></tr>';
        $res[] = '</table>';

        $rs2 = 'rowspan="2"';
        $cs3 = 'colspan="3"';
        $s_cnt = count($rdata['sources']);
        if ($s_cnt > 0) {
            $res[] = "<h3 {$h2a}>Sources</h3>";
            $res[] = "<table {$t2a}>";
            $res[] = " <caption {$c1a}>Total records: {$s_cnt}</caption>";
            $res[] = ' <thead>';
            $style = "style=\"{$d3s}{$d5s}\"";
            $res[] = "  <tr><th {$rs2} {$style}>IP address</th><th {$rs2} {$style}>Email volume</th>" .
                     "<th {$cs3} {$style}>SPF</th><th {$cs3} {$style}>DKIM</th></tr>";
            $style = "style=\"{$d2s}{$d3s}{$d5s}\"";
            $res[] = "  <tr><th {$style}>pass</th><th {$style}>fail</th><th {$style}>rate</th>" .
                     "<th {$style}>pass</th><th {$style}>fail</th><th {$style}>rate</th></tr>";
            $res[] = ' </thead>';
            $res[] = ' <tbody>';
            foreach ($rdata['sources'] as &$row) {
                $ip     = htmlspecialchars($row['ip']);
                $total  = $row['emails'];
                $spf_a  = $row['spf_aligned'];
                $spf_n  = $total - $spf_a;
                $spf_p  = sprintf('%.0f%%', $spf_a / $total * 100);
                $dkim_a = $row['dkim_aligned'];
                $dkim_n = $total - $dkim_a;
                $dkim_p = sprintf('%.0f%%', $dkim_a / $total * 100);
                $style  = "style=\"{$d3s}{$d5s}";

                $row_str  = "  <tr><td {$style}\">{$ip}</td><td {$style}{$d4s}\">{$total}</td>";
                $row_str .= "<td {$style}{$d4s}{$add_green($spf_a)}\">{$spf_a}</td>";
                $row_str .= "<td {$style}{$d4s}{$add_red($spf_n)}\">{$spf_n}</td>";
                $row_str .= "<td {$style}{$d4s}\">{$spf_p}</td>";
                $row_str .= "<td {$style}{$d4s}{$add_green($dkim_a)}\">{$dkim_a}</td>";
                $row_str .= "<td {$style}{$d4s}{$add_red($dkim_n)}\">{$dkim_n}</td>";
                $row_str .= "<td {$style}{$d4s}\">{$dkim_p}</td>";
                $res[] = $row_str . '</tr>';
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
            $res[] = "  <tr><th {$style}>Name</th><th {$style}>Emails</th><th {$style}>Reports</th></tr>";
            $res[] = ' </thead>';
            $res[] = ' <tbody>';
            foreach ($rdata['organizations'] as &$row) {
                $name   = htmlspecialchars($row['name']);
                $style2 = "style=\"{$d3s}{$d4s}{$d5s}\"";
                $res[] = "  <tr><td {$style}>{$name}</td>" .
                         "<td {$style2}>{$row['emails']}</td>" .
                         "<td {$style2}>{$row['reports']}</td></tr>";
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
        $res[] = sprintf('DKIM or SPF aligned: %s', self::num2percent($rdata['summary']['aligned'], $total));
        $res[] = sprintf('Not aligned: %s', self::num2percent($rdata['summary']['n_aligned'], $total));
        $res[] = sprintf('Organizations: %d', $rdata['summary']['organizations']);
        $res[] = '';

        if (count($rdata['sources']) > 0) {
            $res[] = 'Sources';
            $res[] = [ '', 'Total', 'SPF aligned', 'DKIM aligned' ];
            foreach ($rdata['sources'] as &$it) {
                $total    = $it['emails'];
                $spf_a    = $it['spf_aligned'];
                $dkim_a   = $it['dkim_aligned'];
                $spf_str  = self::num2percent($spf_a, $total);
                $dkim_str = self::num2percent($dkim_a, $total);
                $res[] = [ $it['ip'], $total, $spf_str, $dkim_str ];
            }
            unset($it);
            $res[] = '';
        }

        if (count($rdata['organizations']) > 0) {
            $res[] = 'Organizations';

            $res[] = [ '', 'emails', 'reports' ];
            foreach ($rdata['organizations'] as &$org) {
                $res[] = [ trim($org['name']), $org['emails'], $org['reports'] ];
            }
            unset($org);
            $res[] = '';
        }
        return Common::arrayToCSV($res);
    }

    /**
     * Returns the percentage with the original number. If $per is 0 then '0' is returned.
     *
     * @param int $per  Value
     * @param int $cent Divisor for percentage calculation
     *
     * @return string
     */
    private static function num2percent(int $per, int $cent): string
    {
        if (!$per) {
            return '0';
        }
        return sprintf('%.0f%%(%d)', $per / $cent * 100, $per);
    }

    /**
     * Generates the report if it has not already been done
     *
     * @return void
     */
    private function ensureData(): void
    {
        if (!$this->domain) {
            throw new LogicException('No one domain was specified');
        }

        if (!$this->stat) {
            switch ($this->period) {
                case self::LAST_WEEK:
                    $this->stat = Statistics::lastWeek($this->domain);
                    break;
                case self::LAST_MONTH:
                    $this->stat = Statistics::lastMonth($this->domain);
                    break;
                default:
                    $this->stat = Statistics::lastNDays($this->domain, $this->period);
                    break;
            }
        }
    }

    /**
     * Returns prepared data for the report
     *
     * @return array
     */
    private function reportData(): array
    {
        $this->ensureData();

        $stat  = $this->stat;
        $rdata = [];
        $range = $stat->range();
        $cyear = (new \Datetime())->format('Y');
        $dform = ($range[0]->format('Y') !== $cyear || $range[1]->format('Y') !== $cyear) ? 'M d Y' : 'M d';
        $rdata['range'] = $range[0]->format($dform) . ' - ' . $range[1]->format($dform);

        $summ      = $stat->summary();
        $total     = $summ['emails']['total'];
        $aligned   = $summ['emails']['dkim_spf_aligned'] +
                     $summ['emails']['dkim_aligned'] +
                     $summ['emails']['spf_aligned'];
        $n_aligned = $total - $aligned;
        $rdata['summary'] = [
            'total'         => $total,
            'organizations' => $summ['organizations']
        ];
        if ($total > 0) {
            $rdata['summary']['aligned']   = $aligned;
            $rdata['summary']['n_aligned'] = $n_aligned;
        } else {
            $rdata['summary']['aligned']   = $aligned;
            $rdata['summary']['n_aligned'] = $aligned;
        }

        $rdata['sources'] = $stat->ips();

        $rdata['organizations'] = $stat->organizations();

        return $rdata;
    }
}
