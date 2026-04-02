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
 * This file contains an abstract class Request
 *
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Requests;

abstract class Request
{
    protected string $path      = '/';
    protected string $method    = '';
    protected array $properties = [];
    protected array $data       = [];

    private int $errorCode  = 0;
    private string $message = '';

    final public function __construct()
    {
        $this->init();
    }

    /**
     * Returns the request's path
     *
     * @return string
     */
    final public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Returns the request's method
     *
     * @return string
     */
    final public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Checks if a property with the passed name exists
     *
     * @param string $name
     *
     * @return bool
     */
    final public function hasProperty(string $name): bool
    {
        return isset($this->properties[$name]);
    }

    /**
     * Checks if a property with the passed name is empty
     *
     * @param string $name
     *
     * @return bool
     */
    final public function emptyProperty(string $name): bool
    {
        return empty($this->properties[$name]);
    }

    /**
     * Sets the request's property with the passed name
     *
     * @param string $name
     * @param string $value
     *
     * @return void
     */
    final public function setProperty(string $name, string $value): void
    {
        $this->properties[$name] = $value;
    }

    /**
     * Returns the request's property by its name or nul if it does not exist
     *
     * @return ?string
     */
    final public function getProperty(string $name): ?string
    {
        return $this->properties[$name] ?? null;
    }

    /**
     * Sets the request data
     *
     * @param array $data
     *
     * @return void
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    /**
     * Returns the request data
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Sets the error code
     *
     * @param int $code
     *
     * @return void
     */
    final public function setErrorCode(int $code): void
    {
        $this->errorCode = $code;
    }

    /**
     * Returns the error code
     *
     * @return int
     */
    final public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    /**
     * Sets the message
     *
     * @param string $text
     *
     * @return void
     */
    final public function setMessage(string $text): void
    {
        $this->message = $text;
    }

    /**
     * Returns the message
     *
     * @return string
     */
    final public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Fills the class with data from the context
     */
    abstract protected function init(): void;
}
