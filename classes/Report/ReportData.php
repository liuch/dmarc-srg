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

use Liuch\DmarcSrg\DateTime;
use Liuch\DmarcSrg\Exception\RuntimeException;

class ReportData
{
    public static $rep_data = null;
    public static $tag_id   = null;

    public static function fromXmlFile($fd)
    {
        self::$tag_id = '<root>';
        self::$rep_data = [ 'records' => [] ];

        $parser = xml_parser_create();
        xml_set_element_handler(
            $parser,
            'Liuch\DmarcSrg\Report\ReportData::xmlStartTag',
            'Liuch\DmarcSrg\Report\ReportData::xmlEndTag'
        );
        xml_set_character_data_handler($parser, 'Liuch\DmarcSrg\Report\ReportData::xmlTagData');
        xml_set_external_entity_ref_handler($parser, function () {
            throw new RuntimeException('The XML document has an external entity!');
        });
        try {
            while ($file_data = fread($fd, 4096)) {
                if (!xml_parse($parser, $file_data, feof($fd))) {
                    throw new RuntimeException('XML error!');
                }
            }
        } finally {
            xml_parser_free($parser);
            unset($parser);
        }
        return self::$rep_data;
    }

    public static function xmlStartTag($parser, $name, $attrs)
    {
        self::xmlEnterTag($name);

        switch (self::$tag_id) {
            case 'rec':
                self::$rep_data['records'][] = [];
                break;
            case 'error_string':
                if (!isset(self::$rep_data['error_string'])) {
                    self::$rep_data['error_string'] = [];
                }
                break;
            case 'reason':
                $idx = count(self::$rep_data['records']) - 1;
                if (!isset(self::$rep_data['records'][$idx]['reason'])) {
                    self::$rep_data['records'][$idx]['reason'] = [];
                }
                self::$report_tags['reason']['tmp_data'] = [];
                break;
            case 'dkim_auth':
                $idx = count(self::$rep_data['records']) - 1;
                if (!isset(self::$rep_data['records'][$idx]['dkim_auth'])) {
                    self::$rep_data['records'][$idx]['dkim_auth'] = [];
                }
                self::$report_tags['dkim_auth']['tmp_data'] = [];
                break;
            case 'spf_auth':
                $idx = count(self::$rep_data['records']) - 1;
                if (!isset(self::$rep_data['records'][$idx]['spf_auth'])) {
                    self::$rep_data['records'][$idx]['spf_auth'] = [];
                }
                self::$report_tags['spf_auth']['tmp_data'] = [];
                break;
        }
    }

    public static function xmlEndTag($parser, $name)
    {
        switch (self::$tag_id) {
            case 'reason':
                $idx = count(self::$rep_data['records']) - 1;
                self::$rep_data['records'][$idx]['reason'][] = self::$report_tags['reason']['tmp_data'];
                unset(self::$report_tags['reason']['tmp_data']);
                break;
            case 'dkim_auth':
                $idx = count(self::$rep_data['records']) - 1;
                self::$rep_data['records'][$idx]['dkim_auth'][] = self::$report_tags['dkim_auth']['tmp_data'];
                unset(self::$report_tags['dkim_auth']['tmp_data']);
                break;
            case 'spf_auth':
                $idx = count(self::$rep_data['records']) - 1;
                self::$rep_data['records'][$idx]['spf_auth'][] = self::$report_tags['spf_auth']['tmp_data'];
                unset(self::$report_tags['spf_auth']['tmp_data']);
                break;
            case 'feedback':
                // Set the default value if it's necessary and there is no data
                foreach (self::$report_tags as $tag_id => &$tag_data) {
                    if (array_key_exists('default', $tag_data)) { // not isset() because of null values
                        if (isset($tag_data['header']) && $tag_data['header']) {
                            if (!isset(self::$rep_data[$tag_id])) {
                                self::$rep_data[$tag_id] = $tag_data['default'];
                            }
                        } else {
                            foreach (self::$rep_data['records'] as $idx => &$rec_val) {
                                if (!isset($rec_val[$tag_id])) {
                                    $rec_val[$tag_id] = $tag_data['default'];
                                }
                            }
                            unset($rec_val);
                        }
                    }
                }
                unset($tag_data);
                $b_ts = intval(self::$rep_data['begin_time']);
                $e_ts = intval(self::$rep_data['end_time']);
                self::$rep_data['begin_time'] = new DateTime('@' . ($b_ts < 0 ? 0 : $b_ts));
                self::$rep_data['end_time']   = new DateTime('@' . ($e_ts < 0 ? 0 : $e_ts));
                foreach (self::$rep_data['records'] as &$rec_data) {
                    $rec_data['rcount'] = intval($rec_data['rcount']);
                }
                unset($rec_data);
                break;
        }
        self::xmlLeaveTag();
    }

