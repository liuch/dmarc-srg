<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2026 Aleksey Andreev (liuch)
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
 * This file contains the ReportSourceCollection
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Collections;

/**
 * Abstract class that describes a collection for incoming DMARC report sources
 * like mailboxes, directiries, S3 instances
 */
abstract class ReportSourceCollection implements \Iterator, \Countable
{
    private $position = 0;

    /**
     * Checks whether an item exists at the specified index
     *
     * @param int $index
     *
     * @return bool
     */
    abstract public function has(int $index): bool;

    /**
     * Returns a collection item by its index
     *
     * @param int $index
     *
     * @return object
     */
    abstract public function get(int $index): object;

    /**
     * Iterator interface implementation
     *
     * @return object
     */
    public function current(): object
    {
        return $this->get($this->position);
    }

    /**
     * Iterator interface implementation
     *
     * @return int
     */
    public function key(): int
    {
        return $this->position;
    }

    /**
     * Iterator interface implementation
     *
     * @return void
     */
    public function next(): void
    {
        $this->position++;
    }

    /**
     * Iterator interface implementation
     *
     * @return void
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * Iterator interface implementation
     *
     * @return bool
     */
    public function valid(): bool
    {
        return $this->has($this->position);
    }
}
