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

namespace Liuch\DmarcSrg\Mail\ImapEngine;

use Liuch\DmarcSrg\Core;
use Liuch\DmarcSrg\ErrorHandler;
use Liuch\DmarcSrg\Exception\LogicException;
use Liuch\DmarcSrg\Exception\MailboxException;

use DirectoryTree\ImapEngine\Mailbox as IMailbox;
use DirectoryTree\ImapEngine\Exceptions\Exception as IException;
use DirectoryTree\ImapEngine\Exceptions\ImapCommandException;

class MailBox extends \Liuch\DmarcSrg\Mail\MailBox
{
    private $conn   = null;
    private $folder = null;

    public function __clone(): void
    {
        parent::__clone();
        $this->conn = null;
        $this->folder = null;
    }

    /**
     * Deletes messages marked for deletion
     *
     * @return void
     */
    public function expunge(): void
    {
        try {
            if ($this->conn && $this->conn->connected()) {
                $this->folder->expunge();
            }
        } catch (IException $e) {
            Core::instance()->logger()->error("IMAP error: {$e->getMessage()}");
        }
    }

    /**
     * Deletes messages marked for deletion, if any, and closes the connection
     *
     * @return void
     */
    public function cleanup(): void
    {
        try {
            if ($this->conn && $this->conn->connected()) {
                $this->conn->disconnect();
                $this->conn = null;
            }
        } catch (IException $e) {
            Core::instance()->logger()->error("IMAP error: {$e->getMessage()}");
        }
    }

    public function childMailbox(string $folder)
    {
        $this->ensureConnection();
        $mbox = $this->folder->path() . $this->folder->delimiter() . $folder;

        $child = clone $this;
        $child->mbox = $mbox;
        return $child;
    }

    /**
     * Requests and returns the mailbox status as an array
     *
     * @return array
     */
    public function check(): array
    {
        $res = [];
        try {
            $this->ensureConnection();
            try {
                $res = $this->folder->status();
                $attrs = $this->folder->flags();
                if (in_array('\NoInferiors', $attrs)) {
                    throw new MailboxException('The mailbox may not have any children mailboxes');
                }
                if (in_array('\Noselect', $attrs)) {
                    throw new MailboxException('The resource is not a mailbox');
                }
            } catch (IException $e) {
                Core::instance()->logger()->error("IMAP error: {$e->getMessage()}");
                throw new MailboxException("Failed to get mailbox status", -1, $e);
            }
        } catch (MailboxException $e) {
            return ErrorHandler::exceptionResult($e);
        }
        return [
            'error_code' => 0,
            'message'    => 'Successfully',
            'status'     => [
                'messages' => $res['MESSAGES'] ?? 0,
                'unseen'   => $res['UNSEEN'] ?? 0
            ]
        ];
    }

    public function messages(int $search_criteria, int $sort_criteria): array
    {
        $this->ensureConnection();

        $query = $this->folder->messages()->withHeaders();
        switch ($search_criteria) {
            case static::SEARCH_ALL:
                $query->all();
                break;
            case static::SEARCH_SEEN:
                $query->seen();
                break;
            case static::SEARCH_UNSEEN:
                $query->unseen();
                break;
            default:
                throw new LogicException('Wrong search criteria value');
        }

        // Since the library has a strange implementation of sorting, this implementation is used.
        switch ($sort_criteria) {
            case static::ORDER_ASCENT:
                //$query->oldest();
                $descending = false;
                break;
            case static::ORDER_DESCENT:
                //$query->newest();
                $descending = true;
                break;
            default:
                throw new LogicException('Wrong sort criteria value');
        }

        $res = [];
        $query->get()->sortBy(function ($i_msg, $key) {
            return $i_msg->date()->getTimestamp();
        }, SORT_REGULAR, $descending)->each(function ($i_msg) use (&$res) {
            $res[] = new MailMessage([ 'message' => $i_msg, 'mailbox' => $this ]);
        });

        return $res;
    }

    public function ensureMailbox($folder): void
    {
        $fpath = $this->folder->path() . $this->folder->delimiter() . $folder;
        if (!$this->conn->folders()->find($fpath)) {
            try {
                $this->conn->folders()->create($fpath);
            } catch (IException $e) {
                Core::instance()->logger()->error("IMAP error: {$e->getMessage()}");
                throw new MailboxException('IMAP: Cannot create a mailbox folder', -1, $e);
            }
        }
    }

    private function ensureConnection(): void
    {
        if (!$this->conn) {
            if (preg_match('/^(.+):(\d+)$/', $this->host, $host_a) === 1) {
                $host = $host_a[1];
                $port = intval($host_a[2]);
            } else {
                $host = $this->host;
                $port = 0;
            }
            $i = strrpos($this->host, ':');

            $params = [
                'host'          => $host,
                'username'      => $this->uname,
                'password'      => $this->passw,
                'encryption'    => $this->encrypt == 'none' ? null : $this->encrypt,
                'validate_cert' => !$this->nocert
            ];
            switch ($this->authm) {
                case 'plain':
                    break;
                case 'oauth':
                    $params['authentication'] = $this->authm;
                    break;
                default:
                    throw new LogicException('Unknown authentication method');
            }
            if ($port) {
                $params['port'] = $port;
            }
            $this->conn = new IMailbox($params);
        } elseif ($this->conn->connected()) {
            return;
        }
        try {
            $this->conn->connect();
        } catch (IException $e) {
            Core::instance()->logger()->error("IMAP error: {$e->getMessage()}");
            if ($e instanceof ImapCommandException) {
                throw new MailboxException("IMAP: Authentication failed", -1, $e);
            } else {
                throw new MailboxException("IMAP: Connection failed", -1, $e);
            }
        }
        try {
            $this->folder = $this->conn->folders()->find($this->mbox);
            if ($this->folder === null) {
                throw new MailboxException("IMAP: Mailbox `{$this->mbox}` not found");
            }
            $this->folder->select();
        } catch (IException $e) {
            Core::instance()->logger()->error("IMAP error: {$e->getMessage()}");
            throw new MailboxException("IMAP: Cannot select mailbox", -1, $e);
        }
    }
}
