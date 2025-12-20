<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2020-2024 Aleksey Andreev (liuch)
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

namespace Liuch\DmarcSrg\Mail\PhpExtension;

use Liuch\DmarcSrg\Core;
use Liuch\DmarcSrg\ErrorHandler;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\LogicException;
use Liuch\DmarcSrg\Exception\MailboxException;

class MailBox extends \Liuch\DmarcSrg\Mail\MailBox
{
    private $conn;
    private $server;
    private $delim;
    private $attrs;
    private $options;

    public function __construct(array $params)
    {
        parent::__construct($params);
        $this->conn = null;

        switch ($this->encrypt) {
            case 'ssl':
            default:
                $flags = '/ssl';
                break;
            case 'none':
                $flags = '/notls';
                break;
            case 'starttls':
                $flags = '/tls';
                break;
        }
        if ($this->nocert) {
            $flags .= '/novalidate-cert';
        }

        $this->server = sprintf('{%s/imap%s}', $this->host, $flags);
        $this->options = [];

        if (isset($params['auth_exclude'])) {
            $auth_exclude = $params['auth_exclude'];
            switch (gettype($auth_exclude)) {
                case 'string':
                    $auth_exclude = [ $auth_exclude ];
                    break;
                case 'array':
                    break;
                default:
                    $auth_exclude = null;
                    break;
            }
            if ($auth_exclude) {
                $this->options['DISABLE_AUTHENTICATOR'] = $auth_exclude;
            }
        }
    }

    public function __clone(): void
    {
        parent::__clone();
        $this->conn = null;
    }

    /**
     * Deletes messages marked for deletion
     *
     * @return void
     */
    public function expunge(): void
    {
        if (extension_loaded('imap')) {
            self::resetErrorStack();
            try {
                imap_expunge($this->conn);
            } catch (\ErrorException $e) {
            }
            $this->logImapError('imap_expunge');
        }
    }

    /**
     * Deletes messages marked for deletion, if any, and closes the connection
     *
     * @return void
     */
    public function cleanup(): void
    {
        if (extension_loaded('imap')) {
            self::resetErrorStack();
            if (!is_null($this->conn)) {
                try {
                    imap_close($this->conn);
                    $this->conn = null;
                } catch (\ErrorException $e) {
                }
                $this->logImapError('imap_close');
            }
        }
    }

    /**
     * Returns the underlying connection
     *
     * @return \IMAP\Connection
     */
    public function connection()
    {
        $this->ensureConnection();
        return $this->conn;
    }

    public function connect(): void
    {
        $this->ensureConnection();
    }

    public function delimiter(): string
    {
        if (!$this->delim) {
            $this->ensureConnection();
        }
        return $this->delim;
    }

    public function childMailbox(string $folder)
    {
        $this->ensureConnection();
        try {
            $mb_list = imap_list(
                $this->conn,
                self::utf8ToMutf7($this->server),
                self::utf8ToMutf7($this->mbox) . $this->delim . self::utf8ToMutf7($folder)
            );
        } catch (\ErrorException $e) {
            $mb_list = false;
        }
        $this->logImapError('imap_list');
        if (!$mb_list) {
            return null;
        }
        $child = clone $this;
        $child->mbox .= $this->delim . $folder;
        return $child;
    }

    public function check(): array
    {
        try {
            $this->ensureConnection();
            try {
                $res = imap_status(
                    $this->conn,
                    self::utf8ToMutf7($this->server . $this->mbox),
                    SA_MESSAGES | SA_UNSEEN
                );
            } catch (\ErrorException $e) {
                $res = false;
            }
            $error_message = $this->logImapError();
            if (!$res) {
                throw new MailboxException($error_message ?? 'Failed to get the mail box status');
            }

            if ($this->attrs & \LATT_NOSELECT) {
                throw new MailboxException('The resource is not a mailbox');
            }

            $this->checkRights();
        } catch (MailboxException $e) {
            return ErrorHandler::exceptionResult($e);
        }
        return [
            'error_code' => 0,
            'message'    => 'Successfully',
            'status'     => [
                'messages' => $res->messages,
                'unseen'   => $res->unseen
            ]
        ];
    }

    public function messages(int $search_criteria, int $sort_criteria): array
    {
        $this->ensureConnection();

        switch ($search_criteria) {
            case static::SEARCH_ALL:
                $search = 'ALL';
                break;
            case static::SEARCH_SEEN:
                $search = 'SEEN';
                break;
            case static::SEARCH_UNSEEN:
                $search = 'UNSEEN';
                break;
            default:
                throw new LogicException('imap_sort: Incorrect search criteria');
        }
        switch ($sort_criteria) {
            case static::ORDER_ASCENT:
                $reverse = 0;
                break;
            case static::ORDER_DESCENT:
                $reverse = 1;
                break;
            default:
                throw new LogicException('imap_sort: Incorrect sort criteria');
        }
        try {
            $res = imap_sort($this->conn, SORTDATE, $reverse, SE_NOPREFETCH | SE_UID, $search);
        } catch (\ErrorException $e) {
            $res = false;
        }
        $error_message = $this->logImapError('imap_sort');
        if ($res === false) {
            if ($error_message) {
                throw new MailboxException('Failed to sort email messages', -1, new \ErrorException($error_message));
            }
            $res = [];
        }
        return array_map(function ($number) {
            return new MailMessage([ 'mailbox' => $this, 'number' => $number ]);
        }, $res);
    }

