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
 * This file contains an class HttpRequest
 *
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Requests;

use Liuch\DmarcSrg\Exception\ValidationException;

class HttpRequest extends Request
{
    private ?bool $hasJson = null;

    /**
     * Checks if the request contains JSON data
     *
     * @return bool
     */
    final public function hasJsonData(): bool
    {
        if (is_null($this->hasJson)) {
            $this->hasJson = ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '') === 'application/json';
        }
        return $this->hasJson;
    }

    /**
     * Returns the Bearer token from the Authorization header or null if not present
     *
     * @return ?string
     */
    final public function getBearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        return null;
    }

    /**
     * {@inheritDoc}
     *
     * @throws ValidationException
     */
    public function getData(): array
    {
        if (empty($this->data) && $this->hasJsonData()) {
            $content = \file_get_contents('php://input');
            if ($content) {
                $data = json_decode($content, true);
                if (is_null($data)) {
                    throw new ValidationException('Incorrect JSON data');
                }
                $this->data = gettype($data) === 'array' ? $data : [ $data ];
            }
        }
        return $this->data;
    }

    /**
     * {@inheritDoc}
     */
    protected function init(): void
    {
        $this->path = $_SERVER['REQUEST_URI'] ?? '/';
        $this->method = $_SERVER['REQUEST_METHOD'] ?? '';
        if ($this->method === 'GET') {
            $this->properties = $_GET ?? [];
        } else {
            $this->properties = $_POST ?? [];
        }
    }
}
