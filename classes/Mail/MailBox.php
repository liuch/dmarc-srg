<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2025 Aleksey Andreev (liuch)
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

namespace Liuch\DmarcSrg\Mail;

abstract class MailBox
{
    public const SEARCH_ALL    = 1;
    public const SEARCH_SEEN   = 2;
    public const SEARCH_UNSEEN = 3;
    public const ORDER_ASCENT  = 1;
    public const ORDER_DESCENT = 2;

    protected $host;
    protected $mbox;
    protected $name;
    protected $uname;
    protected $passw;
    protected $authm;
    protected $encrypt;
    protected $nocert;
    protected $a_excl;

    private $expunge_f = false;

    public function __construct(array $params)
    {
        $this->uname = $params['username'];
        $this->passw = $params['password'];
        $this->authm = $params['authentication'] ?? 'plain';
        $this->name  = $params['name'] ?? '';
        if (strlen($this->name) === 0) {
            $name = $this->uname;
            $pos = strpos($name, '@');
            if ($pos !== false && $pos !== 0) {
                $name = substr($name, 0, $pos);
            }
            $this->name = $name;
        }
        $this->mbox    = $params['mailbox'];
        $this->host    = $params['host'];
        $this->encrypt = $params['encryption'] ?? '';
        $this->nocert  = $params['novalidate-cert'] ?? false;

        $aexcl = $params['auth_exclude'] ?? null;
        switch (gettype($aexcl)) {
            case 'string':
                $this->a_excl = [ $aexcl ];
                break;
            case 'array':
                $this->a_excl = [];
                for ($i = 0; $i < count($aexcl); ++$i) {
                    if (gettype($aexcl[$i]) == 'string') {
                        $this->a_excl[] = $aexcl[$i];
                    }
                }
                break;
            default:
                $this->a_excl = [];
                break;
        }
    }

    public function __destruct()
    {
        if ($this->expunge_f) {
            $this->expunge();
        }
        $this->cleanup();
    }

    public function __clone(): void
    {
        $this->expunge_f = false;
    }

    public function setExpunge(): void
    {
        $this->expunge_f = true;
    }

    abstract public function expunge(): void;

    abstract public function cleanup(): void;

    public function name(): string
    {
        return $this->name;
    }

    public function authMethod(): string
    {
        return $this->authm;
    }

    public function host(): string
    {
        return $this->host;
    }

    public function folder(): string
    {
        return $this->mbox;
    }

    abstract public function childMailbox(string $folder);

    abstract public function check(): array;

    abstract public function messages(int $search_criteria, int $sort_criteria): array;

    /**
     * Checks whether the folder exists and creates it if it is not
     *
     * @param string $folder
     *
     * @return void
     */
    abstract public function ensureMailbox($folder): void;
}