    public static function xmlTagData($parser, $data)
    {
        switch (self::$tag_id) {
            case 'error_string':
                if (self::$tag_id === 'error_string') {
                    self::$rep_data['error_string'][] = $data;
                }
                break;
            case 'reason_type':
                self::$report_tags['reason']['tmp_data']['type'] = $data;
                break;
            case 'reason_comment':
                self::$report_tags['reason']['tmp_data']['comment'] = $data;
                break;
            case 'dkim_domain':
                self::$report_tags['dkim_auth']['tmp_data']['domain'] = $data;
                break;
            case 'dkim_selector':
                self::$report_tags['dkim_auth']['tmp_data']['selector'] = $data;
                break;
            case 'dkim_result':
                self::$report_tags['dkim_auth']['tmp_data']['result'] = $data;
                break;
            case 'dkim_human_result':
                self::$report_tags['dkim_auth']['tmp_data']['human_result'] = $data;
                break;
            case 'spf_domain':
                self::$report_tags['spf_auth']['tmp_data']['domain'] = $data;
                break;
            case 'spf_scope':
                self::$report_tags['spf_auth']['tmp_data']['scope'] = $data;
                break;
            case 'spf_result':
                self::$report_tags['spf_auth']['tmp_data']['result'] = $data;
                break;
            default:
                if (!isset(self::$report_tags[self::$tag_id]['children'])) {
                    if (isset(self::$report_tags[self::$tag_id]['header']) &&
                        self::$report_tags[self::$tag_id]['header']
                    ) {
                        if (!isset(self::$rep_data[self::$tag_id])) {
                            self::$rep_data[self::$tag_id] = $data;
                        } else {
                            self::$rep_data[self::$tag_id] .= $data;
                        }
                    } else {
                        $last_idx = count(self::$rep_data['records']) - 1;
                        $last_rec =& self::$rep_data['records'][$last_idx];
                        if (!isset($last_rec[self::$tag_id])) {
                            $last_rec[self::$tag_id] = $data;
                        } else {
                            $last_rec[self::$tag_id] .= $data;
                        }
                        unset($last_rec);
                    }
                }
        }
    }

    public static function xmlEnterTag($name)
    {
        if (!isset(self::$report_tags[self::$tag_id]['children']) ||
            !isset(self::$report_tags[self::$tag_id]['children'][$name])
        ) {
                throw new RuntimeException("Unknown tag: {$name}");
        }

        self::$tag_id = self::$report_tags[self::$tag_id]['children'][$name];
    }

    public static function xmlLeaveTag()
    {
        self::$tag_id = self::$report_tags[self::$tag_id]['parent'];
    }

