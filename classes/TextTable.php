<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2024 Aleksey Andreev (liuch)
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
 * This file contains the class TextTable
 *
 * @category Common
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg;

/**
 * The class is intended for displaying the table as text
 * Usage example:
 *   (new TextTable([ 'Organization', 'Reports' ]))
 *      ->appendRow([ 'Organization 1', 15 ])
 *      ->appendRow([ 'Organization 2', 12 ])
 *      ->setMinColumnWidth(0, 25)
 *      ->sortBy(1)
 *      ->output();
 *
 */
class TextTable
{
    private $columns   = null;
    private $sort_by   = null;
    private $row_list  = [];
    private $widths    = null;
    private $minimals  = [];
    private $alignment = [];
    private $skeleton  = [ 'border' => null, 'header' => null, 'data' => null ];
    private $borders   = [ 'horizontal' => '-', 'vertical' => '|', 'intersection' => '+' ];

    /**
     * Constructor
     *
     * @param array $columns Array of strings with column names
     */
    public function __construct(array $columns)
    {
        $this->columns = $columns;
    }

    /**
     * Adds a row of with data to the table
     *
     * @param array $row_data Array with row data
     *
     * @retrun self
     */
    public function appendRow(array $row_data)
    {
        $this->row_list[] = $row_data;
        return $this;
    }

    /**
     * Sets the minimal width in characters for the specified column
     *
     * @param int $col   Zero-based index of the column
     * @param int $width Minimum column width
     *
     * @return self
     */
    public function setMinColumnWidth(int $col, int $width)
    {
        if ($col >= 0 && $col < count($this->columns) && $width >= 0) {
            $this->minimals[$col] = $width;
        }
        return $this;
    }

    /**
     * Sets the minimal width for in characters for the all columns at once
     *
     * If the array size is not the same as the number of columns, the extra or missing coulumns are ignored.
     *
     * @param array $widths
     *
     * @return self
     */
    public function setMinColumnsWidth(array $widths)
    {
        $c = min(count($widths), count($this->columns));
        for ($i = 0; $i < $c; ++$i) {
            if (is_int($widths[$i])) {
                $this->minimals[$i] = $widths[$i];
            }
        }
        return $this;
    }

    /**
     * Adjusts data alignment in columns
     *
     * @param int    $col   Zero-based index of the column
     * @param string $align Alignment value. Can be either `left` or `right`.
     *
     * @return self
     */
    public function setColumnAlignment(int $col, string $align)
    {
        if ($col >= 0 && $col < count($this->columns)) {
            switch ($align) {
                case 'left':
                case 'right':
                    $this->alignment[$col] = $align;
            }
        }
        return $this;
    }

    /**
     * Sets the characters for the table borders
     *
     * @param string $horizontal   Horizontal border
     * @param string $vertical     Vertical border
     * @param string $intersection Border at intersections
     *
     * @return self
     */
    public function setBorders(string $horizontal, string $vertical, string $intersection)
    {
        $this->borders['horizontal'] = $horizontal;
        $this->borders['vertical'] = $vertical;
        $this->borders['intersection'] = $intersection;
        return $this;
    }

    /**
     * Specifies by which column the rows in the table should be sorted
     *
     * @param int $col Zero-based index of the column
     *
     * @return self
     */
    public function sortBy(int $col)
    {
        if ($col >= 0 && $col < count($this->columns)) {
            $this->sort_by = $col;
        }
        return $this;
    }

    /**
     * Outputs the final table view to stdout
     *
     * @return void
     */
    public function output(): void
    {
        foreach ($this->toArray() as $s) {
            echo $s, PHP_EOL;
        }
    }

    /**
     * Returns the table as an array of text strings
     *
     * @return array
     */
    public function toArray(): array
    {
        $res = [];
        $cc = count($this->columns);
        if ($cc) {
            $this->calculateWidths();
            $this->makeSkeleton();
            $bf = !empty($this->skeleton['border']);
            if ($bf) {
                $res[] = $this->skeleton['border'];
            }
            $res[] = $this->generateHeader();
            if ($bf) {
                $res[] = $this->skeleton['border'];
            }
            if (count($this->row_list)) {
                $sort_by = $this->sort_by;
                if (!is_null($sort_by) && isset($this->columns[$sort_by])) {
                    usort($this->row_list, static function ($a, $b) use ($sort_by) {
                        return $a[$sort_by] <=> $b[$sort_by];
                    });
                }
                foreach ($this->row_list as &$row) {
                    $res[] = $this->generateRow($row);
                }
                unset($row);
            }
            if ($bf) {
                $res[] = $this->skeleton['border'];
            }
        }
        return $res;
    }

    /**
     * Pre-calculates column widths
     *
     * @return void
     */
    private function calculateWidths(): void
    {
        $this->widths = [];
        for ($i = 0; $i < count($this->columns); ++$i) {
            $width = max(mb_strlen(strval($this->columns[$i])), $this->minimals[$i] ?? 0);
            foreach ($this->row_list as $row) {
                $width = max($width, mb_strlen(strval($row[$i] ?? '')));
            }
            $this->widths[$i] = $width;
        }
    }

    /**
     * Creates templates for outputting table elements
     *
     * @return void
     */
    private function makeSkeleton(): void
    {
        $vborder = $this->borders['vertical'];
        $hborder = $this->borders['horizontal'];
        $iborder = $this->borders['intersection'];

        $border = $iborder;
        $header = $vborder;
        $data   = $vborder;
        for ($i = 0; $i < count($this->widths); ++$i) {
            $w = $this->widths[$i];
            $border .= str_repeat($hborder, $w + 2) . $iborder;
            $d = $this->row_list[0][$i] ?? '';
            switch ($this->alignment[$i] ?? (is_int($d) ? 'right' : 'left')) {
                case 'right':
                    $header .= " %{$w}s {$vborder}";
                    $data   .= " %{$w}s {$vborder}";
                    break;
                case 'left':
                default:
                    $header .= " %-{$w}s {$vborder}";
                    $data   .= " %-{$w}s {$vborder}";
                    break;
            }
        }
        $this->skeleton['border'] = trim($border);
        $this->skeleton['header'] = trim($header);
        $this->skeleton['data']   = trim($data);
    }

    /**
     * Makess a string with titles
     *
     * @return string
     */
    private function generateHeader(): string
    {
        return vsprintf($this->skeleton['header'], $this->columns);
    }

    /**
     * Generates a table row
     *
     * @param array $columns Columns of the row
     *
     * @return string
     */
    private function generateRow(array $columns): string
    {
        return vsprintf($this->skeleton['data'], $columns);
    }
}
