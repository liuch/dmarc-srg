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
    private $columns  = null;
    private $sort_by  = null;
    private $row_list = [];
    private $widths   = null;
    private $minimals = [];
    private $skeleton = [ 'border' => null, 'header' => null, 'data' => null ];

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
        $cc = count($this->columns);
        if (!$cc) {
            return;
        }

        $this->calculateWidths();
        $this->makeSkeleton();
        echo $this->skeleton['border'], PHP_EOL;
        echo $this->generateHeader(), PHP_EOL;
        echo $this->skeleton['border'], PHP_EOL;
        if (count($this->row_list)) {
            $sort_by = $this->sort_by;
            if (!is_null($sort_by) && isset($this->columns[$sort_by])) {
                usort($this->row_list, static function ($a, $b) use ($sort_by) {
                    return $a[$sort_by] <=> $b[$sort_by];
                });
            }
            foreach ($this->row_list as &$row) {
                echo $this->generateRow($row), PHP_EOL;
            }
            unset($row);
        }
        echo $this->skeleton['border'], PHP_EOL;
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
        $border = '+';
        $header = '|';
        $data   = '|';
        for ($i = 0; $i < count($this->widths); ++$i) {
            $w = $this->widths[$i];
            $border .= str_repeat('-', $w + 2) . '+';
            $header .= " %-{$w}s |";
            $d = $this->row_list[0][$i] ?? '';
            if (is_int($d)) {
                $data .= " %{$w}s |";
            } else {
                $data .= " %-{$w}s |";
            }
        }
        $this->skeleton['border'] = $border;
        $this->skeleton['header'] = $header;
        $this->skeleton['data']   = $data;
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