    public function ensureMailbox($folder): void
    {
        $mbn = self::utf8ToMutf7($folder);
        $srv = self::utf8ToMutf7($this->server);
        $mbo = self::utf8ToMutf7($this->mbox);
        $this->ensureConnection();
        try {
            $mb_list = imap_list($this->conn, $srv, $mbo . $this->delim . $mbn);
        } catch (\ErrorException $e) {
            $mb_list = false;
        }
        $error_message = $this->logImapError('imap_list');
        if (empty($mb_list)) {
            if ($error_message) {
                throw new MailboxException(
                    'Failed to get the list of mailboxes',
                    -1,
                    new \ErrorException($error_message)
                );
            }

            $new_mailbox = "{$srv}{$mbo}{$this->delim}{$mbn}";
            try {
                $res = imap_createmailbox($this->conn, $new_mailbox);
            } catch (\ErrorException $e) {
                $res = false;
            }
            $error_message = $this->logImapError('imap_createmailbox');
            if (!$res) {
                throw new MailboxException(
                    'Failed to create a new mailbox',
                    -1,
                    new \ErrorException($error_message ?? 'Unknown')
                );
            }

            try {
                imap_subscribe($this->conn, $new_mailbox);
            } catch (\ErrorException $e) {
            }
            $this->logImapError('imap_subscribe');
        }
    }

    public static function resetErrorStack()
    {
        imap_errors();
        imap_alerts();
    }

    /**
     * It's a replacement for the standard function imap_utf8_to_mutf7
     *
     * @param string $s A UTF-8 encoded string
     *
     * @return string|false
     */
    public static function utf8ToMutf7(string $s)
    {
        return mb_convert_encoding($s, 'UTF7-IMAP', 'UTF-8');
    }

    private function ensureConnection()
    {
        if (is_null($this->conn)) {
            if (isset($this->authm) && $this->authm !== 'plain') {
                throw new LogicException('Unknown authentication method');
            }
            $error_message = null;
            $srv = self::utf8ToMutf7($this->server);
            try {
                $this->conn = imap_open(
                    $srv,
                    $this->uname,
                    $this->passw,
                    OP_HALFOPEN,
                    0,
                    $this->options
                );
            } catch (\ErrorException $e) {
                $this->conn = null;
            }
            if ($this->conn) {
                $mbx = self::utf8ToMutf7($this->mbox);
                try {
                    $mb_list = imap_getmailboxes($this->conn, $srv, $mbx);
                } catch (\ErrorException $e) {
                    $mb_list = null;
                }
                if ($mb_list && count($mb_list) === 1) {
                    $this->delim = $mb_list[0]->delimiter ?? '/';
                    $this->attrs = $mb_list[0]->attributes ?? 0;
                    try {
                        if (imap_reopen($this->conn, $srv . $mbx)) {
                            return;
                        }
                    } catch (\ErrorException $e) {
                    }
                } else {
                    $error_message = "Mailbox `{$this->mbox}` not found";
                }
            }
            if (!$error_message) {
                $error_message = imap_last_error();
                if (!$error_message) {
                    $error_message = 'Cannot connect to the mail server';
                }
            }
            Core::instance()->logger()->error("IMAP error: {$error_message}");
            self::resetErrorStack();
            if ($this->conn) {
                try {
                    imap_close($this->conn);
                } catch (\ErrorException $e) {
                }
                $this->logImapError('imap_close');
            }
            $this->conn = null;
            throw new MailboxException($error_message);
        }
    }

    /**
     * Extracts and logs IMAP errors if any
     *
     * @param string $prefix Prefix for the log entry line
     *
     * @return string|null Error string
     */
    public function logImapError(string $prefix = 'IMAP error')
    {
        if ($error_message = imap_last_error()) {
            self::resetErrorStack();
            $error_message = "{$prefix}: {$error_message}";
            Core::instance()->logger()->error($error_message);
            return $error_message;
        }
        return null;
    }

    private function checkRights(): void
    {
        if ($this->attrs & \LATT_NOINFERIORS) {
            throw new SoftException('The mailbox may not have any children mailboxes');
        }

        if (!function_exists('imap_getacl')) {
            return;
        }

        $mbox = self::utf8ToMutf7($this->mbox);
        try {
            $acls = imap_getacl($this->conn, $mbox);
        } catch (\ErrorException $e) {
            // It's not possible to get the ACLs information
            $acls = false;
        }
        $this->logImapError('imap_getacl');
        if ($acls !== false) {
            $needed_rights_map = [
                'l' => 'LOOKUP',
                'r' => 'READ',
                's' => 'WRITE-SEEN',
                't' => 'WRITE-DELETE',
                'k' => 'CREATE'
            ];
            $result = [];
            $needed_rights = array_keys($needed_rights_map);
            foreach ([ "#{$this->uname}", '#authenticated', '#anyone' ] as $identifier) {
                if (isset($acls[$identifier])) {
                    $rights = $acls[$identifier];
                    foreach ($needed_rights as $r) {
                        if (!str_contains($rights, $r)) {
                            $result[] = $needed_rights_map[$r];
                        }
                    }
                    break;
                }
            }
            if (count($result) > 0) {
                throw new SoftException(
                    'Not enough rights. Additionally, these rights are required: ' . implode(', ', $result)
                );
            }
        }
    }
}
