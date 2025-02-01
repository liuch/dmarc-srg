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
    private $rep_data    = null;
    private $tag_id      = null;
    private $skip_depth  = 0;
    private $strict_mode = false;

    private function __construct(bool $strict = false)
    {
        $this->strict_mode = $strict;
    }

    public static function fromArray(array $data, bool $strict = false)
    {
        $new_data = [];
        self::copyFields(self::$fields, $data, $new_data);
        self::copySubdata(self::$dfields, $data, $new_data, 'date');
        self::copySubdata(self::$pfields, $data, $new_data, 'policy');
        if (isset($data['records']) && gettype($data['records']) === 'array') {
            $new_data['records'] = [];
            foreach ($data['records'] as &$rec) {
                $new_rec = [];
                self::copyFields(self::$rfields, $rec, $new_rec);
                $new_data['records'][] = $new_rec;
            }
            unset($rec);
        }
        $rdata = new self($strict);
        $rdata->rep_data = $new_data;
        return $rdata;
    }

    public static function fromXmlFile($fd, $strict = false)
    {
        $rdata = new self($strict);
        $rdata->tag_id = '<root>';
        $rdata->rep_data = [ 'date' => [], 'policy' => [], 'records' => [] ];

        $parser = xml_parser_create();
        xml_set_element_handler($parser, [ $rdata, 'xmlStartTag' ], [ $rdata, 'xmlEndTag' ]);
        xml_set_character_data_handler($parser, [ $rdata, 'xmlTagData' ]);
        xml_set_external_entity_ref_handler($parser, function () {
            throw new RuntimeException('The XML document has an external entity!');
        });
        try {
            while ($file_data = fread($fd, 4096)) {
                if (!xml_parse($parser, $file_data, feof($fd))) {
                    $pc = xml_get_error_code($parser);
                    $error_str  = 'XML error!' . PHP_EOL;
                    $error_str .= 'Parser code: ' . $pc . PHP_EOL;
                    $error_str .= 'Parser message: ' . xml_error_string($pc) . PHP_EOL;
                    $error_str .= 'Line: ' . xml_get_current_line_number($parser);
                    $error_str .= '; Column: ' . xml_get_current_column_number($parser) . PHP_EOL;
                    throw new RuntimeException('Incorrect XML report file', -1, new RuntimeException($error_str));
                }
            }
        } finally {
            xml_parser_free($parser);
            unset($parser);
        }
        return $rdata;
    }

    public function toArray(): array
    {
        return $this->rep_data;
    }

    public function &__get(string $name)
    {
        if (!array_key_exists($name, self::$fields)) {
            throw new RuntimeException('Getting an unknown report property: ' . $name);
        }
        if (!array_key_exists($name, $this->rep_data)) {
            throw new RuntimeException('Getting an undefined report property: ' . $name);
        }
        return $this->rep_data[$name];
    }

    public function __set(string $name, $value): void
    {
        if (!array_key_exists($name, self::$fields)) {
            throw new RuntimeException('Accessing an unknown report property: ' . $name);
        }
        $this->rep_data[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->rep_data);
    }

    /**
     * Checks report data for correctness and completeness
     *
     * @return bool
     */
    public function isValid(): bool
    {
        if (!self::checkRow($this->rep_data, self::$fields) || count($this->rep_data['records']) === 0) {
            return false;
        }
        if (!self::checkRow($this->rep_data['date'], self::$dfields)) {
            return false;
        }
        if (!self::checkRow($this->rep_data['policy'], self::$pfields)) {
            return false;
        }
        foreach ($this->rep_data['records'] as &$rec) {
            if (gettype($rec) !== 'array' || !self::checkRow($rec, self::$rfields)) {
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

    private function xmlStartTag($parser, string $name, array $attributes): void
    {
        if ($this->skip_depth || !$this->xmlEnterTag($name)) {
            ++$this->skip_depth;
            return;
        }

        switch ($this->tag_id) {
            case 'rec':
                $this->rep_data['records'][] = [];
                break;
            case 'error_string':
                if (!isset($this->rep_data['error_string'])) {
                    $this->rep_data['error_string'] = [];
                }
                break;
            case 'reason':
            case 'dkim_auth':
            case 'spf_auth':
                $idx = array_key_last($this->rep_data['records']);
                if (!isset($this->rep_data['records'][$idx][$this->tag_id])) {
                    $this->rep_data['records'][$idx][$this->tag_id] = [];
                }
                $this->report_tags[$this->tag_id]['tmp_data'] = [];
                break;
        }
    }

    private function xmlEndTag($parser, string $name): void
    {
        if ($this->skip_depth) {
            --$this->skip_depth;
            return;
        }

        switch ($this->tag_id) {
            case 'reason':
            case 'dkim_auth':
            case 'spf_auth':
                $idx = array_key_last($this->rep_data['records']);
                $this->rep_data['records'][$idx][$this->tag_id][] = $this->report_tags[$this->tag_id]['tmp_data'];
                unset($this->report_tags[$this->tag_id]['tmp_data']);
                break;
            case 'feedback':
                // Set the default value if it's necessary and there is no data
                foreach ($this->report_tags as $tag_id => &$tag_data) {
                    if (array_key_exists('default', $tag_data)) { // not isset() because of null values
                        $def = $tag_data['default'];
                        $key = $tag_data['key'] ?? $tag_id;
                        $ptr = null;
                        switch ($tag_data['type'] ?? '') {
                            case 'M': // metadata
                                $ptr = &$this->rep_data;
                                break;
                            case 'D': // data range
                                $ptr = &$this->rep_data['date'];
                                break;
                            case 'P': // policy
                                $ptr = &$this->rep_data['policy'];
                                break;
                            default: // record
                                foreach ($this->rep_data['records'] as &$rec_val) {
                                    if (!isset($rec_val[$key])) {
                                        $rec_val[$key] = $def;
                                    }
                                }
                                unset($rec_val);
                        }
                        if ($ptr) {
                            if (!isset($ptr[$key])) {
                                $ptr[$key] = $def;
                            }
                            unset($ptr);
                        }
                    }
                }
                unset($tag_data);
                $b_ts = intval($this->rep_data['date']['begin'] ?? 0);
                $e_ts = intval($this->rep_data['date']['end'] ?? 0);
                $this->rep_data['date']['begin'] = new DateTime('@' . ($b_ts < 0 ? 0 : $b_ts));
                $this->rep_data['date']['end']   = new DateTime('@' . ($e_ts < 0 ? 0 : $e_ts));
                foreach ($this->rep_data['records'] as &$rec_data) {
                    $rec_data['count'] = intval($rec_data['rcount']);
                }
                unset($rec_data);
                break;
        }
        $this->xmlLeaveTag();
    }

    private function xmlTagData($parser, $data): void
    {
        if ($this->skip_depth) {
            return;
        }

        switch ($this->tag_id) {
            case 'error_string':
                if ($this->tag_id === 'error_string') {
                    $this->rep_data['error_string'][] = $data;
                }
                break;
            case 'reason_type':
                $this->report_tags['reason']['tmp_data']['type'] = $data;
                break;
            case 'reason_comment':
                $this->report_tags['reason']['tmp_data']['comment'] = $data;
                break;
            case 'dkim_domain':
                $this->report_tags['dkim_auth']['tmp_data']['domain'] = $data;
                break;
            case 'dkim_selector':
                $this->report_tags['dkim_auth']['tmp_data']['selector'] = $data;
                break;
            case 'dkim_result':
                $this->report_tags['dkim_auth']['tmp_data']['result'] = $data;
                break;
            case 'dkim_human_result':
                $this->report_tags['dkim_auth']['tmp_data']['human_result'] = $data;
                break;
            case 'spf_domain':
                $this->report_tags['spf_auth']['tmp_data']['domain'] = $data;
                break;
            case 'spf_scope':
                $this->report_tags['spf_auth']['tmp_data']['scope'] = $data;
                break;
            case 'spf_result':
                $this->report_tags['spf_auth']['tmp_data']['result'] = $data;
                break;
            default:
                $t_id = $this->tag_id;
                if (!isset($this->report_tags[$t_id]['children'])) {
                    $key = $this->report_tags[$t_id]['key'] ?? $t_id;
                    switch ($this->report_tags[$t_id]['type'] ?? '') {
                        case 'M': // metadata
                            $ptr = &$this->rep_data;
                            break;
                        case 'D': // date range
                            $ptr = &$this->rep_data['date'];
                            break;
                        case 'P': // policy
                            $ptr = &$this->rep_data['policy'];
                            break;
                        default: // record
                            $ptr = &$this->rep_data['records'][array_key_last($this->rep_data['records'])];
                            break;
                    }
                    if (isset($ptr[$key])) {
                        $ptr[$key] .= $data;
                    } else {
                        $ptr[$key] = $data;
                    }
                    unset($ptr);
                }
        }
    }

    private function xmlEnterTag(string $name): bool
    {
        if (!isset($this->report_tags[$this->tag_id]['children'][$name])) {
            if ($this->strict_mode) {
                throw new RuntimeException("Unknown tag: {$name}");
            }
            return false;
        }

        $this->tag_id = $this->report_tags[$this->tag_id]['children'][$name];
        return true;
    }

    private function xmlLeaveTag(): void
    {
        $this->tag_id = $this->report_tags[$this->tag_id]['parent'];
    }

    private static function copyFields(array &$flist, array &$sou_data, array &$des_data): void
    {
        foreach ($flist as $fn => &$fp) {
            $ft = $fp['type'];
            if ($ft !== 'array' && array_key_exists($fn, $sou_data) && gettype($sou_data[$fn]) === $ft) {
                $des_data[$fn] = $sou_data[$fn];
            }
        }
        unset($fp);
    }

    private static function copySubdata(array &$flist, array &$sou_data, array &$des_data, string $key): void
    {
        if (isset($sou_data[$key]) && gettype($sou_data[$key]) === 'array') {
            $des_data[$key] = [];
            self::copyFields($flist, $sou_data[$key], $des_data[$key]);
        }
    }

    private static $fields = [
        'domain'             => [ 'required' => true,  'type' => 'string' ],
        'date'               => [ 'required' => true,  'type' => 'array'  ],
        'org_name'           => [ 'required' => true,  'type' => 'string' ],
        'report_id'          => [ 'required' => true,  'type' => 'string' ],
        'email'              => [ 'required' => false, 'type' => 'string' ],
        'extra_contact_info' => [ 'required' => false, 'type' => 'string' ],
        'error_string'       => [ 'required' => false, 'type' => 'array'  ],
        'policy'             => [ 'required' => true,  'type' => 'array'  ],
        'loaded_time'        => [ 'required' => false, 'type' => 'object' ],
        'records'            => [ 'required' => true,  'type' => 'array'  ]
    ];
    private static $dfields = [
        'begin' => [ 'required' => true,  'type' => 'object' ],
        'end'   => [ 'required' => true,  'type' => 'object' ],
    ];
    private static $pfields = [
        'adkim' => [ 'required' => false, 'type' => 'string' ],
        'aspf'  => [ 'required' => false, 'type' => 'string' ],
        'p'     => [ 'required' => false, 'type' => 'string' ],
        'sp'    => [ 'required' => false, 'type' => 'string' ],
        'np'    => [ 'required' => false, 'type' => 'string' ],
        'pct'   => [ 'required' => false, 'type' => 'string' ],
        'fo'    => [ 'required' => false, 'type' => 'string' ],
    ];
    private static $rfields = [
        'ip'            => [ 'required' => true,  'type' => 'string'  ],
        'count'         => [ 'required' => true,  'type' => 'integer' ],
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
    private $report_tags = [
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
        'ver' => [ 'parent' => 'feedback', 'type' => 'M', 'default' => null ],
        'rmd' => [
            'parent' => 'feedback',
            'children' => [
                'ORG_NAME'           => 'org_name',
                'EMAIL'              => 'email',
                'EXTRA_CONTACT_INFO' => 'extra_contact_info',
                'REPORT_ID'          => 'report_id',
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
                'NP'     => 'policy_np',
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
        'org_name'           => [ 'parent' => 'rmd', 'type' => 'M' ],
        'email'              => [ 'parent' => 'rmd', 'type' => 'M', 'default' => null ],
        'extra_contact_info' => [ 'parent' => 'rmd', 'type' => 'M', 'default' => null ],
        'report_id'          => [ 'parent' => 'rmd', 'type' => 'M' ],
        'd_range'            => [
            'parent' => 'rmd',
            'children' => [
                'BEGIN' => 'begin_time',
                'END' => 'end_time'
            ]
        ],
        'domain'       => [ 'parent' => 'p_p', 'type' => 'M' ],
        'error_string' => [ 'parent' => 'rmd', 'type' => 'M', 'default' => null ],
        'begin_time'   => [ 'parent' => 'd_range', 'type' => 'D', 'key' => 'begin' ],
        'end_time'     => [ 'parent' => 'd_range', 'type' => 'D', 'key' => 'end' ],
        'policy_adkim' => [ 'parent' => 'p_p', 'type' => 'P', 'key' => 'adkim', 'default' => null ],
        'policy_aspf'  => [ 'parent' => 'p_p', 'type' => 'P', 'key' => 'aspf', 'default' => null ],
        'policy_p'     => [ 'parent' => 'p_p', 'type' => 'P', 'key' => 'p', 'default' => null ],
        'policy_sp'    => [ 'parent' => 'p_p', 'type' => 'P', 'key' => 'sp', 'default' => null ],
        'policy_np'    => [ 'parent' => 'p_p', 'type' => 'P', 'key' => 'np', 'default' => null ],
        'policy_pct'   => [ 'parent' => 'p_p', 'type' => 'P', 'key' => 'pct', 'default' => null ],
        'policy_fo'    => [ 'parent' => 'p_p', 'type' => 'P', 'key' => 'fo', 'default' => null ],
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
