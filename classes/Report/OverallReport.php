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
 * This file contains OverallReport class
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Report;

use Liuch\DmarcSrg\Common;
use Liuch\DmarcSrg\TextTable;

/**
 * This class is for generating an overall report on accumulated data
 */
class OverallReport
{
    private $rows = [];

    /**
     * Adds a report row
     *
     * @param array $data Report data array
     *
     * @return void
     */
    public function appendData(array $data): void
    {
        $this->rows[] = $data;
    }

    /**
     * Returns the report data as an array
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->rows;
    }

    /**
     * Returns the report as an array of text strings
     *
     * @return array
     */
    public function text(): array
    {
        $res = [ '# Overall by domains', '' ];

        $table = new TextTable([ '', 'Emails', 'SPF only', 'DKIM only', 'Not aligned', 'Quar+Rej' ]);
        $table->setMinColumnsWidth([ 15, 6, 8, 9, 11, 8 ])->setBorders('', '', '')
            ->setColumnAlignment(4, 'right')->setColumnAlignment(5, 'right');
        foreach ($this->rows as &$row) {
            $total = $row['total'];
            $d_aln = $row['dkim_aligned'];
            $s_aln = $row['spf_aligned'];
            $q_dis = $row['quarantined'];
            $r_dis = $row['rejected'];
            $n_aln = $total - $row['dkim_spf_aligned'] - $d_aln - $s_aln;
            if ($q_dis || $r_dis) {
                $s_dis = Common::num2percent($q_dis + $r_dis, $total, false) . "({$q_dis}+{$r_dis})";
            } else {
                $s_dis = '0';
            }
            $table->appendRow([
                $row['fqdn'], $total, $s_aln, $d_aln, Common::num2percent($n_aln, $total, true), $s_dis
            ]);
        }
        unset($row);
        foreach ($table->toArray() as $line) {
            $res[] = " $line";
        }
        $res[] = '';

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

        $r_cnt = count($this->rows);
        $res[] = "<h2 {$h2a}>Overall by domains</h2>";
        $res[] = "<table {$t2a}>";
        $res[] = " <caption {$c1a}>Total records: {$r_cnt}</caption>";
        $res[] = ' <thead>';
        $style = "style=\"{$d3s}{$d5s}\"";
        $res[] = "  <tr><th {$rs2} {$style}>Name</th><th {$rs2} {$style}>Emails</th>" .
                 "<th {$cs2} {$style}>Partial aligned</th><th {$rs2} {$style}>Not aligned</th>" .
                 "<th {$cs2} {$style}>Disposition</th></tr>";
        $res[] = "<th {$style}>SPF only</th><th {$style}>DKIM only</th>" .
                 "<th {$style}>quar+rej</th><th {$style}>fail rate</th></tr>";
        $res[] = ' </thead>';
        $res[] = ' <tbody>';
        $style = "style=\"{$d3s}{$d5s}";
        foreach ($this->rows as &$row) {
            $name  = htmlspecialchars(trim($row['fqdn']));
            $total = $row['total'];
            $f_aln = $row['dkim_spf_aligned'];
            $d_aln = $row['dkim_aligned'];
            $s_aln = $row['spf_aligned'];
            $n_aln = $total - $f_aln - $d_aln - $s_aln;
            $q_dis = $row['quarantined'];
            $r_dis = $row['rejected'];
            $s_dis = ($q_dis || $r_dis) ? "{$q_dis}+{$r_dis}" : '0';
            $res[] = "  <tr><td {$style}\">{$name}</td><td {$style}{$d4s}\">{$total}</td>" .
                     "<td {$style}{$d4s}\">{$s_aln}</td><td {$style}{$d4s}\">{$d_aln}</td>" .
                     "<td {$style}{$d4s}{$get_color('red', $n_aln)}\">{$n_aln}</td>" .
                     "<td {$style}{$d4s}{$get_color('red', $q_dis + $r_dis)}\">{$s_dis}</td>" .
                     "<td {$style}{$d4s}\">" . Common::num2percent($q_dis + $r_dis, $total, false) .
                     '</td></tr>';
        }
        unset($row);
        $res[] = ' </tbody>';
        $res[] = '</table>';

        return $res;
    }

    /**
     * Returns the report data in CSV format
     *
     * @return string
     */
    public function csv(): string
    {
        $res = [ 'Overall by domains' ];
        $res[] = '';
        $res[] = [ '', 'Emails', 'SPF only', 'DKIM only', 'Not aligned', 'Quar+Rej' ];
        foreach ($this->rows as &$row) {
            $total = $row['total'];
            $d_aln = $row['dkim_aligned'];
            $s_aln = $row['spf_aligned'];
            $q_dis = $row['quarantined'];
            $r_dis = $row['rejected'];
            $n_aln = $total - $row['dkim_spf_aligned'] - $d_aln - $s_aln;
            if ($q_dis || $r_dis) {
                $s_dis = Common::num2percent($q_dis + $r_dis, $total, false) . "({$q_dis}+{$r_dis})";
            } else {
                $s_dis = '0';
            }
            $res[] = [
                trim($row['fqdn']), $total, $s_aln, $d_aln, Common::num2percent($n_aln, $total, true), $s_dis
            ];
        }
        unset($row);
        return Common::arrayToCSV($res);
    }
}