    public static $report_tags = [
        '<root>' => [
            'children' => [
                'FEEDBACK' => 'feedback'
            ]
        ],
        'feedback' => [
            'parent' => '<root>',
            'children' => [
                'VERSION'          => 'ver',
                'REPORT_METADATA'  => 'rmd',
                'POLICY_PUBLISHED' => 'p_p',
                'RECORD'           => 'rec'
            ]
        ],
        'ver' => [ 'parent' => 'feedback', 'header' => true, 'default' => null ],
        'rmd' => [
            'parent' => 'feedback',
            'children' => [
                'ORG_NAME'           => 'org',
                'EMAIL'              => 'email',
                'EXTRA_CONTACT_INFO' => 'extra_contact_info',
                'REPORT_ID'          => 'external_id',
                'DATE_RANGE'         => 'd_range',
                'ERROR'              => 'error_string'
            ]
        ],
        'p_p' => [
            'parent' => 'feedback',
            'children' => [
                'DOMAIN' => 'domain',
                'ADKIM'  => 'policy_adkim',
                'ASPF'   => 'policy_aspf',
                'P'      => 'policy_p',
                'SP'     => 'policy_sp',
                'PCT'    => 'policy_pct',
                'FO'     => 'policy_fo'
            ]
        ],
        'rec' => [
            'parent' => 'feedback',
            'children' => [
                'ROW'          => 'row',
                'IDENTIFIERS'  => 'ident',
                'AUTH_RESULTS' => 'au_res'
            ]
        ],
        'org'                => [ 'parent' => 'rmd', 'header' => true ],
        'email'              => [ 'parent' => 'rmd', 'header' => true, 'default' => null ],
        'extra_contact_info' => [ 'parent' => 'rmd', 'header' => true, 'default' => null ],
        'external_id'        => [ 'parent' => 'rmd', 'header' => true ],
        'd_range'            => [
            'parent' => 'rmd',
            'children' => [
                'BEGIN' => 'begin_time',
                'END' => 'end_time'
            ]
        ],
        'error_string' => [ 'parent' => 'rmd', 'header' => true, 'default' => null ],
        'begin_time'   => [ 'parent' => 'd_range', 'header' => true ],
        'end_time'     => [ 'parent' => 'd_range', 'header' => true ],
        'domain'       => [ 'parent' => 'p_p', 'header' => true ],
        'policy_adkim' => [ 'parent' => 'p_p', 'header' => true, 'default' => null ],
        'policy_aspf'  => [ 'parent' => 'p_p', 'header' => true, 'default' => null ],
        'policy_p'     => [ 'parent' => 'p_p', 'header' => true, 'default' => null ],
        'policy_sp'    => [ 'parent' => 'p_p', 'header' => true, 'default' => null ],
        'policy_pct'   => [ 'parent' => 'p_p', 'header' => true, 'default' => null ],
        'policy_fo'    => [ 'parent' => 'p_p', 'header' => true, 'default' => null ],
        'row' => [
            'parent' => 'rec',
            'children' => [
                'SOURCE_IP' => 'ip',
                'COUNT'     => 'rcount',
                'POLICY_EVALUATED' => 'p_e'
            ]
        ],
        'ident' => [
            'parent' => 'rec',
            'children' => [
                'ENVELOPE_TO'   => 'envelope_to',
                'ENVELOPE_FROM' => 'envelope_from',
                'HEADER_FROM'   => 'header_from'
            ]
        ],
        'au_res' => [
            'parent' => 'rec',
            'children' => [
                'DKIM' => 'dkim_auth',
                'SPF'  => 'spf_auth'
            ]
        ],
        'ip'     => [ 'parent' => 'row' ],
        'rcount' => [ 'parent' => 'row' ],
        'p_e'    => [
            'parent' => 'row',
            'children' => [
                'DISPOSITION' => 'disposition',
                'DKIM'        => 'dkim_align',
                'SPF'         => 'spf_align',
                'REASON'      => 'reason'
            ]
        ],
        'disposition' => [ 'parent' => 'p_e' ],
        'dkim_align'  => [ 'parent' => 'p_e' ],
        'spf_align'   => [ 'parent' => 'p_e' ],
        'reason'  => [
            'parent'   => 'p_e',
            'default'  => null,
            'children' => [
                'TYPE'    => 'reason_type',
                'COMMENT' => 'reason_comment'
            ]
        ],
        'envelope_to'   => [ 'parent' => 'ident', 'default' => null ],
        'envelope_from' => [ 'parent' => 'ident', 'default' => null ],
        'header_from'   => [ 'parent' => 'ident', 'default' => null ],
        'dkim_auth'     => [
            'parent'   => 'au_res',
            'default'  => null,
            'children' => [
                'DOMAIN'       => 'dkim_domain',
                'SELECTOR'     => 'dkim_selector',
                'RESULT'       => 'dkim_result',
                'HUMAN_RESULT' => 'dkim_human_result'
            ]
        ],
        'spf_auth' => [
            'parent'   => 'au_res',
            'default'  => null,
            'children' => [
                'DOMAIN' => 'spf_domain',
                'SCOPE'  => 'spf_scope',
                'RESULT' => 'spf_result'
            ]
        ],
        'reason_type'       => [ 'parent' => 'reason' ],
        'reason_comment'    => [ 'parent' => 'reason' ],
        'dkim_domain'       => [ 'parent' => 'dkim_auth' ],
        'dkim_selector'     => [ 'parent' => 'dkim_auth' ],
        'dkim_result'       => [ 'parent' => 'dkim_auth' ],
        'dkim_human_result' => [ 'parent' => 'dkim_auth' ],
        'spf_domain'        => [ 'parent' => 'spf_auth' ],
        'spf_scope'         => [ 'parent' => 'spf_auth' ],
        'spf_result'        => [ 'parent' => 'spf_auth' ]
    ];
}
